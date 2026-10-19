<?php

namespace App\Jobs;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\Pembayaran\PembayaranManual;
use App\Support\Pengaturan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifikasi pesanan lewat surel dan WhatsApp (checklist F9-06, F9-07, F9-08).
 *
 * Dijalankan sebagai pekerjaan antrean supaya pengiriman yang lambat — Surel
 * yang menunggu sambungan SMTP, atau API WhatsApp yang menjawab dalam hitungan
 * detik — tidak menahan permintaan pengguna. Tanpa antrean, pembeli yang baru
 * menyelesaikan checkout akan menunggu kotak surelnya sendiri terkirim.
 *
 * Kegagalan pengiriman TIDAK boleh menggagalkan pesanan. Pesanan sudah
 * tersimpan dan stok sudah ditahan; notifikasi yang gagal terkirim hanya
 * berarti pembeli tidak menerima kabar, dan itu dicatat lalu dicoba ulang.
 */
class KirimNotifikasiPesanan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    /**
     * Kejadian yang memicu notifikasi.
     *
     * Dibatasi pada tiga yang disebut checklist: pesanan dibuat, pembayaran
     * diterima, barang dikirim. Menambahkan yang lain berarti pembeli menerima
     * lebih banyak pesan daripada perubahan yang berarti baginya.
     */
    public const KEJADIAN_DIBUAT = 'dibuat';
    public const KEJADIAN_DIBAYAR = 'dibayar';
    public const KEJADIAN_DIKIRIM = 'dikirim';

    public function __construct(
        public readonly int $pesananId,
        public readonly string $kejadian,
    ) {}

    public function handle(): void
    {
        $pesanan = Order::query()->with(['items', 'user'])->find($this->pesananId);

        if ($pesanan === null) {
            return;
        }

        $surel = $this->surelPembeli($pesanan);
        $telepon = $this->teleponPembeli($pesanan);

        $surel === null || $this->kirimSurel($pesanan, $surel);
        $telepon === null || $this->kirimWhatsapp($pesanan, $telepon);
    }

    private function kirimSurel(Order $pesanan, string $tujuan): void
    {
        $toko = Pengaturan::instance();
        $judul = $this->judulSurel($toko->teks('site_name', 'Iksan Knalpot'));

        $isi = $this->isiPesan($pesanan, untukSurel: true);

        try {
            Mail::raw($isi, function ($m) use ($tujuan, $judul, $toko): void {
                $m->to($tujuan)->subject($judul);
                $m->from(
                    (string) $toko->teks('contact_email', config('mail.from.address')),
                    (string) $toko->teks('site_name', config('mail.from.name')),
                );
            });

            Log::info('Notifikasi pesanan terkirim lewat surel', [
                'pesanan' => $pesanan->order_number,
                'kejadian' => $this->kejadian,
            ]);
        } catch (Throwable $e) {
            // Dicatat, bukan dilempar: kegagalan surel tidak boleh menggagalkan
            // pengiriman WhatsApp yang mungkin berhasil.
            Log::warning('Notifikasi surel gagal', [
                'pesanan' => $pesanan->order_number,
                'sebab' => $e->getMessage(),
            ]);
        }
    }

    /**
     * WhatsApp lewat Fonnte.
     *
     * Kunci API dibaca dari pengaturan toko, bukan dari berkas .env, supaya
     * dapat diganti tanpa menyalakan ulang aplikasi. Bila kuncinya kosong,
     * pengiriman dilewati tanpa galat — pada pemasangan baru, WhatsApp memang
     * belum tentu dikonfigurasi.
     */
    private function kirimWhatsapp(Order $pesanan, string $tujuan): void
    {
        $token = (string) \App\Support\KomponenPengaturan::mentah('fonnte_token');

        if (blank($token)) {
            return;
        }

        try {
            $respons = Http::withHeaders(['Authorization' => $token])
                ->timeout(15)
                ->asForm()
                ->post('https://api.fonnte.com/send', [
                    'target' => $this->normalkanTelepon($tujuan),
                    'message' => $this->isiPesan($pesanan, untukSurel: false),
                    'countryCode' => '62',
                ]);

            if (! $respons->successful() || $respons->json('status') === false) {
                Log::warning('Notifikasi WhatsApp ditolak penyedia', [
                    'pesanan' => $pesanan->order_number,
                    'status' => $respons->status(),
                    'balasan' => mb_substr((string) $respons->body(), 0, 200),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp gagal', [
                'pesanan' => $pesanan->order_number,
                'sebab' => $e->getMessage(),
            ]);
        }
    }

    private function judulSurel(string $namaToko): string
    {
        return match ($this->kejadian) {
            self::KEJADIAN_DIBUAT => 'Pesanan diterima · '.$namaToko,
            self::KEJADIAN_DIBAYAR => 'Pembayaran diterima · '.$namaToko,
            self::KEJADIAN_DIKIRIM => 'Pesanan dikirim · '.$namaToko,
            default => 'Kabar pesanan · '.$namaToko,
        };
    }

    /**
     * Isi pesan disusun dari data pesanan, bukan dari masukan pengguna.
     *
     * Untuk surel, isinya lebih panjang karena memuat rincian barang dan cara
     * melacak. Untuk WhatsApp, dipangkas: pesan panjang di aplikasi obrolan
     * jarang dibaca sampai habis.
     */
    private function isiPesan(Order $pesanan, bool $untukSurel): string
    {
        $toko = Pengaturan::instance();
        $namaToko = (string) $toko->teks('site_name', 'Iksan Knalpot');
        $nama = $this->namaPembeli($pesanan);

        $awal = match ($this->kejadian) {
            self::KEJADIAN_DIBUAT => "Halo {$nama}, pesanan Anda sudah kami terima.",
            self::KEJADIAN_DIBAYAR => "Halo {$nama}, pembayaran pesanan Anda sudah kami terima dan pesanan akan segera diproses.",
            self::KEJADIAN_DIKIRIM => "Halo {$nama}, pesanan Anda sudah dikirim.",
            default => "Halo {$nama}, ada kabar untuk pesanan Anda.",
        };

        $baris = [
            $awal,
            '',
            'Nomor pesanan: '.$pesanan->order_number,
            'Status: '.$pesanan->status->label(),
            'Total: '.rupiah($pesanan->grand_total),
        ];

        if ($untukSurel && $pesanan->items->isNotEmpty()) {
            $baris[] = '';
            $baris[] = 'Rincian:';

            foreach ($pesanan->items as $item) {
                $baris[] = sprintf(
                    '- %s × %d = %s',
                    $item->product_name,
                    $item->quantity,
                    rupiah($item->subtotal),
                );
            }
        }

        // Instruksi pembayaran hanya untuk pesanan yang belum dibayar, dan
        // hanya bila rekeningnya memang sudah diisi. Menyebut rekening kosong
        // membuat pembeli bingung.
        if ($this->kejadian === self::KEJADIAN_DIBUAT && $pesanan->payment_method === \App\Enums\MetodePembayaran::Manual) {
            $manual = app(PembayaranManual::class);

            if ($manual->rekeningLengkap()) {
                $r = $manual->rekeningTujuan();

                $baris[] = '';
                $baris[] = 'Transfer ke:';
                $baris[] = $r['bank'].' '.$r['nomor'];
                $baris[] = 'a.n. '.$r['atas_nama'];
                $baris[] = 'Setelah transfer, unggah bukti lewat halaman pesanan Anda.';
            }
        }

        if ($this->kejadian === self::KEJADIAN_DIKIRIM) {
            $baris[] = '';
            $baris[] = 'Lacak pesanan: '.route('front.order.track', ['kode' => $pesanan->order_number]);
        }

        $baris[] = '';
        $baris[] = $namaToko;

        return implode("\n", $baris);
    }

    /**
     * Nama pembeli. Pesanan tamu tidak punya akun, jadi namanya diambil dari
     * snapshot alamat pengiriman — satu-satunya tempat nama itu tersimpan.
     */
    private function namaPembeli(Order $pesanan): string
    {
        $snapshot = (array) $pesanan->shipping_address_snapshot;
        $nama = $snapshot['recipient_name'] ?? $snapshot['name'] ?? null;

        return filled($nama) ? (string) $nama : ($pesanan->user?->name ?? 'Pelanggan');
    }

    private function surelPembeli(Order $pesanan): ?string
    {
        $surel = $pesanan->guest_email ?: $pesanan->user?->email;

        return filled($surel) ? (string) $surel : null;
    }

    private function teleponPembeli(Order $pesanan): ?string
    {
        $telepon = $pesanan->guest_phone ?: $pesanan->user?->phone_number;

        return filled($telepon) ? (string) $telepon : null;
    }

    /** Fonnte meminta nomor tanpa tanda baca, diawali kode negara. */
    private function normalkanTelepon(string $nomor): string
    {
        $bersih = preg_replace('/[^0-9]/', '', $nomor) ?: '';

        if (str_starts_with($bersih, '0')) {
            return '62'.substr($bersih, 1);
        }

        if (str_starts_with($bersih, '8')) {
            return '62'.$bersih;
        }

        return $bersih;
    }

    /** Dipanggil bila pekerjaan gagal setelah semua percobaan. */
    public function failed(?Throwable $e): void
    {
        Log::error('Notifikasi pesanan gagal total', [
            'pesanan_id' => $this->pesananId,
            'kejadian' => $this->kejadian,
            'sebab' => $e?->getMessage(),
        ]);
    }
}
