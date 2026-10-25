<?php

namespace Tests\Feature;

use App\Livewire\Front\KeranjangHalaman;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * IDOR pada keranjang tamu (audit keamanan, temuan KERANJANG-01).
 *
 * `KeranjangHalaman::ubahJumlah()` dan `hapusItem()` menerima `itemId` dari
 * sisi klien dan mencarinya lewat `cariItem()`. Sebelum diperbaiki, `cariItem()`
 * memakai `whereKey($itemId)` tanpa membatasi pada keranjang pemanggil, sehingga
 * baris carts milik sesi lain (atau akun lain) dapat diubah dan dihapus.
 *
 * Yang dibuktikan di sini sesudah perbaikan: permintaan dari sesi penyerang
 * TIDAK lagi menyentuh baris milik sesi lain, sedangkan baris milik sendiri
 * tetap dapat diubah dan dihapus seperti biasa.
 */
class KeranjangIdorTest extends TestCase
{
    use RefreshDatabase;

    private function produk(): Product
    {
        $kategori = Category::query()->create([
            'slug' => 'kategori-idor',
            'name_id' => 'Kategori',
            'name_en' => 'Category',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'IDOR-1',
            'slug' => 'produk-idor',
            'title_id' => 'Produk IDOR',
            'title_en' => 'IDOR Product',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => 500000,
            'is_published' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $produk->id,
            'sku_variant' => 'IDOR-1-V1',
            'variant_name_id' => 'Ukuran',
            'variant_name_en' => 'Size',
            'price_adjustment' => 0,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        return $produk;
    }

    /** Baris keranjang milik "korban", di luar sesi pemanggil. */
    private function keranjangKorban(): CartItem
    {
        $produk = $this->produk();
        $varian = $produk->variants()->firstOrFail();

        $keranjang = Cart::query()->create(['session_id' => 'sesi-korban']);

        return $keranjang->items()->create([
            'product_id' => $produk->id,
            'product_variant_id' => $varian->id,
            'quantity' => 3,
        ]);
    }

    public function test_item_keranjang_sesi_lain_tidak_dapat_dihapus(): void
    {
        $item = $this->keranjangKorban();

        $this->assertSame(1, CartItem::query()->count());

        // Sesi penyerang: keranjang kosong, tetapi ia tahu (atau menebak) ID
        // baris milik korban. ID yang dikirim lewat parameter metode Livewire,
        // sama seperti tombol hapus pada halaman keranjang.
        Livewire::test(KeranjangHalaman::class, ['keranjang' => app(Keranjang::class)])
            ->call('hapusItem', $item->id, app(Keranjang::class));

        $this->assertSame(1, CartItem::query()->count(), 'Baris keranjang milik sesi lain harus tetap ada.');
        $this->assertSame(3, (int) $item->fresh()->quantity, 'Isi baris milik sesi lain tidak boleh berubah.');
    }

    public function test_item_keranjang_sesi_lain_tidak_dapat_diubah_jumlahnya(): void
    {
        $item = $this->keranjangKorban();

        Livewire::test(KeranjangHalaman::class, ['keranjang' => app(Keranjang::class)])
            ->call('ubahJumlah', $item->id, 1, app(Keranjang::class));

        $this->assertSame(3, (int) $item->fresh()->quantity, 'Jumlah baris milik sesi lain tidak boleh berubah.');
    }

    public function test_item_milik_sendiri_masih_dapat_diubah(): void
    {
        $produk = $this->produk();
        $varian = $produk->variants()->firstOrFail();
        $keranjang = app(Keranjang::class);
        $keranjang->tambah($varian, 2);

        $item = $keranjang->items()->firstOrFail();

        Livewire::test(KeranjangHalaman::class, ['keranjang' => $keranjang])
            ->call('ubahJumlah', $item->id, 4, $keranjang);

        $this->assertSame(4, (int) $item->fresh()->quantity);
    }

    public function test_item_milik_sendiri_masih_dapat_dihapus(): void
    {
        $produk = $this->produk();
        $varian = $produk->variants()->firstOrFail();
        $keranjang = app(Keranjang::class);
        $keranjang->tambah($varian, 2);

        $item = $keranjang->items()->firstOrFail();

        Livewire::test(KeranjangHalaman::class, ['keranjang' => $keranjang])
            ->call('hapusItem', $item->id, $keranjang);

        $this->assertSame(0, CartItem::query()->count(), 'Baris milik sendiri tetap dapat dihapus.');
    }
}
