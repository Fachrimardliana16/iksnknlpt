<?php

namespace Tests\Feature;

use App\Livewire\Admin\HeroSunting;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Skema URL tombol hero.
 *
 * Kolom primary_button_url diisi admin dan dirender langsung sebagai atribut
 * href di beranda. Laravel meng-escape atributnya, jadi ini BUKAN XSS — tetapi
 * tanpa penyaringan, nilai seperti `javascript:alert(1)` atau
 * `file:///etc/passwd` tetap sampai ke peramban dan dijalankan saat pengunjung
 * mengklik tombolnya.
 *
 * Penyaringan ditaruh di model (HeroSlide::urlTombolAman) supaya berlaku untuk
 * setiap tempat yang merender tombol ini, bukan hanya beranda.
 */
class HeroUrlAmanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    public function test_skema_berbahaya_ditolak_model(): void
    {
        foreach ([
            'javascript:alert(1)',
            'file:///etc/passwd',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
        ] as $buruk) {
            $this->assertNull(
                HeroSlide::urlTombolAman($buruk),
                'Skema berbahaya harus ditolak: '.$buruk,
            );
        }
    }

    public function test_url_protokol_relatif_ditolak(): void
    {
        // "//evil.test" dibaca peramban sebagai HOST LAIN, bukan jalur di
        // dalam situs. Ini yang paling mudah lolos dari penyaringan naif yang
        // hanya memeriksa awalan "/".
        $this->assertNull(HeroSlide::urlTombolAman('//evil.test/masuk'));
    }

    public function test_url_sah_diterima(): void
    {
        $this->assertSame('/produk', HeroSlide::urlTombolAman('/produk'));
        $this->assertSame('/kontak?dari=hero', HeroSlide::urlTombolAman('/kontak?dari=hero'));
        $this->assertSame('https://iksanknalpot.test/produk', HeroSlide::urlTombolAman('https://iksanknalpot.test/produk'));
        $this->assertSame('http://contoh.test', HeroSlide::urlTombolAman('http://contoh.test'));
    }

    public function test_kosong_dan_null_menghasilkan_null(): void
    {
        $this->assertNull(HeroSlide::urlTombolAman(null));
        $this->assertNull(HeroSlide::urlTombolAman(''));
        $this->assertNull(HeroSlide::urlTombolAman('   '));
    }

    public function test_beranda_tidak_memasang_skema_berbahaya_pada_href(): void
    {
        // Slide aktif dengan URL berbahaya, seolah tersimpan lewat jalur lama
        // atau basis data yang disunting langsung.
        HeroSlide::query()->create([
            'title_id' => 'Slide jahat',
            'title_en' => 'Evil slide',
            'media_type' => 'image',
            'media_path' => 'hero/jahat.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'primary_button_label_id' => 'Klik saya',
            'primary_button_url' => 'javascript:alert(1)',
        ]);

        \App\Support\CacheKatalog::tandaiBerubah('uji url jahat');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        // Labelnya sendiri tetap boleh tampil sebagai teks biasa.
        $this->assertStringContainsString('Slide jahat', $html);
    }

    public function test_halaman_sunting_menolak_url_ngawur(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::query()->create([
            'title_id' => 'Uji',
            'title_en' => 'Test',
            'media_type' => 'image',
            'media_path' => 'hero/uji.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.primary_button_url', 'javascript:alert(1)')
            ->call('simpan')
            ->assertHasErrors('form.primary_button_url');

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.secondary_button_url', 'file:///etc/passwd')
            ->call('simpan')
            ->assertHasErrors('form.secondary_button_url');
    }
}
