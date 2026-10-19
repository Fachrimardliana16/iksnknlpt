<?php

namespace Tests\Unit;

use App\Support\SanitasiHtml;
use PHPUnit\Framework\TestCase;

/**
 * Uji sanitasi HTML (checklist F10-03).
 *
 * Yang diuji bukan sekadar "tag script dibuang", melainkan cara-cara yang
 * lazim dipakai untuk melewatinya: skema yang disamarkan dengan spasi dan
 * karakter kendali, penanda yang tidak ditutup, dan atribut penangan kejadian
 * yang disisipkan lewat kutip.
 */
class SanitasiHtmlTest extends TestCase
{
    public function test_penanda_format_sederhana_dipertahankan(): void
    {
        $hasil = SanitasiHtml::bersihkan('<p>Halo <b>dunia</b> dan <em>semua</em>.</p>');

        $this->assertSame('<p>Halo <b>dunia</b> dan <em>semua</em>.</p>', $hasil);
    }

    public function test_skrip_dibuang_beserta_isinya(): void
    {
        $hasil = SanitasiHtml::bersihkan('<p>Aman</p><script>alert("jahat")</script>');

        $this->assertStringNotContainsString('<script', $hasil);
        // Isinya juga harus hilang: kalau hanya penandanya dibuang, teks
        // skripnya tetap tertinggal dan ikut tercetak di halaman.
        $this->assertStringNotContainsString('alert', $hasil);
        $this->assertStringContainsString('Aman', $hasil);
    }

    public function test_penangan_kejadian_dibuang(): void
    {
        $hasil = SanitasiHtml::bersihkan('<p onclick="jahat()">Klik</p>');
        $hasil2 = SanitasiHtml::bersihkan('<img src="/a.png" onerror="jahat()">');

        $this->assertStringNotContainsString('onclick', $hasil);
        $this->assertStringNotContainsString('onerror', $hasil2);
        $this->assertStringContainsString('Klik', $hasil);
    }

    public function test_skema_javascript_ditolak(): void
    {
        foreach ([
            '<a href="javascript:alert(1)">x</a>',
            '<a href="JaVaScRiPt:alert(1)">x</a>',
            // Spasi dan tab di tengah nama skema adalah cara klasik menyamarkan.
            "<a href=\"java\tscript:alert(1)\">x</a>",
            "<a href=\"java\nscript:alert(1)\">x</a>",
            '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
            '<a href="vbscript:msgbox(1)">x</a>',
        ] as $jahat) {
            $hasil = SanitasiHtml::bersihkan($jahat);

            $this->assertStringNotContainsString('javascript', strtolower($hasil), "lolos: $jahat");
            $this->assertStringNotContainsString('data:', strtolower($hasil), "lolos: $jahat");
            $this->assertStringNotContainsString('vbscript', strtolower($hasil), "lolos: $jahat");
        }
    }

    public function test_alamat_biasa_dipertahankan(): void
    {
        $hasil = SanitasiHtml::bersihkan('<a href="https://contoh.test/halaman" title="Info">tautan</a>');

        $this->assertStringContainsString('href="https://contoh.test/halaman"', $hasil);
        $this->assertStringContainsString('title="Info"', $hasil);
    }

    public function test_tautan_relatif_dan_anchor_dipertahankan(): void
    {
        $hasil = SanitasiHtml::bersihkan('<a href="/produk/knalpot">relatif</a><a href="#bagian">anchor</a>');

        $this->assertStringContainsString('href="/produk/knalpot"', $hasil);
        $this->assertStringContainsString('href="#bagian"', $hasil);
    }

    public function test_iframe_dan_object_dibuang(): void
    {
        $hasil = SanitasiHtml::bersihkan(
            '<p>a</p><iframe src="https://jahat.test"></iframe><object data="x"></object><p>b</p>'
        );

        $this->assertStringNotContainsString('<iframe', $hasil);
        $this->assertStringNotContainsString('<object', $hasil);
        $this->assertStringContainsString('<p>a</p>', $hasil);
        $this->assertStringContainsString('<p>b</p>', $hasil);
    }

    public function test_atribut_style_dibuang(): void
    {
        $hasil = SanitasiHtml::bersihkan('<p style="background:url(javascript:alert(1))">x</p>');

        $this->assertStringNotContainsString('style', $hasil);
        $this->assertStringContainsString('x', $hasil);
    }

    public function test_kutip_dalam_nilai_tidak_dapat_menyelipkan_atribut(): void
    {
        // Nilai yang berusaha menutup atribut lalu menambah penangan kejadian.
        $hasil = SanitasiHtml::bersihkan('<a href="/x" title=\'a" onmouseover="jahat()\'>x</a>');

        // Atribut yang benar selalu berbentuk name="..." dengan tanda kutip
        // harfiah. Kutip yang diselipkan sudah menjadi &quot;, sehingga
        // "onmouseover=&quot;" BUKAN atribut — ia bagian dari nilai title.
        // Karena itu yang diperiksa adalah daftar atribut harfiah, bukan
        // sekadar ada tidaknya kata "onmouseover" di dalam keluaran.
        preg_match_all('/\s([a-zA-Z][-a-zA-Z0-9]*)\s*=\s*"/', $hasil, $cocok);

        $atribut = array_map('strtolower', $cocok[1]);

        sort($atribut);

        $this->assertSame(['href', 'title'], $atribut, 'hanya href dan title yang boleh menjadi atribut');

        // Kutip yang diselipkan terbukti ter-escape, bukan menutup nilai.
        $this->assertStringContainsString('&quot;', $hasil);
    }

    public function test_penanda_tidak_dikenal_dibuang(): void
    {
        $hasil = SanitasiHtml::bersihkan('<p>a</p><marquee>jalan</marquee><blink>b</blink>');

        $this->assertStringNotContainsString('<marquee', $hasil);
        $this->assertStringNotContainsString('<blink', $hasil);
        // Isi penanda yang tidak dikenal tetap dipertahankan sebagai teks.
        $this->assertStringContainsString('jalan', $hasil);
    }

    public function test_teks_biasa_di_escape(): void
    {
        $hasil = SanitasiHtml::bersihkan('Harga < 5 juta & lebih murah');

        $this->assertStringContainsString('&lt;', $hasil);
        $this->assertStringContainsString('&amp;', $hasil);
    }

    public function test_gambar_dengan_ukuran_dipertahankan(): void
    {
        $hasil = SanitasiHtml::bersihkan('<img src="/foto.jpg" alt="Knalpot" width="600" height="400">');

        $this->assertStringContainsString('src="/foto.jpg"', $hasil);
        $this->assertStringContainsString('alt="Knalpot"', $hasil);
        $this->assertStringContainsString('width="600"', $hasil);
    }

    public function test_tabel_dipertahankan(): void
    {
        $hasil = SanitasiHtml::bersihkan(
            '<table><thead><tr><th colspan="2">Judul</th></tr></thead><tbody><tr><td>a</td></tr></tbody></table>'
        );

        $this->assertStringContainsString('<table>', $hasil);
        $this->assertStringContainsString('colspan="2"', $hasil);
        $this->assertStringContainsString('<td>a</td>', $hasil);
    }

    public function test_kosong_menghasilkan_kosong(): void
    {
        $this->assertSame('', SanitasiHtml::bersihkan(null));
        $this->assertSame('', SanitasiHtml::bersihkan(''));
        $this->assertSame('', SanitasiHtml::bersihkan('   '));
    }

    public function test_ke_paragraf_memecah_baris_kosong(): void
    {
        $hasil = SanitasiHtml::keParagraf("Baris pertama.\n\nBaris kedua.");

        $this->assertSame('<p>Baris pertama.</p><p>Baris kedua.</p>', $hasil);
    }

    public function test_ke_paragraf_menghilangkan_skrip(): void
    {
        $hasil = SanitasiHtml::keParagraf("Halo\n\n<script>jahat()</script>");

        $this->assertStringNotContainsString('script', $hasil);
        $this->assertStringContainsString('<p>Halo</p>', $hasil);
    }
}
