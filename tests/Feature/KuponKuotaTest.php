<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\TipeKupon;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PesananService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kuota kupon saat checkout (audit keamanan, temuan KUPON-01).
 *
 * Yang diuji di sini adalah bagian yang dapat dipastikan TANPA menjalankan dua
 * proses sungguhan: kuota kupon diperiksa ULANG saat pesanan dibuat, bukan
 * hanya saat kupon ditempelkan ke keranjang. Tanpa pemeriksaan itu, kupon
 * kuota 1 yang sudah terpakai tetap menghasilkan pesanan berdiskon.
 *
 * Yang TIDAK diuji di sini: dua checkout yang berjalan benar-benar bersamaan.
 * Pada SQLite penulisan diserialisasi, dan besaran risiko sebenarnya hanya
 * dapat ditetapkan dengan uji berbarengan di lingkungan terkendali.
 */
class KuponKuotaTest extends TestCase
{
    use RefreshDatabase;

    private function siapkan(int $kuota, int $terpakai): Coupon
    {
        $kategori = Category::query()->firstOrCreate(
            ['slug' => 'k-kupon-kuota'],
            ['name_id' => 'K', 'name_en' => 'K', 'is_active' => true],
        );

        $produk = Product::query()->firstOrCreate(
            ['sku' => 'K-1'],
            [
                'category_id' => $kategori->id,
                'slug' => 'p-k',
                'title_id' => 'P',
                'title_en' => 'P',
                'description_id' => 'D',
                'description_en' => 'D',
                'base_price' => 1000000,
                'is_published' => true,
            ],
        );

        $varian = ProductVariant::query()->firstOrCreate(
            ['sku_variant' => 'K-1-V1'],
            [
                'product_id' => $produk->id,
                'variant_name_id' => 'V',
                'variant_name_en' => 'V',
                'price_adjustment' => 0,
                'stock_quantity' => 50,
                'is_active' => true,
            ],
        );

        $kupon = Coupon::query()->firstOrCreate(
            ['code' => 'KUOTA1'],
            [
                'type' => TipeKupon::Nominal->value,
                'value' => 100000,
                'quota' => $kuota,
                'used_count' => $terpakai,
                'is_active' => true,
            ],
        );

        $keranjang = Cart::query()->create([
            'session_id' => session()->getId(),
            'coupon_id' => $kupon->id,
        ]);

        $keranjang->items()->create([
            'product_id' => $produk->id,
            'product_variant_id' => $varian->id,
            'quantity' => 1,
        ]);

        return $kupon;
    }

    public function test_kuota_habis_menolak_diskon_walau_kupon_masih_menempel_di_keranjang(): void
    {
        $kupon = $this->siapkan(kuota: 1, terpakai: 1);

        $keranjang = app(\App\Services\Keranjang::class);

        // Kupon kuota 1 yang sudah terpakai tetap menempel di keranjang.
        // Pesanan TIDAK boleh dibuat dengan diskonnya.
        $pesanan = app(PesananService::class)->buatDariKeranjang(
            keranjang: $keranjang,
            data: [
                'nama' => 'Uji',
                'email' => 'uji@contoh.test',
                'telepon' => '081234567890',
                'alamat1' => 'Jalan Uji 1',
                'alamat2' => '',
                'kota' => 'Jakarta',
                'provinsi' => 'DKI',
                'kodePos' => '12345',
                'kurir' => 'JNE',
                'negara' => 'ID',
            ],
            metode: MetodePembayaran::Manual,
            user: null,
        );

        $this->assertSame(1, $pesanan->items()->count());
        $this->assertNull($pesanan->coupon_id, 'Kupon yang kuotanya habis tidak boleh dipakai.');
        $this->assertSame(0.0, (float) $pesanan->discount_amount, 'Diskon kupon habis tidak boleh masuk pesanan.');
        $this->assertSame(1, $kupon->fresh()->used_count, 'used_count tidak boleh bertambah melewati kuota.');
    }

    public function test_kupon_kuota_cukup_tetap_menghasilkan_diskon(): void
    {
        $kupon = $this->siapkan(kuota: 5, terpakai: 0);

        $keranjang = app(\App\Services\Keranjang::class);

        $pesanan = app(PesananService::class)->buatDariKeranjang(
            keranjang: $keranjang,
            data: [
                'nama' => 'Uji',
                'email' => 'uji@contoh.test',
                'telepon' => '081234567890',
                'alamat1' => 'Jalan Uji 1',
                'alamat2' => '',
                'kota' => 'Jakarta',
                'provinsi' => 'DKI',
                'kodePos' => '12345',
                'kurir' => 'JNE',
                'negara' => 'ID',
            ],
            metode: MetodePembayaran::Manual,
            user: null,
        );

        $this->assertSame(1, $pesanan->items()->count());
        $this->assertSame($kupon->id, $pesanan->coupon_id);
        $this->assertSame(1, $kupon->fresh()->used_count);
    }
}
