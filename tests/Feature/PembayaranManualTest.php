<?php

namespace Tests\Feature;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Pembayaran\PembayaranManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Alur pembayaran manual (checklist F9-05).
 *
 * Yang diuji adalah janji yang mahal kalau salah: berkas bukti tidak pernah
 * tersimpan di disk publik, batas tiga unggahan per jam benar-benar menolak
 * yang keempat, pesanan yang sudah lunas menolak bukti baru, dan admin yang
 * memverifikasi membuat pesanan berpindah ke lunas tanpa menyentuh kolom
 * status langsung.
 */
class PembayaranManualTest extends TestCase
{
    use RefreshDatabase;

    private function pesanan(array $atribut = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'IK-20260101-UJIMNL',
            'status' => StatusPesanan::Pending,
            'payment_status' => StatusPembayaran::Unpaid,
            'payment_method' => 'manual',
            'subtotal' => 500000,
            'grand_total' => 500000,
            'shipping_address_snapshot' => ['recipient_name' => 'Uji', 'city' => 'Sleman'],
            'expires_at' => now()->addDay(),
        ], $atribut));
    }

    /**
     * Berkas bukti yang sah: PNG 400x300 yang benar-benar dibuat.
     *
     * Bukan gambar 4x4 dari string base64. Layanan menolak gambar yang sisi
     * terpendeknya di bawah 100 px ("terlalu kecil untuk dibaca"), dan gambar
     * uji yang terlalu kecil membuat seluruh test gagal karena alasan yang
     * tidak ada hubungannya dengan yang sedang diuji.
     */
    private function bukti(string $nama = 'bukti.png'): UploadedFile
    {
        $im = imagecreatetruecolor(400, 300);
        imagefilledrectangle($im, 0, 0, 399, 299, imagecolorallocate($im, 30, 30, 140));
        imagestring($im, 5, 40, 140, 'BUKTI TRANSFER UJI', imagecolorallocate($im, 255, 255, 255));

        ob_start();
        imagepng($im);
        $biner = (string) ob_get_clean();
        imagedestroy($im);

        return UploadedFile::fake()->createWithContent($nama, $biner);
    }

    public function test_bukti_tersimpan_di_disk_privat_dan_membuat_baris_payments(): void
    {
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $pesanan = $this->pesanan();
        $pesanan->update(['payment_method' => 'manual']);

        $hasil = app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti(), 'Transfer BCA');

        $this->assertNotEmpty($hasil['bukti']);

        // Disk publik selalu memuat .gitignore bawaan Laravel; yang diperiksa
        // adalah tidak ada berkas bukti yang ikut tersimpan di sana.
        // Disk publik di-refresh tiap test: isinya bisa memuat berkas contoh
        // dari seeder (gambar hero). Yang diperiksa adalah tidak ada berkas
        // bukti yang ikut ke sana — dicocokkan lewat namanya.
        $namaBukti = basename($hasil['bukti']);

        $bocor = array_values(array_filter(
            Storage::disk('public')->allFiles(),
            static fn (string $jalur): bool => str_contains($jalur, $namaBukti)
                || str_contains($jalur, 'bukti-bayar'),
        ));

        $this->assertSame([], $bocor, 'Bukti transfer bocor ke disk publik.');
        $this->assertNotEmpty(Storage::disk('local')->allFiles(), 'Bukti tidak tersimpan di disk privat.');

        $payment = Payment::query()->where('order_id', $pesanan->id)->latest('id')->first();

        $this->assertNotNull($payment, 'Baris payments tidak terbentuk.');
        $this->assertSame('manual', $payment->provider instanceof \BackedEnum ? $payment->provider->value : (string) $payment->provider);
        $this->assertNotSame(StatusPembayaran::Paid, $pesanan->fresh()->payment_status, 'Bukti saja tidak boleh melunasi pesanan.');
    }

    public function test_unggahan_keempat_dalam_satu_jam_ditolak(): void
    {
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $pesanan = $this->pesanan();
        $layanan = app(PembayaranManual::class);

        for ($i = 0; $i < 3; $i++) {
            $layanan->unggahBukti($pesanan->fresh(), $this->bukti('bukti-'.$i.'.png'));
        }

        $this->expectException(ValidationException::class);

        try {
            $layanan->unggahBukti($pesanan->fresh(), $this->bukti('bukti-4.png'));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('bukti', $e->errors());
            $this->assertStringContainsString('3 kali', $e->errors()['bukti'][0]);

            throw $e;
        }
    }

    public function test_pesanan_lunas_menolak_bukti_baru(): void
    {
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $pesanan = $this->pesanan(['payment_status' => StatusPembayaran::Paid]);

        $this->expectException(ValidationException::class);

        app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti());
    }

    public function test_admin_memverifikasi_membuat_pesanan_lunas(): void
    {
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-uji@contoh.test',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $pesanan = $this->pesanan();

        $hasil = app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti());

        $payment = Payment::query()->where('order_id', $pesanan->id)->latest('id')->first();

        app(PembayaranManual::class)->verifikasi($payment, $admin->id);

        $segar = $pesanan->fresh();

        $this->assertSame(StatusPembayaran::Paid, $segar->payment_status, 'Verifikasi tidak melunasi pesanan.');
        $this->assertSame(StatusPesanan::Paid, $segar->status, 'Status pesanan tidak berpindah ke paid.');
        $this->assertNotNull($segar->paid_at, 'paid_at tidak terisi.');

        $this->assertTrue(
            $segar->statusHistory()->where('to_status', StatusPesanan::Paid->value)->exists(),
            'Perpindahan status tidak tercatat di riwayat.',
        );
    }

    public function test_penolakan_bukti_mengembalikan_pesanan_ke_belum_bayar(): void
    {
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $admin = User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-tolak@contoh.test',
            'password' => 'rahasia123',
            'role' => 'admin',
        ]);

        $pesanan = $this->pesanan();

        app(PembayaranManual::class)->unggahBukti($pesanan, $this->bukti());

        $payment = Payment::query()->where('order_id', $pesanan->id)->latest('id')->first();

        app(PembayaranManual::class)->tolak($payment, 'Nominal tidak sesuai', $admin->id);

        $this->assertSame(
            StatusPembayaran::Unpaid,
            $pesanan->fresh()->payment_status,
            'Bukti yang ditolak tidak boleh melunasi pesanan.',
        );

        $segar = $payment->fresh();

        // Penolakan dicatat di provider_status dan payload, bukan di kolom
        // status enum: status pembayaran tetap "pending" sebagai status
        // siklus, sedangkan keputusan admin ada di provider_status.
        $this->assertSame('ditolak', $segar->provider_status, 'Provider status tidak berubah menjadi ditolak.');
        $this->assertSame('Nominal tidak sesuai', (string) ($segar->payload_json['alasan_penolakan'] ?? ''));
        $this->assertNotNull($segar->verified_at, 'Waktu verifikasi tidak tercatat.');
    }
}
