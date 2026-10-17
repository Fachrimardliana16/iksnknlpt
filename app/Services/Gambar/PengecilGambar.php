<?php

namespace App\Services\Gambar;

use App\Support\GambarContoh;
use RuntimeException;

/**
 * Pemrosesan gambar unggahan (PRD Bagian 14.2).
 *
 * Seluruh jalur memakai GD, karena ekstensi Imagick tidak tersedia di lingkungan
 * ini. Berkas didekode ulang, bukan disalin, sehingga metadata EXIF termasuk
 * koordinat GPS tidak pernah ikut tersimpan.
 */
class PengecilGambar
{
    /**
     * @return array{paths: array<string,string>, width:int, height:int, byte_size:int, hash:string}
     */
    public function proses(string $berkasAsal, string $folder, string $namaDasar, ?int $lebarMaks = null, ?int $kualitas = null): array
    {
        $this->pastikanGd();

        $lebarMaks ??= 1600;
        $kualitas ??= (int) config('iksan.uploads.webp_quality', 82);

        $sumber = $this->buka($berkasAsal);

        try {
            // Orientasi EXIF diterapkan sebelum metadata dibuang. Tanpa langkah
            // ini, foto dari ponsel tampil miring.
            $sumber = $this->terapkanOrientasi($sumber, $berkasAsal);

            $asliL = imagesx($sumber);
            $asliT = imagesy($sumber);

            $skala = min(1.0, $lebarMaks / max(1, $asliL));
            $lebar = max(1, (int) round($asliL * $skala));
            $tinggi = max(1, (int) round($asliT * $skala));

            $kanvas = imagecreatetruecolor($lebar, $tinggi);
            imagealphablending($kanvas, false);
            imagesavealpha($kanvas, true);
            imagecopyresampled($kanvas, $sumber, 0, 0, 0, 0, $lebar, $tinggi, $asliL, $asliT);

            // Turunan dibuat dari kanvas utama, bukan dari berkas asal, supaya
            // tidak ada dekode ulang berkali-kali.
            $turunans = config('iksan.uploads.derivatives');
            $paths = [];

            foreach ($turunans as $nama => [$w, $h]) {
                $isi = match ($nama) {
                    'og' => $this->potongTengah($kanvas, $w, $h, $kualitas),
                    default => $this->skalaPas($kanvas, $w, $h, $kualitas),
                };

                $relatif = trim($folder, '/').'/'.$namaDasar.'-'.$nama.'.webp';
                \Illuminate\Support\Facades\Storage::disk('public')->put($relatif, $isi);
                $paths[$nama] = $relatif;
            }

            $isiUtama = \Illuminate\Support\Facades\Storage::disk('public')->get($paths['full']);

            return [
                'paths' => $paths,
                'width' => $lebar,
                'height' => $tinggi,
                'byte_size' => strlen($isiUtama),
                'hash' => hash('sha256', $isiUtama),
            ];
        } finally {
            imagedestroy($sumber);
        }
    }

    private function buka(string $berkas): \GdImage
    {
        $info = @getimagesize($berkas);

        if ($info === false) {
            throw new RuntimeException('Berkas bukan gambar yang dapat dibaca.');
        }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($berkas),
            IMAGETYPE_PNG => @imagecreatefrompng($berkas),
            IMAGETYPE_WEBP => @imagecreatefromwebp($berkas),
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($berkas) : false,
            default => false,
        };

        if ($img === false) {
            throw new RuntimeException('Format gambar tidak didukung pemroses.');
        }

        return $img;
    }

    private function terapkanOrientasi(\GdImage $img, string $berkas): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($berkas);
        $orientasi = $exif['Orientation'] ?? null;

        $hasil = match ($orientasi) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };

        if ($hasil === false || $hasil === null) {
            return $img;
        }

        imagedestroy($img);

        return $hasil;
    }

    private function skalaPas(\GdImage $sumber, int $lebar, int $tinggi, int $kualitas): string
    {
        $asliL = imagesx($sumber);
        $asliT = imagesy($sumber);

        // Tidak pernah memperbesar. Gambar kecil dibiarkan pada ukurannya.
        $skala = min(1.0, $lebar / max(1, $asliL), $tinggi / max(1, $asliT));

        $tujuanL = max(1, (int) round($asliL * $skala));
        $tujuanT = max(1, (int) round($asliT * $skala));

        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        $latar = imagecolorallocate($kanvas, 11, 11, 12);
        imagefilledrectangle($kanvas, 0, 0, $lebar, $tinggi, $latar);
        imagecopyresampled(
            $kanvas, $sumber,
            (int) (($lebar - $tujuanL) / 2), (int) (($tinggi - $tujuanT) / 2),
            0, 0, $tujuanL, $tujuanT, $asliL, $asliT,
        );

        $isi = $this->keWebp($kanvas, $kualitas);
        imagedestroy($kanvas);

        return $isi;
    }

    private function potongTengah(\GdImage $sumber, int $lebar, int $tinggi, int $kualitas): string
    {
        $asliL = imagesx($sumber);
        $asliT = imagesy($sumber);

        $rasioTujuan = $lebar / $tinggi;
        $rasioAsli = $asliL / max(1, $asliT);

        if ($rasioAsli > $rasioTujuan) {
            $potongT = $asliT;
            $potongL = (int) round($asliT * $rasioTujuan);
            $x = (int) (($asliL - $potongL) / 2);
            $y = 0;
        } else {
            $potongL = $asliL;
            $potongT = (int) round($asliL / $rasioTujuan);
            $x = 0;
            $y = (int) (($asliT - $potongT) / 2);
        }

        $kanvas = imagecreatetruecolor($lebar, $tinggi);
        imagecopyresampled($kanvas, $sumber, 0, 0, $x, $y, $lebar, $tinggi, $potongL, $potongT);

        $isi = $this->keWebp($kanvas, $kualitas);
        imagedestroy($kanvas);

        return $isi;
    }

    private function keWebp(\GdImage $img, int $kualitas): string
    {
        ob_start();
        imagewebp($img, null, $kualitas);
        $isi = (string) ob_get_clean();

        if ($isi === '') {
            throw new RuntimeException('Gagal mengubah gambar ke WebP.');
        }

        return $isi;
    }

    private function pastikanGd(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
            throw new RuntimeException('GD tanpa dukungan WebP tidak dapat memproses gambar.');
        }
    }
}
