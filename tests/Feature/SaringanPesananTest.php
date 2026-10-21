<?php

use App\Livewire\Admin\PesananDaftar;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saringan status pada daftar pesanan panel.
 *
 * Yang mudah rusak tanpa ketahuan: saringan yang diam-diam tidak menyaring.
 * Gejalanya bukan galat, melainkan daftar yang selalu menampilkan SEMUA
 * pesanan — admin menyaring "pending", melihat tabel penuh, dan menyimpulkan
 * angkanya memang begitu. Karena itu yang diperiksa adalah nomor yang benar-
 * benar tampil di HTML, bukan jumlah pada koleksi.
 */
class SaringanPesananTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    /**
     * @param array<string, mixed> $atribut
     */
    private function pesanan(array $atribut): Order
    {
        // Order tidak punya factory; dibuat langsung seperti test sewaktu lain.
        // Nomor ditulis acak supaya dua pesanan pada satu test tidak bertabrakan.
        return Order::query()->create($atribut + [
            'order_number' => 'IK-2026'.random_int(1000, 9999).'-'.strtoupper(Str::random(4)),
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Penerima Uji'],
        ]);
    }

    public function test_saringan_status_pending_hanya_menampilkan_pending(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $pending = $this->pesanan(['status' => 'pending', 'payment_status' => 'unpaid']);
        $batal = $this->pesanan(['status' => 'cancelled', 'payment_status' => 'unpaid']);
        $lunas = $this->pesanan(['status' => 'paid', 'payment_status' => 'paid']);

        $html = Livewire::test(PesananDaftar::class, ['status' => 'pending'])
            ->html();

        $this->assertStringContainsString($pending->order_number, $html);
        $this->assertStringNotContainsString($batal->order_number, $html);
        $this->assertStringNotContainsString($lunas->order_number, $html);
    }

    public function test_saringan_status_lewat_query_string_dipakai_pada_render_pertama(): void
    {
        // Yang mudah rusak: nilai #[Url] terbaca dari query string. Kalau
        // saringan menerima nilai properti saja (karena sudah di-mount dengan
        // parameter), penyaringan lewat tautan ?status= tidak pernah bekerja.
        $admin = $this->admin();
        $this->actingAs($admin);

        $pending = $this->pesanan(['status' => 'pending', 'payment_status' => 'unpaid']);
        $batal = $this->pesanan(['status' => 'cancelled', 'payment_status' => 'unpaid']);

        $html = Livewire::withQueryParams(['status' => 'pending'])
            ->test(PesananDaftar::class)
            ->html();

        $this->assertStringContainsString($pending->order_number, $html);
        $this->assertStringNotContainsString($batal->order_number, $html);
    }

    public function test_saringan_bayar_hanya_menampilkan_yang_sesuai(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $lunas = $this->pesanan(['status' => 'paid', 'payment_status' => 'paid']);
        $belum = $this->pesanan(['status' => 'pending', 'payment_status' => 'unpaid']);

        $html = Livewire::test(PesananDaftar::class, ['status' => 'semua', 'bayar' => 'paid'])
            ->html();

        $this->assertStringContainsString($lunas->order_number, $html);
        $this->assertStringNotContainsString($belum->order_number, $html);
    }

    public function test_status_tidak_dikenal_dari_query_string_kembali_ke_semua(): void
    {
        // Nilai dari query string harus disaring di mount: yang lolos hanya
        // anggota daftar yang dikenal. Selain itu berarti "semua", bukan galat.
        // Diuji lewat query string karena itulah satu-satunya jalan nilai luar
        // masuk; mount dengan parameter langsung sengaja tidak dipakai — nilai
        // itu datang dari dalam aplikasi sendiri.
        $admin = $this->admin();
        $this->actingAs($admin);

        $pending = $this->pesanan(['status' => 'pending', 'payment_status' => 'unpaid']);

        $html = Livewire::withQueryParams(['status' => 'ngawur-tidak-ada'])
            ->test(PesananDaftar::class)
            ->html();

        $this->assertStringContainsString($pending->order_number, $html);
    }

    public function test_atur_status_menolak_nilai_yang_tidak_dikenal(): void
    {
        // Jalur kedua dan yang dipakai tombol di halaman: metodanya sendiri
        // yang menyaring, bukan mount. Tanpa ini, tombol filter yang terkeliru
        // dapat memasang nilai yang tidak ada dan halaman jadi kosong.
        $admin = $this->admin();
        $this->actingAs($admin);

        $pending = $this->pesanan(['status' => 'pending', 'payment_status' => 'unpaid']);

        Livewire::test(PesananDaftar::class)
            // set() hanya mengisi properti, dan itu memang hanya jalur internal
            // — yang datang dari luar dilalui mount() dan aturStatus().
            ->call('aturStatus', 'ngawur-tidak-ada')
            ->assertSet('status', 'semua')
            ->assertSee($pending->order_number);
    }

    public function test_tombol_saring_urut_berubah_hasil(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // created_at ditulis eksplisit. Dua baris yang dibuat pada detik yang
        // sama punya urutan tidak pasti, dan test yang bergantung pada detik
        // sama lolos lalu gagal bergantian.
        $pertama = $this->pesanan([
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'created_at' => now()->subDay(),
            'grand_total' => 100000,
        ]);
        $kedua = $this->pesanan([
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'created_at' => now(),
            'grand_total' => 900000,
        ]);

        $urutTerbaru = Livewire::test(PesananDaftar::class, ['status' => 'pending', 'urut' => 'terbaru'])->html();

        $this->assertTrue(
            strpos($urutTerbaru, $kedua->order_number) !== false
            && strpos($urutTerbaru, $pertama->order_number) !== false
            && strpos($urutTerbaru, $kedua->order_number) < strpos($urutTerbaru, $pertama->order_number),
            'Urut terbaru harus menaruh pesanan yang lebih dulu dibuat di bawah.',
        );

        $urutTerlama = Livewire::test(PesananDaftar::class, ['status' => 'pending', 'urut' => 'terlama'])->html();

        $this->assertTrue(
            strpos($urutTerlama, $pertama->order_number) < strpos($urutTerlama, $kedua->order_number),
            'Urut terlama harus membalik urutan yang sama.',
        );
    }
}
