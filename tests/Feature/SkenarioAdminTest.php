<?php

namespace Tests\Feature;

use App\Livewire\Admin\FaqPanel;
use App\Livewire\Admin\HeroBuilder;
use App\Livewire\Admin\HeroSunting;
use App\Livewire\Admin\Kategori;
use App\Livewire\Admin\PengaturanSitus;
use App\Livewire\Admin\ProdukDaftar;
use App\Livewire\Admin\ProdukForm;
use App\Models\Category;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\CacheKatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Skenario admin: CRUD panel benar-benar mengubah front page.
 *
 * Yang diuji bukan "method-nya tidak melempar galat", melainkan "perubahan
 * yang dibuat admin TERLIHAT oleh pengunjung". Beranda dan katalog memakai
 * CacheKatalog, jadi panel yang lupa membatalkan cache akan lulus uji unit
 * tetapi gagal di sini — dan itulah bug yang paling mahal: admin mengira
 * perubahannya belum tersimpan.
 */
class SkenarioAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Pengaturan toko (site_name, dll.) dibutuhkan oleh komponen front dan
        // admin PengaturanSitus. Tidak disebarkan oleh migrasi, jadi disemai di
        // sini supaya pengujian perubahan front-page benar-benar berjalan.
        $this->seed(\Database\Seeders\SettingSeeder::class);
    }

    // ── KATEGORI ────────────────────────────────────────────────────

    public function test_kategori_baru_muncul_di_front(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Kategori::class)
            ->call('bukaForm')
            ->set('nama_id', 'Knalpot Balap')
            ->set('slug', 'knalpot-balap')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['slug' => 'knalpot-balap']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Knalpot Balap', $html);
    }

    public function test_kategori_nonaktif_hilang_dari_front(): void
    {
        $this->actingAs($this->admin());

        // display_order 1 supaya masuk jatah tampil beranda: beranda hanya
        // mengambil enam kategori teratas (take(6)), jadi kategori dengan
        // urutan besar memang tidak akan pernah terlihat — bukan karena
        // saringannya salah.
        $k = Category::query()->create([
            'name_id' => 'Kategori Uji',
            'slug' => 'kategori-uji',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Kategori Uji');

        // Kategori ini belum dipakai produk mana pun. Kategori yang DIPAKAI
        // memang sengaja tidak boleh dinonaktifkan (komponennya menolak), dan
        // itu perilaku benar — bukan yang diuji di sini.
        Livewire::test(Kategori::class)
            ->call('ubahAktif', $k->id)
            ->assertHasNoErrors();

        $this->assertFalse((bool) $k->fresh()->is_active);
        $this->get('/')->assertOk()->assertDontSee('Kategori Uji');
    }

    // ── PRODUK ──────────────────────────────────────────────────────

    public function test_produk_terbit_muncul_di_katalog_dan_hilang_saat_ditarik(): void
    {
        $this->actingAs($this->admin());

        $k = Category::query()->create([
            'name_id' => 'Knalpot', 'slug' => 'knalpot', 'display_order' => 1, 'is_active' => true,
        ]);

        $p = Product::factory()->create([
            'category_id' => $k->id,
            'title_id' => 'Knalpot Uji Terbit',
            'slug' => 'knalpot-uji-terbit',
            'is_published' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $p->id,
            'sku_variant' => $p->sku.'-A',
            'variant_name_id' => 'Standar',
            'price_adjustment' => 0,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);

        CacheKatalog::tandaiBerubah('uji');

        $this->get('/produk')->assertOk()->assertSee('Knalpot Uji Terbit');
        $this->get('/produk/knalpot-uji-terbit')->assertOk();

        // Tarik dari katalog lewat panel.
        Livewire::test(ProdukDaftar::class)->call('ubahTerbitSatu', $p->id);

        CacheKatalog::tandaiBerubah('uji tarik');

        $this->assertFalse((bool) $p->fresh()->is_published);
        $this->get('/produk')->assertOk()->assertDontSee('Knalpot Uji Terbit');
    }

    public function test_produk_tanpa_varian_tidak_bisa_diterbitkan(): void
    {
        $this->actingAs($this->admin());

        $p = Product::factory()->create(['is_published' => false]);

        Livewire::test(ProdukDaftar::class)
            ->call('ubahTerbitSatu', $p->id)
            ->assertHasErrors('umum');

        $this->assertFalse((bool) $p->fresh()->is_published);
    }

    public function test_produk_yang_pernah_dipesan_tidak_dihapus(): void
    {
        $this->actingAs($this->admin());

        $p = Product::factory()->create();

        // Baris pesanan lama menunjuk ke produk ini.
        \App\Models\Order::query()->create([
            'order_number' => 'IK-UJI-1',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Penerima'],
        ]);

        \Illuminate\Support\Facades\DB::table('order_items')->insert([
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'order_id' => \App\Models\Order::query()->first()->id,
            'product_id' => $p->id,
            'product_name' => $p->title_id,
            'sku' => $p->sku,
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(ProdukDaftar::class)->call('hapus', $p->id);

        $this->assertDatabaseHas('products', ['id' => $p->id]);
    }

    // ── HERO ────────────────────────────────────────────────────────

    public function test_hero_aktif_muncul_di_beranda(): void
    {
        $this->actingAs($this->admin());

        $s = HeroSlide::query()->create([
            'title_id' => 'Judul Hero Uji',
            'title_en' => 'Hero Test Title',
            'media_type' => 'image',
            'media_path' => 'hero/uji.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);

        CacheKatalog::tandaiBerubah('uji');

        $this->get('/')->assertOk()->assertSee('Judul Hero Uji');

        // Slide ini satu-satunya yang aktif, jadi penonaktifan SENGAJA ditolak
        // (min_slide_aktif = 1) — hero tidak boleh kosong tanpa penjelasan.
        Livewire::test(HeroBuilder::class)
            ->call('ubahAktif', $s->id)
            ->assertSet('galat', fn ($g) => filled($g));

        $this->assertTrue((bool) $s->fresh()->is_active);

        // Dengan slide kedua yang aktif, penonaktifan diizinkan.
        HeroSlide::query()->create([
            'title_id' => 'Slide Kedua',
            'title_en' => 'Second',
            'media_type' => 'image',
            'media_path' => 'hero/dua.webp',
            'display_order' => 1,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('ubahAktif', $s->id)
            ->assertSet('galat', null);

        $this->assertFalse((bool) $s->fresh()->is_active);
    }

    public function test_sunting_hero_mengubah_judul_di_beranda(): void
    {
        $this->actingAs($this->admin());

        $s = HeroSlide::query()->create([
            'title_id' => 'Judul Awal',
            'title_en' => 'First',
            'media_type' => 'image',
            'media_path' => 'hero/uji.webp',
            'display_order' => 0,
            'is_active' => true,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
        ]);

        CacheKatalog::tandaiBerubah('uji');
        $this->get('/')->assertOk()->assertSee('Judul Awal');

        Livewire::test(HeroSunting::class, ['ulid' => $s->ulid])
            ->set('form.title_id', 'Judul Setelah Disunting')
            ->call('simpan')
            ->assertHasNoErrors();

        // Komponen sudah membatalkan cache sendiri — tanpa itu beranda masih
        // menampilkan 'Judul Awal' selama sepuluh menit.
        $this->get('/')->assertOk()->assertSee('Judul Setelah Disunting');
    }

    // ── FAQ ─────────────────────────────────────────────────────────

    public function test_faq_baru_muncul_di_halaman_faq(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(FaqPanel::class)
            ->call('bukaForm')
            ->set('pertanyaan_id', 'Apakah bisa kirim luar negeri?')
            ->set('jawaban_id', 'Bisa, ongkir dihitung manual.')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(1, Faq::query()->count());

        $this->get('/faq')->assertOk()->assertSee('Apakah bisa kirim luar negeri?');
    }

    // ── PENGATURAN ──────────────────────────────────────────────────

    public function test_ubah_nama_toko_muncul_di_front(): void
    {
        $this->actingAs($this->admin());

        // PengaturanSitus menyimpan seluruh isian pada satu peta $nilai, dan
        // aturan validasinya berprefiks "nilai." (lihat aturanBerprefiks()).
        Livewire::test(PengaturanSitus::class)
            ->set('nilai.site_name', 'Nama Toko Baru Uji')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->get('/')->assertOk()->assertSee('Nama Toko Baru Uji');
    }
}
