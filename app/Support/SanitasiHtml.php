<?php

namespace App\Support;

/**
 * Sanitasi HTML dengan daftar putih (checklist F10-03).
 *
 * Dipakai untuk konten yang MEMANG boleh memuat penanda format — deskripsi
 * produk dari penyunting kaya, atau isi halaman statis yang ditulis lewat
 * panel. Konten seperti itu tidak boleh langsung dicetak dengan {!! !!}.
 *
 * Daftar putih, bukan daftar hitam. Pendekatan daftar hitam selalu kalah: ada
 * saja penanda atau atribut baru yang belum terpikirkan. Di sini, apa pun yang
 * tidak disebut secara eksplisit dibuang.
 *
 * Yang dibuang, dan alasannya:
 *
 *   script, iframe, object, embed, form, input
 *     Dapat menjalankan kode atau memuat halaman lain.
 *
 *   style, dan setiap atribut yang diawali on
 *     Gaya dapat memuat URL luar lewat url(), dan penangan kejadian adalah
 *     tempat JavaScript paling sering diselipkan.
 *
 *   href dan src dengan skema javascript:, data:, atau vbscript:
 *     javascript: menjalankan kode saat diklik. data: dapat memuat dokumen
 *     HTML lengkap yang lalu berjalan di asal aplikasi ini.
 *
 * Tidak memakai pustaka pihak ketiga: yang dibutuhkan hanya daftar putih kecil,
 * dan menambah ketergantungan baru untuk itu memperbesar permukaan serangan
 * alih-alih mengecilkannya.
 */
class SanitasiHtml
{
    /** Penanda yang diizinkan, tanpa atribut apa pun. */
    private const DIIZINKAN = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'del',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'a', 'img', 'hr',
    ];

    /** Atribut yang diizinkan, per penanda. */
    private const ATRIBUT = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    /** Penanda yang isinya dibuang seluruhnya, termasuk teksnya. */
    private const DIBUANG_DENGAN_ISI = ['script', 'style', 'iframe', 'object', 'embed', 'noscript'];

    public static function bersihkan(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Penanda yang isinya berbahaya dibuang lebih dulu, bersama isinya.
        // Kalau hanya dibuang penandanya, isi skripnya tetap tertinggal sebagai
        // teks dan bisa ikut tercetak.
        foreach (self::DIBUANG_DENGAN_ISI as $tag) {
            $html = preg_replace(
                '#<'.$tag.'\b[^>]*>.*?</'.$tag.'\s*>#is',
                '',
                (string) $html,
            ) ?? $html;

            // Bentuk yang tidak ditutup.
            $html = preg_replace('#<'.$tag.'\b[^>]*/?>#is', '', $html) ?? $html;
        }

        return self::susunUlang($html);
    }

    /**
     * Menyusun ulang HTML dari token yang diizinkan saja.
     *
     * Pendekatan menyusun ulang dipilih daripada menghapus pola berbahaya satu
     * per satu: hasilnya hanya memuat yang ada di daftar putih, sehingga
     * penanda yang belum terpikirkan pun tidak lolos.
     */
    private static function susunUlang(string $html): string
    {
        $keluaran = '';
        $posisi = 0;
        $panjang = strlen($html);

        while ($posisi < $panjang) {
            $buka = strpos($html, '<', $posisi);

            if ($buka === false) {
                $keluaran .= self::teks(substr($html, $posisi));

                break;
            }

            $keluaran .= self::teks(substr($html, $posisi, $buka - $posisi));

            $tutup = strpos($html, '>', $buka);

            if ($tutup === false) {
                // Kurang dari yang menggantung: sisanya diperlakukan sebagai teks.
                $keluaran .= self::teks(substr($html, $buka));

                break;
            }

            $potongan = substr($html, $buka, $tutup - $buka + 1);

            $keluaran .= self::penanda($potongan);

            $posisi = $tutup + 1;
        }

        return trim($keluaran);
    }

    /** Satu potongan "<...>": dipertahankan bila diizinkan, dibuang bila tidak. */
    private static function penanda(string $potongan): string
    {
        if (! preg_match('#^<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9]*)([^>]*)>$#', $potongan, $m)) {
            // Bukan penanda yang sah — misalnya "< 5" atau komentar rusak.
            return self::teks($potongan);
        }

        $miring = $m[1] === '/';
        $nama = strtolower($m[2]);
        $sisa = $m[3];

        if (! in_array($nama, self::DIIZINKAN, true)) {
            return '';
        }

        if ($miring) {
            return '</'.$nama.'>';
        }

        // Penanda tanpa isi.
        $mandiri = in_array($nama, ['br', 'hr', 'img'], true);
        $atribut = self::atribut($nama, $sisa);

        return '<'.$nama.$atribut.($mandiri ? ' />' : '>');
    }

    /** Atribut yang diizinkan untuk satu penanda. */
    private static function atribut(string $nama, string $sisa): string
    {
        $diizinkan = self::ATRIBUT[$nama] ?? [];

        if ($diizinkan === [] || trim($sisa) === '') {
            return '';
        }

        $hasil = '';

        // Nilai atribut dapat memakai kutip ganda, tunggal, atau tanpa kutip.
        if (! preg_match_all(
            '#([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))#',
            $sisa,
            $cocok,
            PREG_SET_ORDER,
        )) {
            return '';
        }

        foreach ($cocok as $a) {
            $atribut = strtolower($a[1]);

            if (! in_array($atribut, $diizinkan, true)) {
                continue;
            }

            $nilai = $a[3] !== '' ? $a[3] : ($a[4] !== '' ? $a[4] : ($a[5] ?? ''));

            if (($atribut === 'href' || $atribut === 'src') && ! self::alamatAman($nilai)) {
                continue;
            }

            // Nilai selalu dibungkus kutip ganda, dan kutip gandanya sendiri
            // dibuang supaya tidak dapat menutup atribut lalu menyelipkan
            // atribut baru.
            $bersih = htmlspecialchars($nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $hasil .= ' '.$atribut.'="'.$bersih.'"';
        }

        return $hasil;
    }

    /**
     * Apakah alamat aman dipakai pada href atau src.
     *
     * Skema yang membahayakan ditolak; sisanya dibolehkan, termasuk alamat
     * relatif yang tidak memiliki skema sama sekali.
     */
    private static function alamatAman(string $nilai): bool
    {
        // Spasi dan karakter kendali dibuang lebih dulu: "java\nscript:" adalah
        // cara klasik menyamarkan skema berbahaya.
        $sidik = strtolower(preg_replace('/[\x00-\x20]+/', '', $nilai) ?? $nilai);

        foreach (['javascript:', 'vbscript:', 'data:', 'blob:', 'file:'] as $bahaya) {
            if (str_starts_with($sidik, $bahaya)) {
                return false;
            }
        }

        return true;
    }

    /** Teks biasa: di-escape, tanpa perubahan lain. */
    private static function teks(string $teks): string
    {
        return htmlspecialchars($teks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Membersihkan lalu mengubah baris kosong menjadi paragraf.
     *
     * Dipakai untuk kolom teks panjang yang ditulis sebagai teks biasa, tetapi
     * boleh memuat penanda format sederhana.
     */
    public static function keParagraf(?string $teks): string
    {
        $bersih = self::bersihkan($teks);

        if ($bersih === '') {
            return '';
        }

        $paragraf = preg_split('/\n\s*\n/', $bersih) ?: [];

        return implode('', array_map(
            fn (string $p): string => '<p>'.trim(preg_replace('/\s*\n\s*/', ' ', $p) ?? $p).'</p>',
            $paragraf,
        ));
    }
}
