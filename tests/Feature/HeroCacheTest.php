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
 * Cache beranda harus dibatalkan setiap kali slide hero berubah.
 *
 * Gejala yang dilaporkan: "saya sudah mengganti gambar, tapi tidak muncul".
 *
 * Penyebabnya bukan penyimpanan berkas. Beranda menyimpan daftar slide di
 * cache 'home:isi' selama 600 detik; gambar baru tersimpan di disk dengan
 * benar, tetapi hero di halaman depan masih menyajikan MODEL slide lama
 * sampai cache kedaluwarsa. Tidak ada galat di log, dan berkasnya memang ada
 * — karena itu gejalanya mudah disalahartikan sebagai unggahan yang gagal.
 *
 * Yang diuji: setelah setiap tindakan yang mengubah apa yang tayang, versi
 * cache naik, sehingga kunci lama tidak terpakai lagi.
 */
class HeroCacheTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    private function slide(array $atribut = []): HeroSlide
    {
        return HeroSlide::query()->create($atribut + [
            'title_id' => 'Slide uji',
            'title_en' => 'Test slide',
            'media_type' => 'image',
            'media_path' => 'hero/uji.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);
    }

    /**
     * Versi cache katalog saat ini.
     *
     * Dibaca lewat refleksi karena CacheKatalog menyimpan kuncinya sebagai
     * konstanta privat — yang diuji adalah PERILAKU (versi naik), bukan nama
     * kuncinya.
     */
    private function versi(): int
    {
        $refleksi = new \ReflectionClass(\App\Support\CacheKatalog::class);
        $kunci = $refleksi->getConstant('KUNCI_VERSI');

        return (int) \Illuminate\Support\Facades\Cache::get($kunci, 1);
    }

    public function test_menyimpan_slide_membatalkan_cache_beranda(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide();

        // Panaskan cache beranda lebih dulu: inilah keadaan yang membuat
        // gambar lama tetap tampil.
        $this->get('/')->assertOk();

        $sebelum = $this->versi();

        Livewire::test(HeroSunting::class, ['ulid' => $slide->ulid])
            ->set('form.title_id', 'Judul setelah diubah')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertGreaterThan(
            $sebelum,
            $this->versi(),
            'Menyimpan slide harus menaikkan versi cache — kalau tidak, hero di beranda tetap yang lama sampai 10 menit.',
        );
    }

    public function test_merubah_status_aktif_membatalkan_cache_beranda(): void
    {
        $this->actingAs($this->admin());

        // Dua-duanya aktif, jadi menonaktifkan salah satu DIIZINKAN
        // (min_slide_aktif = 1 tetap terpenuhi). Dengan satu slide saja,
        // penonaktifan memang ditolak dan cache tidak berubah — itu perilaku
        // benar, bukan yang mau diuji di sini.
        $this->slide(['title_id' => 'Aktif', 'is_active' => true, 'display_order' => 0]);
        $kedua = $this->slide(['title_id' => 'Kedua', 'is_active' => true, 'display_order' => 1]);

        $this->get('/')->assertOk();
        $sebelum = $this->versi();

        Livewire::test(HeroBuilder::class)
            ->call('ubahAktif', $kedua->id)
            ->assertSet('galat', null);

        $this->assertFalse($kedua->fresh()->is_active);
        $this->assertGreaterThan($sebelum, $this->versi());
    }

    public function test_menghapus_slide_membatalkan_cache_beranda(): void
    {
        $this->actingAs($this->admin());
        $this->slide(['is_active' => true]);
        $cadangan = $this->slide(['title_id' => 'Cadangan', 'is_active' => false, 'display_order' => 1]);

        $this->get('/')->assertOk();
        $sebelum = $this->versi();

        Livewire::test(HeroBuilder::class)->call('hapus', $cadangan->id);

        $this->assertGreaterThan($sebelum, $this->versi());
    }

    public function test_menggeser_urutan_membatalkan_cache_beranda(): void
    {
        $this->actingAs($this->admin());
        $a = $this->slide(['title_id' => 'Pertama', 'display_order' => 0]);
        $this->slide(['title_id' => 'Kedua', 'display_order' => 1]);

        $this->get('/')->assertOk();
        $sebelum = $this->versi();

        Livewire::test(HeroBuilder::class)->call('turun', $a->id);

        $this->assertGreaterThan($sebelum, $this->versi());
    }

    public function test_beranda_menampilkan_gambar_baru_setelah_disimpan(): void
    {
        $this->actingAs($this->admin());
        $slide = $this->slide(['media_path' => 'hero/lama.webp']);

        // Beranda disimpan di cache dengan gambar lama.
        $awal = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('hero/lama.webp', $awal);

        // Slide diubah tanpa mengunggah berkas baru — jalur yang sama dengan
        // mengganti gambar lewat unggahan, karena keduanya berakhir di save().
        $slide->update(['media_path' => 'hero/baru.webp']);
        \App\Support\CacheKatalog::tandaiBerubah('uji ganti gambar');

        $akhir = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('hero/baru.webp', $akhir);
        $this->assertStringNotContainsString('hero/lama.webp', $akhir);
    }
}
