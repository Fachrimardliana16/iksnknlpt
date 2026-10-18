<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CacheKatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji cache katalog dan invalidasinya (checklist F3-14, F11-01).
 *
 * Yang diperiksa bukan "apakah cache menyimpan", melainkan apakah perubahan
 * dari panel admin benar-benar membuat halaman menampilkan data baru. Cache
 * yang cepat tetapi menyajikan produk yang sudah ditarik lebih buruk daripada
 * tidak ada cache sama sekali.
 */
class CacheKatalogTest extends TestCase
{
    use RefreshDatabase;

    private function hitungKueri(callable $aksi): int
    {
        $jumlah = 0;

        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $aksi();

        return $jumlah;
    }

    public function test_permintaan_kedua_memakai_cache(): void
    {
        Product::factory()->count(3)->create([
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
        ]);

        $saringan = ['urut' => 'newest'];

        // Panggilan pertama mengisi cache.
        CacheKatalog::ingat('katalog.daftar', $saringan, 300, fn () => Product::query()->terbit()->count());
        Cache::flush();

        CacheKatalog::ingat('katalog.daftar', $saringan, 300, fn () => Product::query()->terbit()->count());

        $kueri = $this->hitungKueri(function () use ($saringan): void {
            CacheKatalog::ingat('katalog.daftar', $saringan, 300, function () {
                // Kueri ini TIDAK boleh dijalankan: cache sudah terisi.
                return Product::query()->terbit()->get();
            });
        });

        $this->assertSame(0, $kueri, 'kueri tidak boleh dijalankan saat cache terisi');
    }

    public function test_menandai_katalog_berubah_membuat_cache_lama_tidak_cocok(): void
    {
        $saringan = ['urut' => 'newest'];

        $sebelum = CacheKatalog::kunci('katalog.daftar', $saringan);

        CacheKatalog::tandaiBerubah('uji');

        $sesudah = CacheKatalog::kunci('katalog.daftar', $saringan);

        $this->assertNotSame($sebelum, $sesudah, 'kunci harus berubah setelah ditandai');

        // Isi lama masih ada di penyimpan cache, tetapi tidak akan pernah
        // terpakai lagi karena kuncinya berbeda.
        $this->assertSame(CacheKatalog::versi(), CacheKatalog::versi());
    }

    public function test_produk_yang_ditarik_tidak_muncul_di_daftar_walau_ada_cache(): void
    {
        $produk = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'title_id' => 'Produk Akan Ditarik',
        ]);

        ProductVariant::factory()->create(['product_id' => $produk->id]);

        $saringan = ['urut' => 'newest'];

        // Isi cache seperti halaman katalog.
        $ambil = fn () => CacheKatalog::ingat(
            'katalog.daftar',
            $saringan,
            300,
            fn () => Product::query()->terbit()->pluck('title_id')->all(),
        );

        $this->assertContains('Produk Akan Ditarik', $ambil());

        // Tarik produknya, seperti yang dilakukan panel admin.
        $produk->update(['is_published' => false]);
        CacheKatalog::tandaiProduk($produk, 'uji tarik');

        $this->assertNotContains(
            'Produk Akan Ditarik',
            $ambil(),
            'produk yang sudah ditarik tidak boleh muncul kembali dari cache',
        );
    }

    public function test_perubahan_dari_panel_admin_menandai_cache(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'super_admin']));

        $versiAwal = CacheKatalog::versi();

        $produk = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)
            ->call('ubahTerbitSatu', $produk->id);

        $this->assertGreaterThan(
            $versiAwal,
            CacheKatalog::versi(),
            'menerbitkan produk harus menandai katalog berubah',
        );
    }

    public function test_cache_yang_bermasalah_tidak_menjatuhkan_halaman(): void
    {
        // Penyimpan cache yang melempar tidak boleh membuat halaman katalog
        // gagal dirender; kueri dijalankan langsung.
        Cache::shouldReceive('get')->andThrow(new \RuntimeException('penyimpan rusak'));

        $hasil = CacheKatalog::ingat('katalog.daftar', ['uji' => 1], 300, fn () => 'langsung');

        $this->assertSame('langsung', $hasil);
    }
}
