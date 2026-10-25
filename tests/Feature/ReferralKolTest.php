<?php

namespace Tests\Feature;

use App\Livewire\Admin\KolPerforma;
use App\Livewire\Admin\Kupon;
use App\Livewire\Front\KeranjangHalaman;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Keranjang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Referral KOL (checklist Tahap 3).
 *
 * Yang diuji adalah rantai utuhnya, bukan satu potong: migrasi menambah kolom
 * pemilik, halaman Kupon dapat menetapkan pemilik dan membentuk tautan
 * berbagi, tautan itu benar-benar memasang kupon di keranjang pembeli, dan
 * halaman Performa KOL menghitung penjualan hanya dari pesanan yang sah.
 *
 * Yang sengaja TIDAK diuji: jumlah klik atau kunjungan. Angka itu tidak ada
 * di aplikasi ini, dan menambahkannya menuntut penanda di setiap halaman.
 */
class ReferralKolTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'kol-admin@contoh.test']);
    }

    private function kupon(array $atribut = []): Coupon
    {
        return Coupon::query()->create($atribut + [
            'code' => 'KOL'.random_int(100, 999),
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'used_count' => 0,
        ]);
    }

    /** Baris carts untuk sesi tamu, dipakai keranjang berbasis sesi. */
    private function keranjangTamu(): Keranjang
    {
        return app(Keranjang::class);
    }

    // ── 3.1 Migrasi ─────────────────────────────────────────────────

    public function test_kolom_pemilik_kol_ada_dan_nullable(): void
    {
        $kolom = [
            'kol_nama',
            'kol_slug',
            'kol_kontak',
        ];

        foreach ($kolom as $nama) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Schema::hasColumn('coupons', $nama),
                "Kolom {$nama} seharusnya ada di tabel coupons."
            );
        }

        // Baris tanpa pemilik tetap sah — kupon lama tidak ikut gagal.
        $kupon = $this->kupon();

        $this->assertNull($kupon->kol_slug);
        $this->assertFalse($kupon->punyaKol());
    }

    public function test_dua_kupon_tanpa_pemilik_boleh_hidup_bersamaan(): void
    {
        $this->kupon(['code' => 'BIASA-A']);
        $this->kupon(['code' => 'BIASA-B']);

        // Indeks unik parsial hanya berlaku untuk baris yang punya slug.
        // Tanpa itu, dua kupon biasa gagal tersimpan karena slug sama-sama NULL.
        $this->assertSame(2, Coupon::query()->whereNull('kol_slug')->count());
    }

    // ── 3.3 Menetapkan pemilik + tautan berbagi ──────────────────────

    public function test_kupon_dengan_nama_kol_membentuk_slug_dari_nama(): void
    {
        $kupon = $this->kupon(['code' => 'KOL-BUDI', 'kol_nama' => 'Budi Santoso']);

        // Slug dibentuk otomatis supaya tautan yang dibagikan KOL cocok dengan
        // yang tercatat di laporan.
        $this->assertSame('budi-santoso', $kupon->fresh()->kol_slug);
        $this->assertTrue($kupon->punyaKol());
    }

    public function test_tautan_berbagi_menunjuk_beranda_dengan_kode_kupon(): void
    {
        $kupon = $this->kupon(['code' => 'KOL-TAUTAN', 'kol_nama' => 'Siti']);

        $this->assertSame(
            route('front.home', ['kupon' => 'KOL-TAUTAN']),
            $kupon->tautanKol(),
        );
    }

    public function test_kupon_biasa_tidak_punya_tautan_berbagi(): void
    {
        $this->assertNull($this->kupon()->tautanKol());
    }

    public function test_slug_kol_harus_unik(): void
    {
        $this->kupon(['code' => 'KOL-SATU', 'kol_nama' => 'Budi', 'kol_slug' => 'budi']);
        $this->actingAs($this->admin());

        Livewire::test(Kupon::class)
            ->set('code', 'KOL-DUA')
            ->set('type', 'percent')
            ->set('value', '10')
            ->set('kol_nama', 'Budi Lain')
            ->set('kol_slug', 'budi')
            ->call('simpan')
            ->assertHasErrors('kol_slug');
    }

    public function test_form_admin_menyimpan_pemilik_kol(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Kupon::class)
            ->set('code', 'kupon-kol')
            ->set('type', 'percent')
            ->set('value', '10')
            ->set('kol_nama', 'Dewi Lestari')
            ->set('kol_kontak', '08123456789')
            ->call('simpan')
            ->assertHasNoErrors();

        $kupon = Coupon::query()->where('code', 'KUPON-KOL')->firstOrFail();

        $this->assertSame('Dewi Lestari', $kupon->kol_nama);
        $this->assertSame('dewi-lestari', $kupon->kol_slug);
        $this->assertSame('08123456789', $kupon->kol_kontak);
    }

    // ── Tautan berbagi benar-benar memasang kupon ───────────────────

    public function test_tautan_berbagi_memasang_kupon_di_keranjang(): void
    {
        $kupon = $this->kupon(['code' => 'KOL-PAKAI', 'kol_nama' => 'Budi']);

        $keranjang = $this->keranjangTamu();

        // Permintaan datang dari tautan KOL. mount() membaca request()->query()
        // langsung, jadi yang dipasang di sini adalah parameter permintaannya —
        // bukan URL halaman lain.
        $this->get('/keranjang?kupon=KOL-PAKAI');

        request()->merge(['kupon' => 'KOL-PAKAI']);

        Livewire::test(KeranjangHalaman::class, ['keranjang' => $keranjang])
            ->assertSet('kodeKupon', 'KOL-PAKAI');

        $this->assertSame($kupon->id, $keranjang->kupon()?->id);
    }

    public function test_tautan_berbagi_kode_tidak_sah_tidak_memasang_apa_pun(): void
    {
        $keranjang = $this->keranjangTamu();

        request()->merge(['kupon' => 'TIDAK-ADA-KODE-INI']);

        Livewire::test(KeranjangHalaman::class, ['keranjang' => $keranjang])
            ->assertSet('kodeKupon', '');

        $this->assertNull($keranjang->kupon());
    }

    public function test_kode_yang_sudah_diketik_pembeli_tidak_ditimpa_tautan(): void
    {
        $milikKupon = $this->kupon(['code' => 'KOL-TIMPA', 'kol_nama' => 'Budi']);
        $pilihanSendiri = $this->kupon(['code' => 'PILIHAN-SENDIRI']);

        $keranjang = $this->keranjangTamu();
        $keranjang->terapkanKupon($pilihanSendiri);

        request()->merge(['kupon' => 'KOL-TIMPA']);

        Livewire::test(KeranjangHalaman::class, ['keranjang' => $keranjang])
            ->assertSet('kodeKupon', 'PILIHAN-SENDIRI');

        $this->assertSame($pilihanSendiri->id, $keranjang->kupon()?->id);
        $this->assertNotSame($milikKupon->id, $keranjang->kupon()?->id);
    }

    // ── 3.4 Performa KOL ────────────────────────────────────────────

    public function test_halaman_performa_kol_terdaftar_dengan_middleware_admin(): void
    {
        $this->assertTrue(Route::has('admin.kol.index'));

        $rute = Route::getRoutes()->getByName('admin.kol.index');
        $this->assertContains('auth', $rute->gatherMiddleware());
        $this->assertContains(\App\Http\Middleware\PastikanAdmin::class, $rute->gatherMiddleware());
    }

    public function test_performa_hanya_menghitung_pesanan_yang_sah(): void
    {
        $kol = $this->kupon(['code' => 'KOL-PERFORMA', 'kol_nama' => 'Budi']);

        // Dua pesanan sah dan satu dibatalkan. Yang dibatalkan tidak boleh
        // dihitung — uangnya tidak pernah masuk.
        foreach (['paid', 'completed'] as $status) {
            $pesanan = Order::query()->create([
                'order_number' => 'IK-2026'.random_int(1000, 9999).'-'.strtoupper(\Illuminate\Support\Str::random(4)),
                'status' => $status,
                'payment_status' => 'paid',
                'coupon_id' => $kol->id,
                'subtotal' => 1000000,
                'discount_amount' => 100000,
                'grand_total' => 900000,
                'shipping_address_snapshot' => ['nama' => 'Penerima Uji'],
            ]);
        }

        Order::query()->create([
            'order_number' => 'IK-2026'.random_int(1000, 9999).'-'.strtoupper(\Illuminate\Support\Str::random(4)),
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'coupon_id' => $kol->id,
            'subtotal' => 1000000,
            'discount_amount' => 100000,
            'grand_total' => 900000,
            'shipping_address_snapshot' => ['nama' => 'Penerima Uji'],
        ]);

        $this->actingAs($this->admin());

        $komponen = Livewire::test(KolPerforma::class);
        $baris = $komponen->viewData('daftar')->first();

        $this->assertSame(2, (int) $baris->pesanan_sah);
        $this->assertEquals(1800000.0, (float) $baris->nilai_penjualan);
        $this->assertEquals(200000.0, (float) $baris->potongan_diberikan);
    }

    public function test_performa_menghitung_pesanan_belum_dibayar_sebagai_bukan_sah(): void
    {
        $kol = $this->kupon(['code' => 'KOL-PENDING', 'kol_nama' => 'Siti']);

        Order::query()->create([
            'order_number' => 'IK-2026'.random_int(1000, 9999).'-'.strtoupper(\Illuminate\Support\Str::random(4)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'coupon_id' => $kol->id,
            'subtotal' => 1000000,
            'discount_amount' => 100000,
            'grand_total' => 900000,
            'shipping_address_snapshot' => ['nama' => 'Penerima Uji'],
        ]);

        $this->actingAs($this->admin());

        $komponen = Livewire::test(KolPerforma::class);

        $this->assertSame(0, (int) $komponen->viewData('daftar')->first()->pesanan_sah);
    }

    public function test_kupon_tanpa_pemilik_tidak_muncul_di_performa_kol(): void
    {
        $this->kupon(['code' => 'KUPON-BIASA']);
        $kol = $this->kupon(['code' => 'KOL-MUNCUL', 'kol_nama' => 'Budi']);

        $this->actingAs($this->admin());

        $komponen = Livewire::test(KolPerforma::class);
        $kode = $komponen->viewData('daftar')->pluck('code');

        $this->assertTrue($kode->contains('KOL-MUNCUL'));
        $this->assertFalse($kode->contains('KUPON-BIASA'));
    }
}
