<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Pembuat gambar contoh untuk seeder.
 *
 * Berkas biner tidak pernah disimpan di repositori. Gambar dibuat saat seeding
 * dengan GD, diberi penanda teks, dan mudah dikenali sebagai foto contoh yang
 * harus diganti sebelum publikasi.
 */
class GambarContoh
{
    /**
     * Tulis satu gambar contoh beserta turunannya.
     *
     * @param  array<string, array{0:int,1:int}>  $turunans
     * @return array{path:string,width:int,height:int,byte_size:int,hash:string,paths:array<string,string>}
     */
    public static function buat(
        string $folder,
        string $namaBerkas,
        string $teks,
        int $lebar = 1600,
        int $tinggi = 1200,
        string $warna = '#1f2933',
        array $turunans = ['thumb' => [320, 240], 'card' => [640, 480], 'full' => [1600, 1200], 'og' => [1200, 630]],
    ): array {
        self::pastikanGd();

        $jalur = [];

        foreach ($turunans as $nama => [$w, $h]) {
            $relatif = trim($folder, '/').'/'.pathinfo($namaBerkas, PATHINFO_FILENAME).'-'.$nama.'.webp';
            $isi = self::render($teks, $w, $h, $warna, $nama);

            Storage::disk('public')->put($relatif, $isi);
            $jalur[$nama] = $relatif;
        }

        $utama = 'full';
        $isiUtama = Storage::disk('public')->get($jalur[$utama]);

        return [
            'path' => $jalur[$utama],
            'paths' => $jalur,
            'width' => $turunans[$utama][0],
            'height' => $turunans[$utama][1],
            'byte_size' => strlen($isiUtama),
            'hash' => hash('sha256', $isiUtama),
        ];
    }

    private static function render(string $teks, int $lebar, int $tinggi, string $warna, string $label): string
    {
        $img = imagecreatetruecolor($lebar, $tinggi);

        [$r, $g, $b] = self::keRgb($warna);
        $latar = imagecolorallocate($img, $r, $g, $b);
        imagefilledrectangle($img, 0, 0, $lebar, $tinggi, $latar);

        // Tenun karbon dua arah, sama seperti panel di situs, supaya gambar
        // contoh tidak terlihat sebagai kotak warna rata.
        $garis = imagecolorallocatealpha($img, 255, 255, 255, 118);
        $langkah = max(6, (int) ($lebar / 40));
        for ($x = 0; $x < $lebar; $x += $langkah) {
            imageline($img, $x, 0, $x, $tinggi, $garis);
        }
        for ($y = 0; $y < $tinggi; $y += $langkah) {
            imageline($img, 0, $y, $lebar, $y, $garis);
        }

        $tinta = imagecolorallocate($img, 237, 237, 235);
        $aksen = imagecolorallocate($img, 200, 16, 46);

        // Tiga batang bertingkat, penanda yang sama dengan torque stripe di UI.
        $baseY = (int) ($tinggi * 0.5) - 40;
        foreach ([[0.22, 2], [0.145, 2], [0.075, 2]] as $i => [$rasio, $tebal]) {
            $w = (int) ($lebar * $rasio);
            $y = $baseY + ($i * 7);
            imagefilledrectangle($img, (int) ($lebar * 0.08), $y, (int) ($lebar * 0.08) + $w, $y + $tebal, $aksen);
        }

        $font = 5;
        $teks = mb_strimwidth($teks, 0, 34, '...');

        if (function_exists('imagettftext')) {
            $fontFile = self::font();
            if ($fontFile !== null) {
                $ukuran = max(14, (int) ($lebar / 26));
                imagettftext($img, $ukuran, 0, (int) ($lebar * 0.08), $baseY + 44, $tinta, $fontFile, $teks);
                imagettftext($img, (int) ($ukuran * 0.45), 0, (int) ($lebar * 0.08), $baseY + 44 + $ukuran + 12, $tinta, $fontFile, 'FOTO CONTOH / '.$label.' '.$lebar.'x'.$tinggi);
            } else {
                imagestring($img, $font, (int) ($lebar * 0.08), $baseY + 30, $teks, $tinta);
                imagestring($img, $font, (int) ($lebar * 0.08), $baseY + 50, 'FOTO CONTOH', $tinta);
            }
        } else {
            imagestring($img, $font, (int) ($lebar * 0.08), $baseY + 30, $teks, $tinta);
            imagestring($img, $font, (int) ($lebar * 0.08), $baseY + 50, 'FOTO CONTOH', $tinta);
        }

        ob_start();
        imagewebp($img, null, 82);
        $isi = (string) ob_get_clean();
        imagedestroy($img);

        if ($isi === '') {
            throw new RuntimeException('Gagal membuat gambar contoh: hasil WebP kosong.');
        }

        return $isi;
    }

    /** Font TrueType bawaan pustaka Dompdf, juga dipakai panel, jadi selalu ada. */
    private static function font(): ?string
    {
        $kandidat = [
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
        ];

        foreach ($kandidat as $berkas) {
            if (is_file($berkas)) {
                return $berkas;
            }
        }

        foreach (glob(base_path('vendor/dompdf/dompdf/lib/fonts/*.ttf')) ?: [] as $berkas) {
            return $berkas;
        }

        return null;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function keRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function pastikanGd(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Ekstensi GD tidak aktif. Seeder gambar tidak dapat berjalan.');
        }

        if (! function_exists('imagewebp')) {
            throw new RuntimeException('GD tanpa dukungan WebP. Seeder gambar tidak dapat berjalan.');
        }
    }
}
