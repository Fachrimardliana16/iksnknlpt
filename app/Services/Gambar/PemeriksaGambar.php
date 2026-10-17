<?php

namespace App\Services\Gambar;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Pemeriksaan berkas masuk (PRD Bagian 14.1).
 *
 * Setiap pemeriksaan mengembalikan alasan penolakan yang spesifik, bukan pesan
 * umum. Alasan itu disimpan di media_upload_logs, sehingga kegagalan unggahan
 * dapat ditelusuri tanpa menebak.
 */
class PemeriksaGambar
{
    /**
     * @return array{alasan: string, mime: string|null, hash: string|null}
     */
    public function periksa(UploadedFile $berkas, int $jumlahBerkasProduk = 0, int $totalByteProduk = 0): array
    {
        $konfig = config('iksan.uploads');

        if (! $berkas->isValid()) {
            return $this->tolak('Unggahan gagal diterima server.', null, null);
        }

        $ukuranKb = (int) ceil($berkas->getSize() / 1024);

        if ($ukuranKb > $konfig['image_max_kb']) {
            return $this->tolak(
                sprintf('Ukuran %d KB melebihi batas %d KB.', $ukuranKb, $konfig['image_max_kb']),
                null,
                null,
            );
        }

        $jalur = $berkas->getRealPath();

        // Isi dibaca lewat jalur, fopen, atau getContent(). getRealPath() boleh
        // false, dan getContent() boleh melempar FileException ketika berkas
        // sementara sudah tidak ada. Pembacaan gagal bukan alasan untuk membalas
        // 500 — unggahan seperti itu ditolak dengan alasan yang jelas.
        //
        // Catatan Windows: berkas sementara yang isinya cocok dengan signature
        // webshell dapat dikunci Windows Defender, sehingga fopen dan
        // file_get_contents gagal meskipun berkas ada dan is_readable() true.
        // Di mesin lain berkas yang sama terbaca normal, jadi jangan
        // mengandalkan pembacaan berhasil; cukup tolak bila gagal.
        $isi = '';

        if (is_string($jalur) && is_readable($jalur)) {
            $handle = @fopen($jalur, 'rb');

            if (is_resource($handle)) {
                $isi = (string) stream_get_contents($handle);
                fclose($handle);
            }

            if ($isi === '') {
                try {
                    $isi = (string) $berkas->getContent();
                } catch (\Throwable) {
                    $isi = '';
                }
            }
        }

        if ($jalur === false && $isi === '') {
            return $this->tolak('Berkas sementara tidak dapat dibaca.', null, null);
        }

        // Tipe dideteksi dari ISI berkas, bukan dari ekstensi maupun header yang
        // dikirim peramban. Berkas .php yang dinamai .jpg tertangkap di sini.
        //
        // Urutannya buffer() lebih dulu, baru file() sebagai cadangan. Di PHP 8.4
        // Windows, finfo->file() mengembalikan false untuk berkas yang berisi tag
        // PHP, padahal finfo->buffer() dengan isi identik melaporkannya sebagai
        // text/x-php. Memakai file() lebih dulu membuat berkas berbahaya ditolak
        // dengan alasan "tidak dikenal" — hasilnya sama-sama ditolak, tetapi
        // alasannya menyesatkan dan bergantung pada perilaku libmagic yang bisa
        // berubah. Peringatan finfo ditahan dengan @ karena Laravel mengubahnya
        // menjadi ErrorException sebelum fungsinya sempat mengembalikan nilai.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $isi !== '' ? (string) @$finfo->buffer($isi) : '';

        if ($mime === '' && is_string($jalur) && is_readable($jalur)) {
            $mime = (string) @$finfo->file($jalur);
        }

        if ($mime === '' && $isi === '') {
            return $this->tolak('Isi berkas tidak dapat dibaca untuk diperiksa.', null, null);
        }

        if (! in_array($mime, $konfig['allowed_mimes'], true)) {
            return $this->tolak(
                sprintf('Tipe berkas terdeteksi "%s" dan tidak diizinkan.', $mime === '' ? 'tidak dikenal' : $mime),
                $mime,
                null,
            );
        }

        // Dimensi dibaca dari isi, bukan jalur, supaya tetap bekerja ketika
        // berkas sementara tidak dapat dibuka ulang lewat nama.
        $info = $isi !== '' ? @getimagesizefromstring($isi) : @getimagesize($jalur);

        if ($info === false) {
            return $this->tolak('Isi berkas bukan gambar yang sah meskipun tipe MIME-nya diizinkan.', $mime, null);
        }

        [$lebar, $tinggi] = $info;

        if (max($lebar, $tinggi) > $konfig['max_dimension']) {
            return $this->tolak(
                sprintf('Dimensi %dx%d melebihi batas %d px.', $lebar, $tinggi, $konfig['max_dimension']),
                $mime,
                null,
            );
        }

        if (min($lebar, $tinggi) < $konfig['min_dimension']) {
            return $this->tolak(
                sprintf('Dimensi %dx%d di bawah minimum %d px.', $lebar, $tinggi, $konfig['min_dimension']),
                $mime,
                null,
            );
        }

        $rasio = max($lebar, $tinggi) / max(1, min($lebar, $tinggi));

        if ($rasio > $konfig['max_aspect_ratio']) {
            return $this->tolak(
                sprintf('Rasio sisi %.1f:1 terlalu ekstrem (batas %d:1).', $rasio, $konfig['max_aspect_ratio']),
                $mime,
                null,
            );
        }

        if ($jumlahBerkasProduk >= $konfig['max_files_per_product']) {
            return $this->tolak(
                sprintf('Produk ini sudah memiliki %d berkas (batas %d).', $jumlahBerkasProduk, $konfig['max_files_per_product']),
                $mime,
                null,
            );
        }

        $totalMb = ($totalByteProduk + $berkas->getSize()) / 1024 / 1024;

        if ($totalMb > $konfig['max_total_mb_per_product']) {
            return $this->tolak(
                sprintf('Total media produk akan mencapai %.1f MB (batas %d MB).', $totalMb, $konfig['max_total_mb_per_product']),
                $mime,
                null,
            );
        }

        $hash = ($isi !== '' ? hash('sha256', $isi) : hash_file('sha256', (string) $jalur)) ?: null;

        return ['alasan' => '', 'mime' => $mime, 'hash' => $hash];
    }

    /**
     * Berkas identik yang sudah ada untuk produk yang sama tidak disimpan
     * dua kali. Isi yang sama tidak menambah nilai, hanya menghabiskan ruang.
     */
    public function duplikat(?string $hash, \Illuminate\Support\Collection $hashTersimpan): bool
    {
        return $hash !== null && $hashTersimpan->contains($hash);
    }

    public function pastikanBukanDuplikat(?string $hash, \Illuminate\Support\Collection $hashTersimpan): void
    {
        if ($this->duplikat($hash, $hashTersimpan)) {
            throw new RuntimeException('Berkas ini sudah pernah diunggah untuk produk yang sama.');
        }
    }

    /** @return array{alasan: string, mime: string|null, hash: string|null} */
    private function tolak(string $alasan, ?string $mime, ?string $hash): array
    {
        return ['alasan' => $alasan, 'mime' => $mime, 'hash' => $hash];
    }
}
