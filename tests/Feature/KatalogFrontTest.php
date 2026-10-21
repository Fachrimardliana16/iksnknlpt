<?php

namespace Tests\Feature;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Livewire\Front\DetailProduk;
use App\Livewire\Front\ProdukDaftar;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6 - katalog dan detail produk (checklist F6-01 sampai F6-08).
 *
 * Yang diuji di sini bukan tampilan, melainkan aturan yang menentukan apa yang
 * dilihat pembeli: saringan mana yang menyala, harga varian mana yang tampil,
 * dan apa yang tercatat saat halaman dibuka.
 */
class KatalogFrontTest extends TestCase
{
    use RefreshDatabase;

    private Category $knalpot;

    private Category $aksesoris;

    protected function setUp(): void
    {
        parent::setUp();

        // Pengaturan wajib ada: nama toko dipakai di judul halaman, nomor
        // WhatsApp dipakai di tombol tanya. Tanpa seeder ini halaman dirender
        // dengan nilai kosong dan yang diuji bukan lagi perilaku sebenarnya.
        $this->seed(\Database\Seeders\SettingSeeder::class);

        Pengaturan::lupakan();

        $this->knalpot = Category::query()->create([
            'name_id' => 'Full System',
            'slug' => 'full-system',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->aksesoris = Category::query()->create([
            'name_id' => 'Aksesoris',
            'slug' => 'aksesoris',
            'display_order' => 2,
            'is_active' => true,
        ]);
    }

    private function produk(
        string $judul,
        Category $kategori,
        float $harga,
        array $ubah = [],
    ): Product {
        return Product::query()->create(array_merge([
            'category_id' => $kategori->id,
            'sku' => 'IK-'.strtoupper(substr(md5($judul), 0, 6)),
            'title_id' => $judul,
            'title_en' => $judul,
            'slug' => \Illuminate\Support\Str::slug($judul),
            'description_id' => 'Deskripsi '.$judul,
            'description_en' => 'Description '.$judul,
            'base_price' => $harga,
            'weight_gram' => 2000,
            'is_published' => true,
            'published_at' => now(),
        ], $ubah));
    }

    private function varian(Product $produk, string $nama, float $selisih, int $stok): ProductVariant
    {
        return ProductVariant::query()->create([
            'product_id' => $produk->id,
            'sku_variant' => $produk->sku.'-'.strtoupper(substr(md5($nama), 0, 4)),
            'variant_name_id' => $nama,
            'variant_name_en' => $nama,
            'price_adjustment' => $selisih,
            'stock_quantity' => $stok,
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    // ── F6-01: daftar, filter, urut, paginasi ────────────────────────

    public function test_filter_kategori_hanya_menampilkan_produk_kategori_itu(): void
    {
        $this->produk('Knalpot Racing Jupiter', $this->knalpot, 2450000);
        $this->produk('Bracket Universal', $this->aksesoris, 95000);

        $daftar = \Livewire\Livewire::test(ProdukDaftar::class)
            ->set('kategoriSlug', 'aksesoris');

        $daftar->assertSee('Bracket Universal');
        $daftar->assertDontSee('Knalpot Racing Jupiter');
    }

    public function test_rentang_harga_menyaring_di_kedua_ujung(): void
    {
        $this->produk('Murah', $this->aksesoris, 75000);
        $this->produk('Sedang', $this->aksesoris, 500000);
        $this->produk('Mahal', $this->aksesoris, 4250000);

        $daftar = \Livewire\Livewire::test(ProdukDaftar::class)
            ->set('hargaMin', '100000')
            ->set('hargaMaks', '1000000');

        $daftar->assertSee('Sedang');
        $daftar->assertDontSee('Murah');
        $daftar->assertDontSee('Mahal');
    }

    public function test_urutan_harga_menaik_dan_menurun(): void
    {
        foreach (['Murah' => 75000, 'Sedang' => 500000, 'Mahal' => 4250000] as $nama => $harga) {
            $this->produk($nama, $this->aksesoris, $harga);
        }

        $naik = \Livewire\Livewire::test(ProdukDaftar::class)->set('urut', 'price_asc');
        $this->assertSame(
            ['Murah', 'Sedang', 'Mahal'],
            $naik->viewData('daftar')->pluck('title_id')->all(),
        );

        $turun = \Livewire\Livewire::test(ProdukDaftar::class)->set('urut', 'price_desc');
        $this->assertSame(
            ['Mahal', 'Sedang', 'Murah'],
            $turun->viewData('daftar')->pluck('title_id')->all(),
        );
    }

    public function test_pencarian_mencocokkan_judul_dan_sku(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->produk('Bracket Universal', $this->aksesoris, 95000);

        $hasil = \Livewire\Livewire::test(ProdukDaftar::class)->set('kata', 'NMAX');
        $hasil->assertSee('Knalpot Full System NMAX');
        $hasil->assertDontSee('Bracket Universal');

        $lewatSku = \Livewire\Livewire::test(ProdukDaftar::class)->set('kata', $produk->sku);
        $lewatSku->assertSee($produk->sku);
    }

    public function test_paginasi_dua_belas_per_halaman(): void
    {
        foreach (range(1, 14) as $i) {
            $this->produk('Produk Nomor '.$i, $this->aksesoris, 100000 * $i);
        }

        $halaman1 = \Livewire\Livewire::test(ProdukDaftar::class);
        $this->assertSame(12, $halaman1->viewData('daftar')->count());
        $this->assertSame(14, $halaman1->viewData('daftar')->total());

        $halaman2 = \Livewire\Livewire::test(ProdukDaftar::class)->call('gotoPage', 2);
        $this->assertSame(2, $halaman2->viewData('daftar')->count());
    }

    public function test_produk_yang_ditarik_tidak_muncul_di_daftar(): void
    {
        $this->produk('Masih Terbit', $this->aksesoris, 95000);
        $this->produk('Sudah Ditarik', $this->aksesoris, 95000, [
            'is_published' => false,
            'published_at' => null,
        ]);

        $daftar = \Livewire\Livewire::test(ProdukDaftar::class);

        $daftar->assertSee('Masih Terbit');
        $daftar->assertDontSee('Sudah Ditarik');
    }

    public function test_ubah_saringan_mengembalikan_ke_halaman_pertama(): void
    {
        foreach (range(1, 14) as $i) {
            $this->produk('Produk Nomor '.$i, $this->aksesoris, 100000 * $i);
        }

        $daftar = \Livewire\Livewire::test(ProdukDaftar::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('kata', 'Nomor 1');

        $this->assertSame(1, $daftar->get('paginators')['page'] ?? 1);
    }

    // ── F6-02: keadaan kosong dan keadaan memuat ─────────────────────

    public function test_hasil_kosong_menjelaskan_sebabnya_bukan_angka_nol(): void
    {
        $this->produk('Knalpot Racing Jupiter', $this->knalpot, 2450000);

        \Livewire\Livewire::test(ProdukDaftar::class)
            ->set('kata', 'zzzz-tidak-ada')
            ->assertSee('Tidak ada produk yang memuat kata')
            ->assertSee('zzzz-tidak-ada');
    }

    public function test_keadaan_kosong_menyebut_jumlah_saringan_yang_menyala(): void
    {
        $this->produk('Knalpot Racing Jupiter', $this->knalpot, 2450000);

        \Livewire\Livewire::test(ProdukDaftar::class)
            ->set('kategoriSlug', 'aksesoris')
            ->set('kata', 'jupiter')
            ->assertSee('2 saringan');
    }

    public function test_kerangka_memuat_dirender_dan_bukan_pemintal_di_tengah(): void
    {
        $this->produk('Knalpot Racing Jupiter', $this->knalpot, 2450000);

        $html = \Livewire\Livewire::test(ProdukDaftar::class)->html();

        $this->assertStringContainsString('wire:loading.delay.long', $html);
        $this->assertStringContainsString('animate-pulse', $html);
        $this->assertStringContainsString(__('site.catalog.loading'), $html);
    }

    // ── F6-03: pemilih varian memperbarui harga dan stok ─────────────

    public function test_memilih_varian_mengubah_harga_dan_stok_yang_tampil(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);

        $standar = $this->varian($produk, 'Stainless', 0, 6);
        $titanium = $this->varian($produk, 'Titanium', 850000, 2);

        $halaman = \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk]);

        $this->assertSame((float) $standar->id, (float) $halaman->get('variantId'));
        $this->assertEqualsWithDelta(4250000, $halaman->viewData('harga'), 0.01);
        $this->assertSame(6, $halaman->viewData('tersedia'));

        $halaman->call('pilihVarian', $titanium->id);

        $this->assertEqualsWithDelta(5100000, $halaman->viewData('harga'), 0.01);
        $this->assertSame(2, $halaman->viewData('tersedia'));
    }

    public function test_memilih_varian_milik_produk_lain_diabaikan(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $varian = $this->varian($produk, 'Stainless', 0, 6);

        $lain = $this->produk('Knalpot Racing Jupiter', $this->knalpot, 2450000);
        $varianLain = $this->varian($lain, 'Carbon', 0, 4);

        \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk])
            ->call('pilihVarian', $varianLain->id)
            ->assertSet('variantId', $varian->id);
    }

    public function test_jumlah_tidak_dapat_melewati_stok_yang_tersedia(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 3);

        \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk])
            ->call('naikkanJumlah')->assertSet('jumlah', 2)
            ->call('naikkanJumlah')->assertSet('jumlah', 3)
            ->call('naikkanJumlah')->assertSet('jumlah', 3)
            ->call('turunkanJumlah')->assertSet('jumlah', 2);
    }

    public function test_varian_habis_tidak_dipilih_secara_bawaan(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);

        $habis = $this->varian($produk, 'Titanium', 850000, 0);
        $ada = $this->varian($produk, 'Stainless', 0, 5);

        $halaman = \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk]);

        $this->assertSame((float) $ada->id, (float) $halaman->get('variantId'));
        $this->assertNotSame((float) $habis->id, (float) $halaman->get('variantId'));
    }

    public function test_stok_yang_ditahan_pesanan_berjalan_tidak_dapat_dijual(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);

        $varian = $this->varian($produk, 'Stainless', 0, 5);
        $varian->update(['reserved_quantity' => 4]);

        $halaman = \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk]);

        $this->assertSame(1, $halaman->viewData('tersedia'));

        $halaman->call('naikkanJumlah')->assertSet('jumlah', 1);
    }

    // ── F6-04: spesifikasi dua bahasa ────────────────────────────────

    public function test_spesifikasi_tampil_dalam_bahasa_yang_aktif(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $produk->update(['spesifikasi_json' => json_encode([
            ['label_id' => 'Material', 'label_en' => 'Material', 'nilai_id' => 'Titanium grade 1', 'nilai_en' => 'Titanium grade 1'],
            ['label_id' => 'Garansi', 'label_en' => 'Warranty', 'nilai_id' => '6 bulan', 'nilai_en' => '6 months'],
        ])]);

        app()->setLocale('id');
        $id = $produk->fresh()->spesifikasi();
        $this->assertSame(['Titanium grade 1', '6 bulan'], array_column($id, 'nilai'));

        app()->setLocale('en');
        $en = $produk->fresh()->spesifikasi();
        $this->assertSame(['Warranty'], [array_column($en, 'label')[1]]);
        $this->assertSame(['6 months'], [array_column($en, 'nilai')[1]]);
    }

    public function test_baris_setengah_terisi_tidak_tampil_sebagai_garis_kosong(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);

        $produk->update(['spesifikasi_json' => json_encode([
            ['label_id' => 'Material', 'nilai_id' => 'Stainless 304'],
            ['label_id' => 'Diameter', 'nilai_id' => ''],
            ['label_id' => '', 'nilai_id' => 'yatim'],
        ])]);

        $baris = $produk->fresh()->spesifikasi();

        $this->assertCount(1, $baris);
        $this->assertSame('Material', $baris[0]['label']);
    }

    public function test_produk_tanpa_spesifikasi_tidak_menampilkan_judul_tabel(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $this->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertDontSee(__('site.product.specification'));
    }

    public function test_halaman_produk_menampilkan_spesifikasi_yang_tersimpan(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $produk->update(['spesifikasi_json' => json_encode([
            ['label_id' => 'Material', 'label_en' => 'Material', 'nilai_id' => 'Titanium grade 1', 'nilai_en' => 'Titanium grade 1'],
        ])]);

        $this->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertSee(__('site.product.specification'))
            ->assertSee('Titanium grade 1');
    }

    public function test_deskripsi_hanya_menampilkan_bahasa_yang_aktif(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $produk->update([
            'description_id' => 'Paragraf Indonesia.',
            'description_en' => 'English paragraph.',
        ]);

        $this->withUnencryptedCookie('iksan_locale', 'en')
            ->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertSee('English paragraph.')
            ->assertDontSee('Paragraf Indonesia.');

        $this->flushSession();

        $this->withUnencryptedCookie('iksan_locale', 'id')
            ->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertSee('Paragraf Indonesia.')
            ->assertDontSee('English paragraph.');
    }

    // ── F6-05: tiga tombol aksi ──────────────────────────────────────

    public function test_tombol_tambah_keranjang_beli_sekarang_dan_whatsapp_ada(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $halaman = $this->get('/produk/'.$produk->slug)->assertOk();

        $halaman->assertSee(__('site.product.add_to_cart'));
        $halaman->assertSee(__('site.product.buy_now'));
        $halaman->assertSee(__('site.product.share_whatsapp'));
        $halaman->assertSee('https://wa.me/628123456789');
    }

    public function test_tombol_whatsapp_hilang_bila_nomor_dikosongkan(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        Setting::query()->where('key', 'whatsapp_number')->update(['value' => '']);
        Pengaturan::lupakan();

        $this->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertDontSee(__('site.product.share_whatsapp'));
    }

    public function test_pesan_whatsapp_menyebut_produk_dan_varian(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Titanium', 850000, 5);

        \Livewire\Livewire::test(DetailProduk::class, ['product' => $produk])
            ->assertSee('Knalpot Full System NMAX', escape: false)
            ->assertSee(rawurlencode('Titanium'), escape: false);
    }

    // ── F6-06: bar aksi lengket di ponsel ────────────────────────────

    public function test_bar_aksi_lengket_memuat_harga_jumlah_dan_tombol(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $html = $this->get('/produk/'.$produk->slug)->assertOk()->getContent();

        $this->assertStringContainsString('lg:hidden', $html);
        $this->assertStringContainsString('safe-area-inset-bottom', $html);
        $this->assertStringContainsString(rupiah(4250000), $html);
        $this->assertStringContainsString(__('site.product.increase_qty'), $html);
        $this->assertStringContainsString(__('site.product.decrease_qty'), $html);
    }

    // ── F6-07: meta tag dan JSON-LD ──────────────────────────────────

    public function test_judul_halaman_memuat_nama_produk_dan_nama_toko(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $this->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertSee('<title>Knalpot Full System NMAX · Iksan Knalpot</title>', escape: false);
    }

    public function test_meta_og_dan_kanonikal_terpasang(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        ProductMedia::query()->create([
            'product_id' => $produk->id,
            'type' => 'image',
            'path_card' => 'products/contoh/card.webp',
            'path_og' => 'products/contoh/og.webp',
            'path_full' => 'products/contoh/full.webp',
            'width' => 1200,
            'height' => 900,
            'is_primary' => true,
            'display_order' => 1,
        ]);

        $html = $this->get('/produk/'.$produk->slug)->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/produk/'.$produk->slug).'">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="product">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Knalpot Full System NMAX">', $html);
        $this->assertStringContainsString('og:image:width" content="1200"', $html);
    }

    public function test_og_image_memakai_turunan_1200x630(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        ProductMedia::query()->create([
            'product_id' => $produk->id,
            'type' => 'image',
            'path_card' => 'products/contoh/card.webp',
            'path_og' => 'products/contoh/og.webp',
            'path_full' => 'products/contoh/full.webp',
            'width' => 1200,
            'height' => 900,
            'is_primary' => true,
            'display_order' => 1,
        ]);

        $html = $this->get('/produk/'.strtolower($produk->slug))->assertOk()->getContent();

        $this->assertStringContainsString('products/contoh/og.webp', $html);
        $this->assertStringNotContainsString('og:image" content="http://localhost/storage/products/contoh/card.webp"', $html);
    }

    public function test_json_ld_produk_sah_dan_memuat_tawaran_harga(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);

        $this->varian($produk, 'Stainless', 0, 5);
        $this->varian($produk, 'Titanium', 850000, 2);

        $html = $this->get('/produk/'.$produk->slug)->assertOk()->getContent();

        $this->assertStringContainsString('application/ld+json', $html);

        preg_match('/<script type="application\/ld\+json">\s*(\{.*?\})\s*<\/script>/s', $html, $cocok);

        $this->assertNotEmpty($cocok, 'Blok JSON-LD tidak ditemukan di halaman.');

        /** @var array<string, mixed> $data */
        $data = json_decode($cocok[1], true);

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Knalpot Full System NMAX', $data['name']);
        $this->assertSame($produk->sku, $data['sku']);
        $this->assertSame('IDR', $data['offers']['priceCurrency']);
        $this->assertSame('4250000.00', $data['offers']['lowPrice']);
        $this->assertSame('5100000.00', $data['offers']['highPrice']);
        $this->assertSame(2, $data['offers']['offerCount']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function test_json_ld_tidak_memuat_tawaran_bila_seluruh_varian_habis(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 0);

        $html = $this->get('/produk/'.$produk->slug)->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">\s*(\{.*?\})\s*<\/script>/s', $html, $cocok);
        $data = json_decode($cocok[1], true);

        $this->assertArrayNotHasKey('offers', $data);
    }

    // ── F6-08: penghitung tampilan tidak menulis berlebihan ──────────

    public function test_delapan_kali_buka_menambah_delapan_bukan_lebih(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        $awal = (int) $produk->view_count;

        // Kunjungan TAMU. Pencatatan tampilan terjadi di mount() komponen, dan
        // sejak cache halaman publik dipasang, kunjungan tamu kedua dan
        // seterusnya dilayani dari cache — tidak ada mount, tidak ada
        // penambahan. Itu memang yang diinginkan: cache halaman mengalahkan
        // penghitung tampilan, dan angkanya tidak boleh "dibayar" dengan
        // merender ulang seluruh halaman.
        $this->withSession([])->get('/produk/'.$produk->slug)->assertOk();
        $this->assertSame($awal + 1, (int) $produk->fresh()->view_count);

        // Kunjungan yang benar-benar merender (pengunjung yang sudah masuk,
        // yang memang tidak dilayani cache) menambah satu per kunjungan.
        $user = \App\Models\User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'viewcount@contoh.test',
            'password' => 'rahasia123',
            'role' => 'customer',
        ]);

        $this->actingAs($user);

        foreach (range(1, 7) as $i) {
            $this->get('/produk/'.$produk->slug)->assertOk();
        }

        $this->assertSame($awal + 8, (int) $produk->fresh()->view_count);
    }

    public function test_kenaikan_tampilan_memakai_satu_kueri_update(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->get('/produk/'.$produk->slug)->assertOk();

        $update = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->filter(fn (array $q) => str_starts_with(strtolower($q['query']), 'update'))
            ->filter(fn (array $q) => str_contains($q['query'], 'view_count'));

        $this->assertCount(1, $update, 'Penghitung tampilan harus satu kueri UPDATE, bukan baca-lalu-tulis.');

        \Illuminate\Support\Facades\DB::disableQueryLog();
    }

    public function test_daftar_produk_tidak_menambah_penghitung_tampilan(): void
    {
        $produk = $this->produk('Knalpot Full System NMAX', $this->knalpot, 4250000);
        $this->varian($produk, 'Stainless', 0, 5);

        // view_count tidak ada di $fillable — memang tidak boleh diisi dari
        // formulir. Jadi nilai awalnya disetel lewat kueri langsung.
        Product::query()->whereKey($produk->id)->update(['view_count' => 40]);

        $this->get('/produk')->assertOk();

        // SKU sengaja TIDAK lagi tampil di kartu katalog: nomor bagian tidak
        // membantu pembeli memilih dari daftar, dan barisnya menambah tinggi
        // kartu sehingga proporsinya rusak (kartu kurus dan memanjang di ponsel).
        // Yang perlu dibuktikan di sini adalah kartu menampilkan PRODUK yang
        // benar — memakai judul, bukan nomor bagian.
        \Livewire\Livewire::test(ProdukDaftar::class)->assertSee($produk->judul());

        $this->assertSame(40, (int) $produk->fresh()->view_count);
    }
}
