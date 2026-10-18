<?php

namespace App\Jobs;

use App\Models\MediaUploadLog;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Gambar\PemeriksaGambar;
use App\Services\Gambar\PengecilGambar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Memproses satu berkas gambar yang diunggah untuk sebuah produk
 * (checklist F3-11).
 *
 * Dijalankan sebagai pekerjaan antrean, bukan di dalam permintaan HTTP. Alasannya
 * bukan kerapian: satu foto ponsel 5 MB perlu didekode, diputar sesuai orientasi
 * EXIF, lalu dikode ulang menjadi empat turunan WebP. Itu memakan ratusan
 * milidetik sampai beberapa detik dan ratusan MB memori. Bila dikerjakan di
 * dalam permintaan unggah, sepuluh berkas sekaligus akan menghabiskan batas
 * waktu PHP dan pengguna melihat galat 500 padahal berkasnya baik-baik saja.
 *
 * Batas sumber daya yang ditegakkan di sini, bukan hanya di konfigurasi:
 *   - memori pekerja dinaikkan ke 256 MB selama pemrosesan, lalu dikembalikan;
 *   - waktu jalan dibatasi 60 detik;
 *   - berkas asli disimpan di folder karantina dan baru dihapus setelah berhasil,
 *     sehingga kegagalan dapat dicoba ulang tanpa meminta pengguna mengunggah lagi.
 *
 * Setiap percobaan dicatat di media_upload_logs — termasuk penolakan, dengan
 * alasan yang spesifik dan durasi pemrosesan dalam milidetik (checklist F3-13).
 */
class ProsesGambarProduk implements ShouldQueue
{
    use Queueable;

    /**
     * Percobaan ulang terbatas. Kegagalan di sini hampir selalu bersifat
     * permanen (berkas rusak, GD kehabisan memori), bukan gangguan sesaat,
     * sehingga mengulanginya berkali-kali hanya membebani antrean.
     */
    public int $tries = 3;

    /** Jeda bertambah agar berkas sementara tidak diperebutkan. */
    public array $backoff = [5, 30, 120];

    public int $timeout;

    public function __construct(
        public readonly int $produkId,
        public readonly string $berkasKarantina,
        public readonly ?string $namaAsli = null,
        public readonly ?int $penggunaId = null,
        public readonly ?string $mimeDinyatakan = null,
        public readonly ?int $byteSize = null,
    ) {
        $this->timeout = (int) config('iksan.uploads.processing_timeout', 60);
    }

    public function handle(PemeriksaGambar $pemeriksa, PengecilGambar $pengecil): void
    {
        $mulai = microtime(true);

        $produk = Product::query()->find($this->produkId);

        if ($produk === null) {
            // Produk dihapus sebelum pekerjaan sempat berjalan. Karantina
            // dibersihkan supaya tidak menumpuk sampai penjadwal harian.
            $this->bersihkanKarantina();

            return;
        }

        if (! Storage::disk('local')->exists($this->berkasKarantina)) {
            $this->catat('rejected', 'Berkas karantina tidak ditemukan saat pemrosesan.', $mulai);

            return;
        }

        $jalurAbsolut = Storage::disk('local')->path($this->berkasKarantina);

        // Berkas karantina diperiksa ulang lewat pintu masuk jalur.
        //
        // Membungkusnya kembali sebagai UploadedFile tidak dapat dipakai:
        // isValid() memanggil is_uploaded_file(), yang selalu salah untuk berkas
        // dari disk, sehingga setiap gambar ditolak dengan alasan "unggahan gagal
        // diterima server". Berkas di disk adalah satu-satunya sumber kebenaran.
        //
        // Hash seluruh isi dipakai mendeteksi unggahan kembar (checklist F3-07),
        // dihitung dari isi, bukan nama berkas.
        $hash = hash_file('sha256', $jalurAbsolut) ?: null;

        if ($hash !== null && $this->duplikatUntuk($produk, $hash)) {
            $this->bersihkanKarantina();
            $this->catat(
                'deduplicated',
                'Gambar identik sudah ada pada produk ini; unggahan dilewati.',
                $mulai,
                $hash,
            );

            return;
        }

        $hasilPeriksa = $pemeriksa->periksaJalur(
            $jalurAbsolut,
            filesize($jalurAbsolut) ?: null,
            $produk->media()->count(),
            (int) $produk->media()->sum('byte_size'),
        );

        if ($hasilPeriksa['alasan'] !== '') {
            $this->bersihkanKarantina();
            $this->catat('rejected', $hasilPeriksa['alasan'], $mulai, $hasilPeriksa['hash']);

            return;
        }

        // Batas memori dan waktu dinaikkan hanya selama pemrosesan, lalu
        // dikembalikan seperti semula supaya pekerja lain di proses yang sama
        // tidak mewarisi batas besar.
        $memoriAwal = ini_get('memory_limit');
        @ini_set('memory_limit', config('iksan.uploads.processing_memory_mb', 256).'M');
        set_time_limit($this->timeout);

        try {
            $folder = 'products/'.$produk->ulid;
            $namaDasar = $produk->ulid.'-'.Str::lower(Str::random(8));

            $hasil = $pengecil->proses($jalurAbsolut, $folder, $namaDasar);

            $media = $produk->media()->create([
                'type' => 'image',
                'original_filename' => $this->namaAsli,
                'path_original' => null,
                'path_thumb' => $hasil['paths']['thumb'],
                'path_card' => $hasil['paths']['card'],
                'path_full' => $hasil['paths']['full'],
                'path_og' => $hasil['paths']['og'],
                'width' => $hasil['width'],
                'height' => $hasil['height'],
                'byte_size' => $hasil['byte_size'],
                'mime_detected' => $hasilPeriksa['mime'],
                // Sidik jari berkas ASLI, bukan hasil olahan. Dedup
                // membandingkan isi yang diunggah pengguna; memakai hash WebP
                // hasil pengodean ulang membuat dua unggahan identik tidak
                // pernah dikenali sebagai kembar.
                'content_hash' => $hash ?? $hasil['hash'],
                'alt_text_id' => pathinfo((string) $this->namaAsli, PATHINFO_FILENAME),
                'alt_text_en' => null,
                'is_primary' => ! $produk->media()->where('is_primary', true)->exists(),
                'display_order' => ((int) $produk->media()->max('display_order')) + 1,
            ]);

            $this->catat('processed', null, $mulai, $hasil['hash'], $media);
            $this->bersihkanKarantina();
        } catch (Throwable $e) {
            // Berkas karantina SENGAJA tidak dihapus di sini. Pekerjaan akan
            // dicoba ulang, dan percobaan ulang butuh berkasnya. Penghapusan
            // dilakukan oleh penjadwal setelah masa karantina habis, atau oleh
            // percobaan terakhir yang tetap gagal.
            $this->catat('failed', 'Pemrosesan gagal: '.$e->getMessage(), $mulai, $hash ?? null);

            throw $e;
        } finally {
            if (is_string($memoriAwal) && $memoriAwal !== '') {
                @ini_set('memory_limit', $memoriAwal);
            }
        }
    }

    /**
     * Dipanggil ketika percobaan terakhir gagal. Berkas karantina dibersihkan
     * karena tidak akan ada lagi yang mengolahnya, dan pengguna diberi tahu
     * supaya dapat mengunggah ulang.
     */
    public function failed(?Throwable $e): void
    {
        $this->bersihkanKarantina();

        $this->catat(
            'failed',
            'Gagal setelah '.$this->tries.' percobaan: '.($e?->getMessage() ?? 'sebab tidak diketahui'),
            null,
            null,
        );
    }

    /** Apakah produk ini sudah punya gambar dengan isi yang persis sama. */
    private function duplikatUntuk(Product $produk, string $hash): bool
    {
        return $produk->media()
            ->where('content_hash', $hash)
            ->exists();
    }

    private function bersihkanKarantina(): void
    {
        try {
            Storage::disk('local')->delete($this->berkasKarantina);
        } catch (Throwable) {
            // Berkas yang sudah tidak ada bukan masalah.
        }
    }

    private function catat(
        string $status,
        ?string $alasan,
        ?float $mulai,
        ?string $hash,
        ?ProductMedia $media = null,
    ): void {
        try {
            MediaUploadLog::create([
                'user_id' => $this->penggunaId ?? auth()->id(),
                'product_id' => $this->produkId,
                'original_filename' => $this->namaAsli,
                'byte_size' => $this->byteSize,
                'mime_declared' => $this->mimeDinyatakan,
                'mime_detected' => $media?->mime_detected,
                'content_hash' => $hash,
                'status' => $status,
                'rejection_reason' => $alasan,
                'product_media_id' => $media?->id,
                'processing_ms' => $mulai === null ? null : (int) round((microtime(true) - $mulai) * 1000),
            ]);
        } catch (Throwable) {
            // Pencatatan tidak boleh menggagalkan pemrosesan. Berkas yang sudah
            // bagus tidak perlu dibuang hanya karena log gagal ditulis.
        }
    }
}
