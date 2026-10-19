<?php

namespace App\Services\Pembayaran;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\StatusPesananService;
use App\Support\CatatanAktivitas;
use App\Support\KomponenPengaturan;
use App\Support\Pengaturan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Pembayaran transfer manual (checklist F9-05).
 *
 * Alurnya: pembeli mentransfer, mengunggah bukti, admin memeriksa, lalu
 * menandai lunas. Bukti transfer adalah gambar atau PDF yang menyebut nominal
 * dan nama pengirim — data yang cukup untuk menyalahgunakan rekening, jadi
 * berkasnya disimpan di disk privat dan hanya dapat diambil lewat rute
 * bertanda tangan.
 *
 * Batas unggahan tiga berkas per jam per pesanan. Tanpa batas itu, satu
 * pesanan dapat dipenuhi ratusan berkas, dan itu menghabiskan ruang disk
 * sekaligus membuat pemeriksaan manual mustahil.
 */
class PembayaranManual
{
    private const MAKS_PER_JAM = 3;

    /**
     * Menerima bukti transfer.
     *
     * @return array{bukti: string, pesan: string}
     */
    public function unggahBukti(Order $pesanan, UploadedFile $berkas, ?string $catatan = null): array
    {
        $kunci = 'bukti-bayar:'.$pesanan->id;

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PER_JAM)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'bukti' => sprintf(
                    'Sudah %d kali mengunggah bukti untuk pesanan ini. Coba lagi dalam %d menit.',
                    self::MAKS_PER_JAM,
                    (int) ceil($detik / 60),
                ),
            ]);
        }

        // Pesanan yang sudah lunas tidak menerima bukti lagi: kalau ada
        // pembayaran kedua, itu harus diselesaikan sebagai pengembalian dana,
        // bukan diam-diam menumpuk bukti baru.
        if ($pesanan->payment_status === StatusPembayaran::Paid) {
            throw ValidationException::withMessages([
                'bukti' => 'Pesanan ini sudah lunas. Hubungi kami bila terjadi pembayaran ganda.',
            ]);
        }

        if ($pesanan->status === StatusPesanan::Cancelled) {
            throw ValidationException::withMessages([
                'bukti' => 'Pesanan ini sudah dibatalkan dan tidak menerima pembayaran.',
            ]);
        }

        $this->periksa($berkas);

        $jalur = $this->simpan($pesanan, $berkas);

        DB::transaction(function () use ($pesanan, $jalur, $catatan): void {
            Payment::query()->create([
                'order_id' => $pesanan->id,
                'provider' => 'manual',
                'provider_status' => 'menunggu-verifikasi',
                'amount' => $pesanan->grand_total,
                'currency' => $pesanan->currency ?? 'IDR',
                'proof_path' => $jalur,
                'payload_json' => $catatan === null ? null : ['catatan_pembeli' => $catatan],
            ]);

            $pesanan->update(['payment_status' => StatusPembayaran::Pending]);

            CatatanAktivitas::catat('pembayaran.bukti-diunggah', [
                'order_number' => $pesanan->order_number,
            ], null, $pesanan);
        });

        RateLimiter::hit($kunci, 3600);

        return [
            'bukti' => $jalur,
            'pesan' => 'Bukti transfer diterima. Kami periksa dalam 1×24 jam kerja.',
        ];
    }

    /**
     * Memeriksa berkas bukti.
     *
     * Tipe dideteksi dari isi berkas, bukan dari ekstensi. Bukti transfer
     * adalah berkas paling menarik untuk disalahgunakan: berkas berkedok gambar
     * yang berisi kode, atau PDF yang sebenarnya bukan PDF.
     */
    private function periksa(UploadedFile $berkas): void
    {
        $jalur = $berkas->getRealPath() ?: $berkas->getPathname();

        if (! is_string($jalur) || ! is_file($jalur)) {
            throw ValidationException::withMessages(['bukti' => 'Berkas gagal diterima server.']);
        }

        $ukuranKb = (int) ceil(filesize($jalur) / 1024);
        $batasKb = (int) config('iksan.uploads.image_max_kb', 5120);

        if ($ukuranKb > $batasKb) {
            throw ValidationException::withMessages([
                'bukti' => sprintf('Ukuran berkas %d KB melebihi batas %d KB.', $ukuranKb, $batasKb),
            ]);
        }

        $isi = (string) @file_get_contents($jalur);

        if ($isi === '') {
            throw ValidationException::withMessages(['bukti' => 'Isi berkas tidak dapat dibaca.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) @$finfo->buffer($isi);

        $diizinkan = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (! in_array($mime, $diizinkan, true)) {
            throw ValidationException::withMessages([
                'bukti' => 'Bukti harus berupa gambar JPG, PNG, WebP, atau PDF. Tipe terdeteksi "'
                    .($mime === '' ? 'tidak dikenal' : $mime).'".',
            ]);
        }

        // PDF yang sah selalu dimulai dengan %PDF-. Berkas yang mengaku PDF
        // tetapi isinya lain ditolak di sini.
        if ($mime === 'application/pdf' && ! str_starts_with($isi, '%PDF-')) {
            throw ValidationException::withMessages(['bukti' => 'Berkas bukan PDF yang sah.']);
        }

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesizefromstring($isi);

            if ($info === false) {
                throw ValidationException::withMessages(['bukti' => 'Isi berkas bukan gambar yang sah.']);
            }

            if (min($info[0], $info[1]) < 100) {
                throw ValidationException::withMessages([
                    'bukti' => 'Gambar terlalu kecil untuk dibaca. Unggah tangkapan layar yang lebih jelas.',
                ]);
            }
        }
    }

    /**
     * Menyimpan bukti di disk privat.
     *
     * Bukan disk publik: bukti transfer memuat nama pengirim, nomor rekening,
     * dan nominal. Berkas seperti itu tidak boleh dapat diambil hanya dengan
     * menebak alamatnya.
     */
    private function simpan(Order $pesanan, UploadedFile $berkas): string
    {
        $ekstensi = match (true) {
            default => strtolower($berkas->getClientOriginalExtension()) ?: 'bin',
        };

        $nama = Str::ulid()->toString().'.'.preg_replace('/[^a-z0-9]/', '', $ekstensi);

        $jalur = $berkas->storeAs('bukti-bayar/'.$pesanan->ulid, $nama, 'local');

        if (! is_string($jalur)) {
            throw new RuntimeException('Bukti gagal disimpan.');
        }

        return $jalur;
    }

    /**
     * Memverifikasi pembayaran dan menandai pesanan lunas.
     *
     * Perpindahan status pesanan diserahkan ke StatusPesananService, bukan
     * menulis kolomnya langsung. Service itu yang mengurus stok: pesanan lunas
     * masih menahan stok, dan melewatinya akan membuat stok fisik berkurang
     * dua kali atau tidak sama sekali.
     */
    public function verifikasi(Payment $pembayaran, ?int $olehId = null): Order
    {
        $pesanan = $pembayaran->order()->firstOrFail();

        if ($pembayaran->terverifikasi()) {
            return $pesanan;
        }

        DB::transaction(function () use ($pembayaran, $pesanan, $olehId): void {
            $pembayaran->update([
                'provider_status' => 'terverifikasi',
                'verified_by' => $olehId ?? auth()->id(),
                'verified_at' => now(),
            ]);

            $pesanan->update(['payment_status' => StatusPembayaran::Paid]);

            if ($pesanan->status === StatusPesanan::Pending) {
                app(StatusPesananService::class)->pindah(
                    $pesanan,
                    StatusPesanan::Paid,
                    $olehId === null ? null : \App\Models\User::query()->find($olehId),
                    'Pembayaran manual diverifikasi',
                );
            }

            CatatanAktivitas::catat('pembayaran.diverifikasi', [
                'order_number' => $pesanan->order_number,
            ], $olehId, $pesanan);
        });

        $pesanan->refresh();

        // Dikirim setelah transaksi selesai, bukan di dalamnya.
        if (config('iksan.notifikasi.kirim_saat_dibayar', true)) {
            KirimNotifikasiPesanan::dispatch(
                $pesanan->id,
                KirimNotifikasiPesanan::KEJADIAN_DIBAYAR,
            );
        }

        return $pesanan;
    }

    /** Menolak bukti. Pesanan kembali belum bayar, pembeli dapat mengunggah ulang. */
    public function tolak(Payment $pembayaran, string $alasan, ?int $olehId = null): Order
    {
        $pesanan = $pembayaran->order()->firstOrFail();

        if ($pembayaran->terverifikasi()) {
            throw new RuntimeException('Pembayaran yang sudah terverifikasi tidak dapat ditolak.');
        }

        DB::transaction(function () use ($pembayaran, $pesanan, $alasan, $olehId): void {
            $pembayaran->update([
                'provider_status' => 'ditolak',
                'verified_by' => $olehId ?? auth()->id(),
                'verified_at' => now(),
                'payload_json' => array_merge((array) $pembayaran->payload_json, [
                    'alasan_penolakan' => $alasan,
                ]),
            ]);

            // Hanya dikembalikan ke belum bayar bila tidak ada bukti lain yang
            // masih menunggu. Kalau ada, pesanan tetap menunggu verifikasi.
            $masihMenunggu = Payment::query()
                ->where('order_id', $pesanan->id)
                ->where('id', '!=', $pembayaran->id)
                ->where('provider_status', 'menunggu-verifikasi')
                ->exists();

            if (! $masihMenunggu) {
                $pesanan->update(['payment_status' => StatusPembayaran::Unpaid]);
            }

            CatatanAktivitas::catat('pembayaran.ditolak', [
                'order_number' => $pesanan->order_number,
                'alasan' => $alasan,
            ], $olehId, $pesanan);
        });

        return $pesanan->refresh();
    }

    /**
     * Rekening tujuan transfer.
     *
     * Disimpan sebagai SATU pengaturan JSON terenkripsi bernama
     * `manual_bank_accounts`, berisi daftar rekening — bukan tiga kunci
     * terpisah. Encrypted karena nomor rekening memuat data yang tidak boleh
     * terbaca dari basis data saja.
     *
     * Daftar, bukan satu rekening: toko yang punya rekening di beberapa bank
     * perlu menampilkan semuanya, dan pembeli memilih yang paling mudah.
     *
     * @return array<int, array{bank: string, nomor: string, atas_nama: string}>
     */
    public function daftarRekening(): array
    {
        // Dibaca lewat model, bukan lewat kueri mentah.
        //
        // Kolom `value` memakai cast NilaiPengaturan yang mendekripsi sendiri
        // ketika `is_encrypted` benar. Membaca lewat kueri mentah menghasilkan
        // teks terenkripsi, dan json_decode atasnya selalu gagal — rekening
        // tampak kosong padahal isinya ada.
        try {
            $mentah = Setting::query()->where('key', 'manual_bank_accounts')->first()?->value;
        } catch (Throwable) {
            return [];
        }

        if (blank($mentah)) {
            return [];
        }

        $data = json_decode((string) $mentah, true);

        if (! is_array($data)) {
            return [];
        }

        $hasil = [];

        foreach ($data as $satu) {
            if (! is_array($satu)) {
                continue;
            }

            $bank = trim((string) ($satu['bank'] ?? ''));
            $nomor = trim((string) ($satu['number'] ?? $satu['nomor'] ?? ''));
            $nama = trim((string) ($satu['holder'] ?? $satu['atas_nama'] ?? ''));

            if ($bank === '' || $nomor === '') {
                continue;
            }

            $hasil[] = ['bank' => $bank, 'nomor' => $nomor, 'atas_nama' => $nama];
        }

        return $hasil;
    }

    /** Rekening pertama, untuk pesan yang hanya perlu menyebut satu. */
    public function rekeningTujuan(): array
    {
        return $this->daftarRekening()[0] ?? ['bank' => '', 'nomor' => '', 'atas_nama' => ''];
    }

    /** Apakah rekening sudah diisi. Tanpa ini, pembeli tidak tahu harus transfer ke mana. */
    public function rekeningLengkap(): bool
    {
        return $this->daftarRekening() !== [];
    }

    /** URL bertanda tangan untuk melihat bukti, berlaku 15 menit (checklist F10-09). */
    public function urlBukti(Payment $pembayaran): ?string
    {
        if (blank($pembayaran->proof_path)) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.payments.proof',
            now()->addMinutes(15),
            ['payment' => $pembayaran->ulid],
        );
    }

    public function hapusBukti(Payment $pembayaran): void
    {
        if (blank($pembayaran->proof_path)) {
            return;
        }

        try {
            Storage::disk('local')->delete($pembayaran->proof_path);
        } catch (Throwable) {
            // Berkas yang sudah tidak ada bukan masalah.
        }
    }
}
