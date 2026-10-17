<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rute sisi depan harus mengembalikan 200. Ini yang menangkap kesalahan view
 * sebelum sempat terlihat di peramban.
 */
class RuteFrontTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string}>
     */
    public static function rute(): array
    {
        return [
            ['/'],
            ['/produk'],
            ['/tentang'],
            ['/kontak'],
            ['/keranjang'],
            ['/lacak'],
            ['/halaman/kebijakan-privasi'],
            ['/halaman/syarat-ketentuan'],
        ];
    }

    /**
     * @dataProvider rute
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rute')]
    public function test_rute_front_mengembalikan_200(string $url): void
    {
        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_halaman_tidak_dikenal_mengembalikan_404_dengan_gaya_sendiri(): void
    {
        $this->get('/halaman-tidak-ada')
            ->assertNotFound()
            ->assertSee('404');
    }

    public function test_detail_produk_mengembalikan_200_dan_menampilkan_sku(): void
    {
        $produk = Product::factory()->create();
        ProductVariant::factory()->create([
            'product_id' => $produk->id,
            'stock_quantity' => 5,
        ]);

        $this->get('/produk/'.$produk->slug)
            ->assertOk()
            ->assertSee($produk->sku);
    }

    public function test_produk_yang_disembunyikan_mengembalikan_404(): void
    {
        $produk = Product::factory()->disembunyikan()->create();

        $this->get('/produk/'.$produk->slug)->assertNotFound();
    }

    public function test_home_menampilkan_tombol_lihat_semua_produk(): void
    {
        $this->get('/')->assertOk()->assertSee('Lihat Semua Produk');
    }

    public function test_bahasa_mengikuti_cookie(): void
    {
        $this->withUnencryptedCookie(\App\Http\Middleware\SetLocale::COOKIE, 'en')
            ->get('/')
            ->assertOk()
            ->assertSee('View All Products')
            ->assertDontSee('Lihat Semua Produk');
    }

    public function test_url_lang_menulis_cookie_dan_mengarahkan_kembali(): void
    {
        $this->get('/lang/en')
            ->assertRedirect()
            ->assertPlainCookie(\App\Http\Middleware\SetLocale::COOKIE, 'en');
    }

    public function test_bahasa_tidak_didukung_dikembalikan_ke_bawaan(): void
    {
        $this->get('/lang/xx')
            ->assertRedirect()
            ->assertPlainCookie(\App\Http\Middleware\SetLocale::COOKIE, config('app.locale'));
    }
}
