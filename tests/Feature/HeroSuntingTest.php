<?php

namespace Tests\Feature;

use App\Livewire\Admin\HeroBuilder;
use App\Livewire\Admin\HeroSunting;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hero: halaman sunting sendiri, URL tombol opsional, gambar ponsel opsional.
 *
 * Tiga hal yang diuji, semuanya adalah keadaan yang dulu tidak mungkin:
 *
 *   1. Menyunting pindah ke halaman sendiri. Daftar tidak lagi memuat form.
 *   2. Slide boleh punya tombol tanpa URL — dulu aturan `url` menolaknya
 *      dengan pesan "format url tidak sah" pada kolom yang memang opsional.
 *   3. Mengunggah gambar desktop tanpa gambar ponsel tidak boleh menghapus
 *      gambar ponsel yang sudah ada. Dulu ia ditimpa null.
 */
class HeroSuntingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    private function slide(): HeroSlide
    {
        return HeroSlide::query()->create([
            'title_id' => 'Slide uji',
            'title_en' => 'Test slide',
            'media_type' => 'image',
            'media_path' => 'hero/uji.webp',
            'media_path_mobile' => 'hero/uji-ponsel.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);
    }

    public function test_daftar_hero_tidak_lagi_memuat_form_sunting(): void
    {
        $this->actingAs($this->admin());
        $this->slide();

        $html = Livewire::test(HeroBuilder::class)->html();

        // Form pindah ke halaman sendiri; daftar hanya mengelola urutan dan
        // status aktif. Kalau form kembali menumpang, biaya membuka daftar
        // ikut membengkak tanpa manfaat.
        $this->assertStringNotContainsString('wire:submit="simpan"', $html);
        $this->assertStringContainsString('/sunting', $html);
    }

    public function test_rute_sunting_memakai_ulid(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        $this->get('/admin/hero/'.$slide->ulid.'/sunting')->assertOk();

        // Id angka tidak boleh menjadi alamat: itu membuat slide dapat
        // ditebak berurutan dari luar.
        $this->get('/admin/hero/'.$slide->id.'/sunting')->assertNotFound();
    }

    public function test_url_tombol_boleh_kosong(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.title_id', 'Judul')
            ->set('form.title_en', 'Title')
            ->set('form.primary_button_label_id', 'Belanja')
            ->set('form.primary_button_url', '')
            ->set('form.secondary_button_url', '')
            ->call('simpan')
            ->assertHasNoErrors();

        $slide->refresh();

        // Label tersimpan, URL null. Slide tanpa tujuan tombol tetap sah.
        $this->assertNull($slide->primary_button_url);
        $this->assertSame('Belanja', $slide->primary_button_label_id);
    }

    public function test_url_tombol_relatif_diterima(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        // "/produk" adalah tujuan yang wajar di dalam situs sendiri. Aturan
        // `url` bawaan menolaknya, dan pesannya tidak menjelaskan apa pun.
        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.primary_button_url', '/produk')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('/produk', $slide->fresh()->primary_button_url);
    }

    public function test_url_tombol_ngawur_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.primary_button_url', 'bukan url sama sekali')
            ->call('simpan')
            ->assertHasErrors('form.primary_button_url');
    }

    public function test_buang_gambar_ponsel_membuat_slide_memakai_gambar_desktop(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        $this->assertSame('hero/uji-ponsel.webp', $slide->media_path_mobile);

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('hapusPonsel', true)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertNull($slide->fresh()->media_path_mobile);
        // Gambar desktop tidak boleh ikut hilang.
        $this->assertSame('hero/uji.webp', $slide->fresh()->media_path);
    }

    public function test_simpan_tanpa_berkas_baru_tidak_menyentuh_gambar(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.title_id', 'Judul baru')
            ->call('simpan')
            ->assertHasNoErrors();

        $slide->refresh();

        // Inilah bug lama: menyimpan tanpa mengunggah apa pun menghapus
        // gambar ponsel karena hasil unggahan bernilai null.
        $this->assertSame('hero/uji.webp', $slide->media_path);
        $this->assertSame('hero/uji-ponsel.webp', $slide->media_path_mobile);
        $this->assertSame('Judul baru', $slide->title_id);
    }

    public function test_beranda_memakai_picture_untuk_gambar_ponsel(): void
    {
        $this->slide();

        $html = $this->get('/')->assertOk()->getContent();

        // Beranda dulu memakai background-image, sehingga gambar ponsel yang
        // tersimpan tidak pernah terpakai. <picture> membuat pilihan sumbernya
        // milik peramban.
        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('max-width: 639px', $html);
    }

    public function test_tamu_tidak_boleh_membuka_halaman_sunting(): void
    {
        $slide = $this->slide();

        $this->get('/admin/hero/'.$slide->ulid.'/sunting')->assertRedirect('/admin/login');
    }
}
