<?php

namespace App\Services\Produk;

use App\Jobs\ProsesGambarProduk;
use App\Models\MediaUploadLog;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\Gambar\PemeriksaGambar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Menerima unggahan gambar produk, menaruhnya di karantina, lalu menyerahkan
 * pemrosesan beratnya ke antrean (checklist F3-11, F3-12).
 *
 * Dua tahap pemeriksaan, dan keduanya diperlukan:
 *
 *   1. Di sini, secara sinkron, supaya pengguna langsung tahu berkasnya ditolak
 *      beserta alasannya — bukan menunggu beberapa detik lalu pesannya muncul
 *      entah dari mana.
 *   2. Di dalam pekerjaan antrean, karena antara unggahan dan pemrosesan ada
 *      jeda, dan berkas di disk adalah satu-satunya sumber kebenaran.
 *
 * Pemeriksaan ganda bukan pemborosan: yang pertama melayani kenyamanan, yang
 * kedua melayani keamanan. Titik akhir unggah dapat dikirim langsung tanpa
 * melewati formulir, jadi pemeriksaan pertama tidak boleh dianggap penjaga.
 */
class PengunggahMediaProduk
{
    public function __construct(private readonly PemeriksaGambar $pemeriksa) {}

    /**
     * Batas PHP yang lebih kecil dari batas aplikasi membuat berkas ditolak
     * sebelum kode ini sempat memeriksanya.
     *
     * Gejalanya menyesatkan: pengguna hanya melihat "The berkasBaru.0 failed to
     * upload." tanpa sebab, sementara formulir menyatakan batasnya 5 MB. Bila
     * yang menolak adalah PHP, tidak ada jumlah percobaan ulang yang menolong.
     * Pesan di bawah menyebutkan angka sebenarnya supaya dapat ditindaklanjuti.
     */
    private function pastikanBatasPhpCukup(): void
    {
        $batasAplikasiKb = (int) config('iksan.uploads.image_max_kb');

        foreach ([
            'upload_max_filesize' => fn (string $v) => $this->keKb($v),
            'post_max_size' => fn (string $v) => $this->keKb($v),
        ] as $nama => $hitung) {
            $nilai = (string) ini_get($nama);

            if ($nilai === '') {
                continue;
            }

            $kb = $hitung($nilai);

            if ($kb > 0 && $kb < $batasAplikasiKb) {
                throw ValidationException::withMessages([
                    'berkasBaru' => sprintf(
                        'Server menolak berkas lebih besar dari %s (%s di php.ini), sedangkan formulir ini mengizinkan %s. Naikkan %s atau unggah berkas yang lebih kecil.',
                        $this->tampilkanKb($kb),
                        $nama,
                        $this->tampilkanKb($batasAplikasiKb),
                        $nama,
                    ),
                ]);
            }
        }
    }

    /** Mengubah nilai singkatan php.ini seperti "2M" atau "512K" menjadi KB. */
    private function keKb(string $nilai): int
    {
        $nilai = trim($nilai);

        if ($nilai === '') {
            return 0;
        }

        $satuan = strtolower(substr($nilai, -1));
        $angka = (int) $nilai;

        return match ($satuan) {
            'g' => $angka * 1024 * 1024,
            'm' => $angka * 1024,
            'k' => $angka,
            default => (int) round($angka / 1024),
        };
    }

    private function tampilkanKb(int $kb): string
    {
        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1, ',', '.'), '0'), ',').' MB'
            : $kb.' KB';
    }

    /**
     * @param  array<int, UploadedFile>  $berkas
     * @return array{antre: int, ditolak: array<int, string>}
     */
    public function unggahBanyak(Product $produk, array $berkas, ?int $penggunaId = null): array
    {
        $this->pastikanBatasPhpCukup();

        $antre = 0;
        $ditolak = [];

        // Kuota dihitung sekali di awal, lalu bertambah secara lokal. Membaca
        // ulang dari basis data setiap kali berarti unggahan ke-21 dalam satu
        // gelombang tidak melihat 20 berkas sebelumnya — kuota jadi bocor.
        $jumlah = $produk->media()->count();
        $totalByte = (int) $produk->media()->sum('byte_size');

        foreach ($berkas as $satu) {
            $namaAsli = $satu->getClientOriginalName();

            // Salinan untuk penilaian kuota; pemeriksaan memakai angka sebelum
            // berkas ini diterima, bukan sesudahnya.
            $hasil = $this->pemeriksa->periksa($satu, $jumlah, $totalByte);

            if ($hasil['alasan'] !== '') {
                $ditolak[] = $namaAsli.' — '.$hasil['alasan'];
                $this->catatPenolakan($satu, $hasil, $penggunaId, $namaAsli, $produk->id);

                continue;
            }

            try {
                $jalurKarantina = $this->taruhDiKarantina($satu);
            } catch (Throwable $e) {
                $ditolak[] = $namaAsli.' — gagal menyimpan berkas sementara: '.$e->getMessage();

                continue;
            }

            ProsesGambarProduk::dispatch(
                produkId: $produk->id,
                berkasKarantina: $jalurKarantina,
                namaAsli: $namaAsli,
                penggunaId: $penggunaId,
                mimeDinyatakan: $satu->getClientMimeType(),
                byteSize: $satu->getSize() ?: null,
            );

            $antre++;
            $jumlah++;
            $totalByte += (int) $satu->getSize();
        }

        return ['antre' => $antre, 'ditolak' => $ditolak];
    }

    /**
     * Berkas asli dipindahkan ke folder karantina di disk privat.
     *
     * Sengaja TIDAK langsung ke disk publik: berkas belum diperiksa isinya
     * secara menyeluruh, dan menaruh berkas yang belum tepercaya di direktori
     * yang dapat diakses publik berarti memberi jalan bagi berkas berbahaya
     * untuk dijangkau lewat URL sebelum ada yang memeriksanya.
     */
    private function taruhDiKarantina(UploadedFile $berkas): string
    {
        $ekstensi = Str::lower($berkas->getClientOriginalExtension()) ?: 'bin';
        $nama = Str::ulid()->toString().'.'.preg_replace('/[^a-z0-9]/', '', $ekstensi);

        $jalur = $berkas->storeAs('uploads/quarantine', $nama, 'local');

        if (! is_string($jalur)) {
            throw new \RuntimeException('Penulisan berkas karantina gagal.');
        }

        return $jalur;
    }

    /** @param array{alasan: string, mime: string|null, hash: string|null} $hasil */
    private function catatPenolakan(UploadedFile $berkas, array $hasil, ?int $penggunaId, string $namaAsli, int $produkId): void
    {
        try {
            MediaUploadLog::create([
                'user_id' => $penggunaId,
                'product_id' => $produkId,
                'original_filename' => $namaAsli,
                'byte_size' => $berkas->getSize() ?: null,
                'mime_declared' => $berkas->getClientMimeType(),
                'mime_detected' => $hasil['mime'],
                'content_hash' => $hasil['hash'],
                'status' => 'rejected',
                'rejection_reason' => $hasil['alasan'],
                'processing_ms' => 0,
            ]);
        } catch (Throwable) {
            // Log yang gagal ditulis tidak boleh membatalkan penolakan.
        }
    }

    /**
     * Periksa apakah produk ini masih boleh menerima berkas, dan lempar
     * ValidationException bila tidak.
     *
     * Dipakai oleh komponen formulir sebelum memanggil unggahBanyak, supaya
     * pesannya tampil di kolom media, bukan sebagai galat umum.
     */
    public function pastikanKuotaTersedia(Product $produk, int $jumlahBaru): void
    {
        $konfig = config('iksan.uploads');

        $jumlah = $produk->media()->count();

        if ($jumlah + $jumlahBaru > $konfig['max_files_per_product']) {
            throw ValidationException::withMessages([
                'berkasBaru' => sprintf(
                    'Menambah %d berkas akan melewati batas %d berkas per produk (sekarang %d).',
                    $jumlahBaru,
                    $konfig['max_files_per_product'],
                    $jumlah,
                ),
            ]);
        }
    }

    /**
     * Hapus berkas gambar produk beserta keempat turunannya.
     *
     * Turunan tidak disimpan di basis data sebagai daftar, melainkan disusun
     * dari nama dasar yang sama dengan akhiran tetap. Menghapus baris tanpa
     * menghapus berkas akan meninggalkan sampah di disk yang tidak lagi
     * terjangkau apa pun.
     */
    public function hapusBerkas(ProductMedia|array $media): int
    {
        $daftar = is_array($media) ? $media : [$media];
        $terhapus = 0;

        foreach ($daftar as $satu) {
            $jalur = array_filter([
                $satu->path_original,
                $satu->path_thumb,
                $satu->path_card,
                $satu->path_full,
                $satu->path_og,
            ]);

            foreach ($jalur as $satuJalur) {
                try {
                    if (Storage::disk('public')->exists($satuJalur)) {
                        Storage::disk('public')->delete($satuJalur);
                        $terhapus++;
                    }
                } catch (Throwable) {
                    // Berkas yang gagal dihapus tidak boleh menggagalkan
                    // penghapusan barisnya — baris yatim lebih membingungkan
                    // daripada berkas yatim.
                }
            }
        }

        return $terhapus;
    }

    /** Folder kerja satu produk, dipakai saat seluruh produk dihapus. */
    public function hapusFolderProduk(Product $produk): void
    {
        try {
            Storage::disk('public')->deleteDirectory('products/'.$produk->ulid);
        } catch (Throwable) {
            // Bukan kegagalan yang perlu dilaporkan ke pengguna.
        }
    }
}
