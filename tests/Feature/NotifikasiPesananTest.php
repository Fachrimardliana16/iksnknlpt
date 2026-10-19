<?php

namespace Tests\Feature;

use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Pembayaran\PembayaranManual;
use App\Services\StatusPesananService;
use App\Enums\GrupPengaturan;
use App\Enums\StatusPesanan;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Tests\TestCase;

/**
 * Notifikasi pesanan: surel, WhatsApp, dan antreannya (checklist F9-06, F9-07,
 * F9-08).
 *
 * Tiga janji yang diuji, dan ketiganya mudah rusak tanpa ketahuan:
 *
 * 1. Kejadian yang berarti bagi pembeli benar-benar mengirim surel ke alamat
 *    pembeli itu — bukan ke alamat lain, dan bukan tidak sama sekali.
 * 2. WhatsApp memakai Fonnte dengan token dari pengaturan, nomor dinormalkan
 *    ke kode negara, dan pengiriman DILEWATI tanpa galat bila tokennya kosong
 *    (pemasangan baru belum tentu punya).
 * 3. Pengiriman terjadi di antrean, bukan di dalam permintaan: job masuk
 *    antrean saat status berpindah, tidak dijalankan langsung.
 *
 * Catatan cara memeriksa surel: job mengirim lewat Mail::raw(), yang bukan
 * mailable. MailFake tidak mencatatnya, jadi yang diperiksa adalah transport
 * "array" (lihat phpunit.xml) — di situlah pesan yang benar-benar terkirim
 * tersimpan.
 */
class NotifikasiPesananTest extends TestCase
{
    use RefreshDatabase;

    private function pesanan(): Order
    {
        return Order::query()->create([
            'order_number' => 'IK-20260101-NOTIF1',
            'status' => StatusPesanan::Pending,
            'payment_status' => 'unpaid',
            'payment_method' => 'manual',
            'guest_email' => 'pembeli@contoh.test',
            'guest_phone' => '081234567890',
            'subtotal' => 250000,
            'grand_total' => 250000,
            'shipping_address_snapshot' => ['recipient_name' => 'Pembeli Uji', 'city' => 'Sleman'],
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Symfony\Component\Mime\RawMessage>
     */
    private function surelTerkirim(): \Illuminate\Support\Collection
    {
        // Transport "array" menyimpan pesan sebagai Collection of RawMessage,
        // bukan array of Email: isi pesannya ada di getMessage().
        return app('mailer')->getSymfonyTransport()->messages();
    }

    private function setel(string $kunci, string $nilai): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $kunci],
            ['value' => $nilai, 'group' => GrupPengaturan::Kontak],
        );

        Pengaturan::lupakan();
    }

    /** Bukti transfer yang sah: PNG 400x300 dibuat sungguhan. */
    private function bukti(): UploadedFile
    {
        $im = imagecreatetruecolor(400, 300);
        imagefilledrectangle($im, 0, 0, 399, 299, imagecolorallocate($im, 30, 30, 140));
        ob_start();
        imagepng($im);
        $biner = (string) ob_get_clean();
        imagedestroy($im);

        return UploadedFile::fake()->createWithContent('bukti.png', $biner);
    }

    // ── F9-06: surel ────────────────────────────────────────────────

    public function test_tiga_kejadian_mengirim_surel_ke_alamat_pembeli(): void
    {
        $pesanan = $this->pesanan();

        foreach ([
            KirimNotifikasiPesanan::KEJADIAN_DIBUAT,
            KirimNotifikasiPesanan::KEJADIAN_DIBAYAR,
            KirimNotifikasiPesanan::KEJADIAN_DIKIRIM,
        ] as $kejadian) {
            (new KirimNotifikasiPesanan($pesanan->id, $kejadian))->handle();
        }

        $terkirim = $this->surelTerkirim();

        $this->assertCount(3, $terkirim, 'Tiga kejadian harus mengirim tiga surel.');

        foreach ($terkirim as $mentah) {
            // RawMessage hanya menyimpan string MIME utuh; tujuannya dibaca
            // dari baris header "To:" di dalamnya.
            $isi = $mentah->toString();

            $this->assertMatchesRegularExpression(
                '/^To: .*pembeli@contoh\.test/mi',
                $isi,
                'Surel tidak ditujukan ke alamat pembeli.',
            );
        }
    }

    public function test_pesanan_tanpa_surel_dan_telepon_tidak_menggagalkan_job(): void
    {
        Http::fake();

        $pesanan = $this->pesanan();
        $pesanan->update(['guest_email' => null, 'guest_phone' => null]);

        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBUAT))->handle();

        $this->assertCount(0, $this->surelTerkirim());
        Http::assertNothingSent();
    }

    // ── F9-07: WhatsApp ─────────────────────────────────────────────

    public function test_whatsapp_dikirim_lewat_fonnte_dengan_token_pengaturan(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true], 200)]);

        $this->setel('fonnte_token', 'token-uji-123');

        $pesanan = $this->pesanan();

        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIKIRIM))->handle();

        Http::assertSent(function (Request $r): bool {
            return $r->url() === 'https://api.fonnte.com/send'
                && $r->hasHeader('Authorization', 'token-uji-123')
                && ($r['target'] ?? '') === '6281234567890';
        });
    }

    public function test_whatsapp_dilewati_tanpa_token_tanpa_galat(): void
    {
        Http::fake();

        $this->setel('fonnte_token', '');

        $pesanan = $this->pesanan();

        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBUAT))->handle();

        Http::assertNothingSent();
        $this->assertCount(1, $this->surelTerkirim(), 'Surel tetap dikirim walau WhatsApp dilewati.');
    }

    public function test_penyedia_whatsapp_menolak_tidak_menggagalkan_surel(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false], 500)]);

        $this->setel('fonnte_token', 'token-uji-123');

        $pesanan = $this->pesanan();

        // Tidak boleh melempar: kegagalan WhatsApp tidak boleh membatalkan
        // surel yang sudah terkirim, dan tidak boleh membuat job diulang
        // selamanya.
        (new KirimNotifikasiPesanan($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBUAT))->handle();

        $this->assertCount(1, $this->surelTerkirim());
    }

    // ── F9-08: antrean ──────────────────────────────────────────────

    public function test_job_memang_antrean_bukan_sinkron(): void
    {
        $this->assertTrue(
            (new \ReflectionClass(KirimNotifikasiPesanan::class))
                ->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class),
            'KirimNotifikasiPesanan tidak mengimplementasikan ShouldQueue.',
        );
    }

    public function test_status_dikirim_mengantrekan_notifikasi_dikirim(): void
    {
        Queue::fake();

        $pesanan = $this->pesanan();

        app(StatusPesananService::class)->pindah($pesanan, StatusPesanan::Paid, null, 'Lunas untuk uji');

        // Kejadian "dibayar" diantrekan oleh jalur pembayaran, bukan oleh
        // perpindahan status; yang diuji di sini adalah "dikirim".
        Queue::assertPushed(KirimNotifikasiPesanan::class, 0);

        // Alur sah: pending → paid → processing → shipped. Melompat dari paid
        // ke shipped ditolak mesin status, dan test ini tidak boleh
        // menyalahkan aturan itu sebagai kegagalan notifikasi.
        app(StatusPesananService::class)->pindah(
            $pesanan->fresh(),
            StatusPesanan::Processing,
            null,
            'Diproses untuk uji',
        );

        app(StatusPesananService::class)->pindah(
            $pesanan->fresh(),
            StatusPesanan::Shipped,
            null,
            'Dikirim untuk uji',
        );

        Queue::assertPushed(KirimNotifikasiPesanan::class, 1);
        Queue::assertPushed(
            KirimNotifikasiPesanan::class,
            static fn ($job): bool => $job->kejadian === KirimNotifikasiPesanan::KEJADIAN_DIKIRIM,
        );
    }

    public function test_verifikasi_pembayaran_mengantrekan_notifikasi_dibayar(): void
    {
        Queue::fake();
        Storage::fake('private');
        RateLimiter::clear('bukti-bayar:1');

        $pesanan = $this->pesanan();
        $layanan = app(PembayaranManual::class);

        $layanan->unggahBukti($pesanan, $this->bukti());

        $payment = Payment::query()->where('order_id', $pesanan->id)->latest('id')->first();

        $layanan->verifikasi($payment, null);

        Queue::assertPushed(
            KirimNotifikasiPesanan::class,
            static fn ($job): bool => $job->kejadian === KirimNotifikasiPesanan::KEJADIAN_DIBAYAR,
        );
    }

    public function test_pembatalan_tidak_mengantrekan_notifikasi(): void
    {
        Queue::fake();

        $pesanan = $this->pesanan();

        app(StatusPesananService::class)->pindah($pesanan, StatusPesanan::Cancelled, null, 'Uji batal');

        // Pembatalan bukan kejadian yang dikabarkan ke pembeli lewat job ini;
        // menambahkannya berarti pembeli menerima pesan yang tidak diminta.
        Queue::assertPushed(KirimNotifikasiPesanan::class, 0);
    }
}
