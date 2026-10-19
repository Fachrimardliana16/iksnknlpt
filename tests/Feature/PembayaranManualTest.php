<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pembayaran\PembayaranManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Uji pembayaran manual dan notifikasi pesanan (checklist F9-05..08).
 *
 * Bagian paling berisiko di sini adalah hubungan antara verifikasi pembayaran
 * dan stok. Pesanan lunas masih MENAHAN stok, bukan menguranginya. Melewati
 * StatusPesananService saat memverifikasi akan membuat stok fisik berkurang
 * dua kali — sekali saat verifikasi, sekali lagi saat pesanan selesai.
 */
class PembayaranManualTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function pesanan(array $ganti = []): Order
    {
        $produk = Product::factory()->create(['category_id' => \App\Models\Category::factory()->create()->id]);

        $varian = ProductVariant::factory()->create([
            'product_id' => $produk->id,
            'stock_quantity' => 10,
            'reserved_quantity' => 2,
        ]);

        $order = Order::query()->create(array_merge([
            'order_number' => 'IK-UJI-'.fake()->unique()->numerify('####'),
            'status' => StatusPesanan::Pending,
            'payment_status' => StatusPembayaran::Unpaid,
            'payment_method' => MetodePembayaran::Manual,
            'subtotal' => 500000,
            'grand_total' => 500000,
            'currency' => 'IDR',
            'guest_email' => 'pembeli@contoh.test',
            'guest_phone' => '081234567890',
            'shipping_address_snapshot' => ['recipient_name' => 'Budi', 'city' => 'Bandung'],
        ], $ganti));

        $order->items()->create([
            'product_id' => $produk->id,
            'product_variant_id' => $varian->id,
            'product_name' => $produk->title_id,
            'sku' => $produk->sku,
            'unit_price' => 500000,
            'quantity' => 2,
            'subtotal' => 1000000,
        ]);

        return $order->fresh(['items']);
    }

    private function bukti(): UploadedFile
    {
        // Gambar sungguhan: pemeriksaan membaca isi berkas, bukan namanya.
        return UploadedFile::fake()->image('bukti.png', 600, 800);
    }

    // ── Unggah bukti ────────────────────────────────────────────────

    public function test_bukti_transfer_tersimpan_di_disk_privat(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan();

        $hasil = app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti(), 'transfer BCA');

        $this->assertNotEmpty($hasil['bukti']);
        Storage::disk('local')->assertExists($hasil['bukti']);

        // Bukti transfer memuat nama pengirim dan nomor rekening. Ia tidak
        // boleh berada di disk yang dapat diakses publik.
        Storage::disk('public')->assertMissing($hasil['bukti']);

        $bayar = Payment::query()->where('order_id', $pesanan->id)->firstOrFail();

        $this->assertSame(MetodePembayaran::Manual, $bayar->provider);
        $this->assertSame('menunggu-verifikasi', $bayar->provider_status);
        $this->assertNull($bayar->verified_at);
        $this->assertSame(StatusPembayaran::Pending, $pesanan->fresh()->payment_status);
    }

    public function test_berkas_bukan_gambar_atau_pdf_ditolak(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan();

        $jalur = tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($jalur, '<?php system($_GET["c"]); ?>');

        $berkas = new UploadedFile($jalur, 'bukti.png', 'image/png', null, true);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            app(PembayaranManual::class)->unggahBukti($pesanan, $berkas);
        } finally {
            @unlink($jalur);
        }
    }

    public function test_pdf_palsu_ditolak(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan();

        $jalur = tempnam(sys_get_temp_dir(), 'uji');
        // Mime dideteksi sebagai PDF oleh sebagian sistem, tetapi isinya bukan.
        file_put_contents($jalur, "%FDF-1.2\nbukan pdf sungguhan");

        $berkas = new UploadedFile($jalur, 'bukti.pdf', 'application/pdf', null, true);

        try {
            app(PembayaranManual::class)->unggahBukti($pesanan, $berkas);
            $this->fail('PDF palsu seharusnya ditolak.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('bukti', $e->errors());
        } finally {
            @unlink($jalur);
        }
    }

    public function test_pesanan_lunas_menolak_bukti_baru(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan(['payment_status' => StatusPembayaran::Paid]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti());
    }

    public function test_pesanan_dibatalkan_menolak_bukti(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan(['status' => StatusPesanan::Cancelled]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti());
    }

    public function test_batas_tiga_unggahan_per_jam(): void
    {
        Storage::fake('local');
        $pesanan = $this->pesanan();
        $manual = app(PembayaranManual::class);

        for ($i = 0; $i < 3; $i++) {
            $manual->unggahBukti($pesanan, $this->bukti());
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $manual->unggahBukti($pesanan, $this->bukti());
    }

    // ── Verifikasi ──────────────────────────────────────────────────

    public function test_verifikasi_menandai_lunas_tanpa_menyentuh_stok(): void
    {
        Queue::fake();
        $pesanan = $this->pesanan();

        $varian = $pesanan->items->first()->variant;
        $stokAwal = $varian->stock_quantity;
        $tahanAwal = $varian->reserved_quantity;

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        $admin = $this->admin();
        app(PembayaranManual::class)->verifikasi($bayar, $admin->id);

        $pesanan->refresh();

        $this->assertSame(StatusPembayaran::Paid, $pesanan->payment_status);
        $this->assertSame(StatusPesanan::Paid, $pesanan->status);
        $this->assertNotNull($bayar->fresh()->verified_at);
        $this->assertSame($admin->id, $bayar->fresh()->verified_by);

        // Pesanan lunas masih MENAHAN stok. Stok fisik baru berkurang saat
        // pesanan selesai.
        $varian->refresh();
        $this->assertSame($stokAwal, $varian->stock_quantity, 'verifikasi tidak boleh mengurangi stok fisik');
        $this->assertSame($tahanAwal, $varian->reserved_quantity, 'tahanan tetap sampai pesanan selesai');
    }

    public function test_verifikasi_mengirim_notifikasi_pembayaran(): void
    {
        Queue::fake();
        $pesanan = $this->pesanan();

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        app(PembayaranManual::class)->verifikasi($bayar, $this->admin()->id);

        Queue::assertPushed(
            KirimNotifikasiPesanan::class,
            fn (KirimNotifikasiPesanan $j) => $j->pesananId === $pesanan->id
                && $j->kejadian === KirimNotifikasiPesanan::KEJADIAN_DIBAYAR,
        );
    }

    public function test_verifikasi_ganda_tidak_mengubah_apa_pun(): void
    {
        Queue::fake();
        $pesanan = $this->pesanan();

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        $manual = app(PembayaranManual::class);
        $manual->verifikasi($bayar, $this->admin()->id);

        $waktuPertama = $bayar->fresh()->verified_at;

        $manual->verifikasi($bayar->fresh(), $this->admin()->id);

        $this->assertEquals($waktuPertama, $bayar->fresh()->verified_at);
    }

    public function test_penolakan_mengembalikan_ke_belum_bayar(): void
    {
        Queue::fake();
        $pesanan = $this->pesanan(['payment_status' => StatusPembayaran::Pending]);

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        app(PembayaranManual::class)->tolak($bayar, 'Nominal tidak sesuai', $this->admin()->id);

        $this->assertSame(StatusPembayaran::Unpaid, $pesanan->fresh()->payment_status);
        $this->assertSame('ditolak', $bayar->fresh()->provider_status);
        $this->assertSame('Nominal tidak sesuai', $bayar->fresh()->payload_json['alasan_penolakan']);
    }

    public function test_penolakan_tidak_mengubah_belum_bayar_bila_ada_bukti_lain(): void
    {
        Queue::fake();
        $pesanan = $this->pesanan(['payment_status' => StatusPembayaran::Pending]);

        $pertama = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
        ]);

        app(PembayaranManual::class)->tolak($pertama, 'Bukti tidak jelas', $this->admin()->id);

        // Masih ada bukti lain yang menunggu, jadi pesanan tetap menunggu.
        $this->assertSame(StatusPembayaran::Pending, $pesanan->fresh()->payment_status);
    }

    public function test_pembayaran_terverifikasi_tidak_dapat_ditolak(): void
    {
        $pesanan = $this->pesanan();

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'terverifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
            'verified_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        app(PembayaranManual::class)->tolak($bayar, 'coba tolak', $this->admin()->id);
    }

    // ── URL bukti bertanda tangan ───────────────────────────────────

    public function test_url_bukti_bertanda_tangan_dan_berlaku_sementara(): void
    {
        $this->actingAs($this->admin());

        Storage::fake('local');

        $pesanan = $this->pesanan();

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
            'proof_path' => 'bukti-bayar/'.$pesanan->ulid.'/uji.png',
        ]);

        Storage::disk('local')->put('bukti-bayar/'.$pesanan->ulid.'/uji.png', 'isi bukti');

        $url = app(PembayaranManual::class)->urlBukti($bayar);

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);

        // Tanda tangan sah: berkas dapat diambil.
        $this->get($url)->assertOk();

        // URL tanpa tanda tangan ditolak, sekalipun alamatnya benar.
        $polos = route('admin.payments.proof', ['payment' => $bayar->ulid]);
        $this->get($polos)->assertForbidden();
    }

    public function test_bukti_tidak_dapat_diambil_tanpa_sesi_admin(): void
    {
        Storage::fake('local');

        $pesanan = $this->pesanan();

        $bayar = Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
            'proof_path' => 'bukti-bayar/'.$pesanan->ulid.'/uji.png',
        ]);

        Storage::disk('local')->put('bukti-bayar/'.$pesanan->ulid.'/uji.png', 'isi bukti');

        // URL bertanda tangan dibuat tanpa sesi. Tanda tangan yang sah
        // sekalipun tidak cukup untuk berkas pribadi.
        $url = URL::temporarySignedRoute(
            'admin.payments.proof',
            now()->addMinutes(15),
            ['payment' => $bayar->ulid],
        );

        $jawaban = $this->get($url);

        // Tamu diarahkan ke halaman masuk; keduanya sama-sama menolak. Yang
        // penting: berkasnya tidak ikut terkirim.
        $this->assertContains(
            $jawaban->status(),
            [302, 403],
            'bukti tidak boleh dapat diambil tanpa sesi panel',
        );
        $this->assertStringNotContainsString('isi bukti', (string) $jawaban->getContent());
    }

    // ── Halaman pembeli ─────────────────────────────────────────────

    public function test_halaman_unggah_bukti_terbuka_lewat_ulid(): void
    {
        $pesanan = $this->pesanan();

        $this->get(route('front.payment.upload', ['ulid' => $pesanan->ulid]))
            ->assertOk()
            ->assertSee($pesanan->order_number);
    }

    public function test_ulid_pesanan_yang_salah_menghasilkan_404(): void
    {
        $this->get(route('front.payment.upload', ['ulid' => '01abcdefghjkmnpqrstvwxyz00']))
            ->assertNotFound();
    }

    // ── Pengaturan rekening ─────────────────────────────────────────

    /**
     * Regresi: pengaturan terenkripsi harus dapat dibaca kembali.
     *
     * Seeder sempat mengisi `value` sebelum `is_encrypted`, sehingga cast belum
     * melihat tanda enkripsi dan nilainya tersimpan sebagai teks biasa — sementara
     * `is_encrypted` tetap true. Cast lalu gagal mendekripsi dan mengembalikan
     * null tanpa galat, membuat rekening tampak kosong padahal isinya ada.
     */
    public function test_rekening_dibaca_dari_pengaturan_terenkripsi(): void
    {
        \Illuminate\Support\Facades\DB::table('settings')->insert([
            'key' => 'manual_bank_accounts',
            'value' => json_encode([
                ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Iksan Knalpot'],
                ['bank' => 'Mandiri', 'number' => '0987654321', 'holder' => 'Iksan Knalpot'],
            ]),
            'group_name' => 'pembayaran',
            'type' => 'json',
            'label' => 'Rekening transfer manual',
            'is_encrypted' => true,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ditulis lewat model supaya cast yang mengenkripsi. Menulis lewat kueri
        // mentah justru menciptakan baris rusak yang sedang diuji di bawah.
        $setting = \App\Models\Setting::query()->where('key', 'manual_bank_accounts')->first();
        $setting->value = json_encode([
            ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Iksan Knalpot'],
            ['bank' => 'Mandiri', 'number' => '0987654321', 'holder' => 'Iksan Knalpot'],
        ]);
        $setting->save();

        $manual = app(PembayaranManual::class);
        $daftar = $manual->daftarRekening();

        $this->assertCount(2, $daftar, 'dua rekening harus terbaca');
        $this->assertSame('BCA', $daftar[0]['bank']);
        $this->assertSame('1234567890', $daftar[0]['nomor']);
        $this->assertTrue($manual->rekeningLengkap());
    }

    public function test_enkripsi_pengaturan_tidak_kehilangan_nilai(): void
    {
        $setting = \App\Models\Setting::query()->create([
            'key' => 'uji_rahasia',
            'group_name' => 'pembayaran',
            'type' => 'text',
            'label' => 'Uji',
            'is_encrypted' => true,
            'is_public' => false,
        ]);

        $setting->value = 'nilai-rahasia';
        $setting->save();

        // Tersimpan terenkripsi di basis data...
        $mentah = \Illuminate\Support\Facades\DB::table('settings')
            ->where('key', 'uji_rahasia')
            ->value('value');

        $this->assertNotSame('nilai-rahasia', $mentah, 'nilai harus terenkripsi di kolom');
        $this->assertNotNull(\Illuminate\Support\Facades\Crypt::decryptString($mentah));

        // ...dan terbaca kembali lewat cast.
        $this->assertSame('nilai-rahasia', $setting->fresh()->value);
    }
}
