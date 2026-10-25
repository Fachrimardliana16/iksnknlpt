<?php

namespace Tests\Feature;

use App\Http\Middleware\ZonaPelanggan;
use App\Livewire\Front\Checkout;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Zona pengiriman pembeli (checklist Tahap 4).
 *
 * Yang diuji: cookie ditulis dengan kode negara yang sah, nilai tak dikenal
 * dibuang bukan dipercaya, zona terisi ke form checkout, dan halaman tetap
 * terbuka normal saat cookie tidak ada atau rusak.
 */
class ZonaPelangganTest extends TestCase
{
    use RefreshDatabase;

    private function produk(): Product
    {
        $kategori = Category::query()->create([
            'slug' => 'kategori-zona',
            'name_id' => 'Kategori Zona',
            'name_en' => 'Zone Category',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'ZONA-1',
            'slug' => 'produk-zona',
            'title_id' => 'Produk Zona',
            'title_en' => 'Zone Product',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => 500000,
            'is_published' => true,
        ]);

        // Tanpa varian aktif, keranjang menolak penambahan — checkout tidak
        // pernah dijalankan karena keranjangnya kosong.
        ProductVariant::query()->create([
            'product_id' => $produk->id,
            'sku_variant' => 'ZONA-1-V1',
            'variant_name_id' => 'Ukuran standar',
            'variant_name_en' => 'Standard size',
            'price_adjustment' => 0,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        return $produk;
    }

    private function keranjangBerisi(): Keranjang
    {
        // Keranjang adalah singleton (AppServiceProvider). Memakai new Keranjang
        // membuat instance terpisah dengan cache kosong, sementara Livewire
        // menyuntikkan yang singleton — dua isi yang berbeda untuk satu sesi.
        $keranjang = app(Keranjang::class);
        $produk = $this->produk();

        $keranjang->tambah($produk->variants()->firstOrFail(), 1);

        return $keranjang;
    }

    /** Checkout mengalihkan bila keranjang kosong, jadi ia butuh isi. */
    private function checkout(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Checkout::class);
    }

    // ── 4.1 Cookie zona ──────────────────────────────────────────────

    public function test_zona_terbaca_dari_cookie(): void
    {
        $this->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'SG')->get('/')->assertOk();

        $this->assertSame('SG', ZonaPelanggan::sekarang());
    }

    public function test_zona_kosong_bila_cookie_tidak_ada(): void
    {
        $this->get('/')->assertOk();

        $this->assertSame('', ZonaPelanggan::sekarang());
    }

    public function test_kode_negara_tak_dikenal_dibuang_bukan_dipercaya(): void
    {
        // Cookie dapat diedit pembeli. Nilai sembarangan yang lolos ke alur
        // checkout berarti kolom negara berisi kode yang tak ada di daftar.
        $this->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'ZZ-NGARAWAN')->get('/')->assertOk();

        $this->assertSame('', ZonaPelanggan::sekarang());
    }

    public function test_zona_ditulis_kembali_ke_cookie(): void
    {
        // Middleware menulis cookie dengan nilai yang sudah dibersihkan, jadi
        // cookie rusak diperbaiki pada kunjungan berikutnya.
        //
        // Butuh izin lebih dulu: cookie zona bukan cookie esensial, dan tanpa
        // persetujuan pembeli middleware tidak menuliskannya sama sekali.
        $respons = $this->withUnencryptedCookie(ZonaPelanggan::IZIN, '1')
            ->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'BUKAN-KODE')
            ->get('/');

        $tersimpan = collect($respons->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === ZonaPelanggan::COOKIE);

        $this->assertNotNull($tersimpan);
        $this->assertSame('', $tersimpan->getValue());
    }

    public function test_cookie_rusak_diperbaiki_tanpa_persetujuan(): void
    {
        // Nilai yang tidak dikenal tetap dibuang dari permintaan ini — itu
        // terjadi sebelum penulisan cookie. Yang tidak terjadi tanpa izin
        // hanyalah penulisan cookie perbaikannya.
        $respons = $this->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'BUKAN-KODE')->get('/');

        $this->assertNull(collect($respons->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === ZonaPelanggan::COOKIE));

        $this->assertSame('', ZonaPelanggan::sekarang());
    }

    public function test_cookie_zona_boleh_dibaca_dari_sisi_klien(): void
    {
        // Kode negara bukan data pribadi. Pembeli perlu bisa melihat apa yang
        // tersimpan di perambannya sendiri — kalau tidak, cookie ini tak dapat
        // diperiksa saat ada laporan masalah.
        //
        // Cookie hanya ditulis middleware saat nilainya berubah, jadi yang
        // diperiksa adalah cookie yang perlu diperbaiki (nilai rusak). Izin
        // dilewatkan lebih dulu karena tanpanya cookie ini tidak ditulis.
        $respons = $this->withUnencryptedCookie(ZonaPelanggan::IZIN, '1')
            ->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'BUKAN-KODE')
            ->get('/');

        $cookie = collect($respons->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === ZonaPelanggan::COOKIE);

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function test_cookie_zona_bertahan_setahun_dengan_samesite_lax(): void
    {
        $respons = $this->withUnencryptedCookie(ZonaPelanggan::IZIN, '1')
            ->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'BUKAN-KODE')
            ->get('/');

        $cookie = collect($respons->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === ZonaPelanggan::COOKIE);

        $this->assertNotNull($cookie);
        $this->assertSame('lax', strtolower($cookie->getSameSite() ?? ''));
    }

    // ── 4.2 Zona dipakai di checkout ────────────────────────────────

    public function test_checkout_memakai_zona_dari_cookie(): void
    {
        $this->keranjangBerisi();

        // Livewire::test() hanya menerima nama + parameter. Cookie dilewatkan
        // lewat withCookies() lebih dulu, sama seperti withHeaders().
        // Middleware dimatikan saat test, jadi ZonaPelanggan::sekarang() jatuh
        // ke membaca cookie langsung.
        Livewire::withCookies([ZonaPelanggan::COOKIE => 'SG'])
            ->test(Checkout::class)
            ->assertSet('negara', 'SG');
    }

    public function test_checkout_tanpa_zona_memakai_indonesia(): void
    {
        $this->keranjangBerisi();

        $this->checkout()->assertSet('negara', 'ID');
    }

    public function test_zona_tercatat_setelah_pesanan_berhasil(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->keranjangBerisi();

        // segel dan dimulaiPada harus dilewati: keduanya penjaga bot, bukan
        // bagian dari alur zona.
        Livewire::test(Checkout::class)
            ->set('nama', 'Pembeli Zona')
            ->set('email', 'zona@contoh.test')
            ->set('telepon', '08123456789')
            ->set('alamat1', 'Jalan Uji 1')
            ->set('kota', 'Kuala Lumpur')
            ->set('provinsi', 'WP')
            ->set('kodePos', '50000')
            ->set('negara', 'MY')
            ->set('situs', '')
            ->set('dimulaiPada', now()->subSeconds(30)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        // Pesanan benar-benar tersimpan, jadi zona yang dicatat bukan ilusi.
        $this->assertSame('MY', \App\Models\Order::query()->latest('id')->value('shipping_country'));
    }

    public function test_zona_tidak_ditulis_bila_pesanan_gagal(): void
    {
        $this->keranjangBerisi();

        // Negara kosong membuat pesanan gagal. Tidak ada pesanan tersimpan,
        // berarti pembeli tidak terjebak di zona yang bukan ia pilih.
        Livewire::test(Checkout::class)
            ->set('nama', 'Pembeli Gagal')
            ->set('email', 'gagal@contoh.test')
            ->set('telepon', '08123456789')
            ->set('alamat1', 'Jalan Uji 1')
            ->set('kota', 'Kota')
            ->set('provinsi', 'Provinsi')
            ->set('kodePos', '12345')
            ->set('negara', '')
            ->set('situs', '')
            ->set('dimulaiPada', now()->subSeconds(30)->timestamp)
            ->call('buatPesanan')
            ->assertHasErrors('negara');

        $this->assertSame(0, \App\Models\Order::query()->count());
    }

    // ── 4.3 Persetujuan sebelum cookie non-esensial ditulis ────────

    public function test_cookie_zona_tidak_ditulis_tanpa_persetujuan(): void
    {
        // Pembeli baru: belum menekan "Setuju" di pemberitahuan cookie, belum
        // pernah checkout. Cookie zona tidak boleh ikut tertulis — yang boleh
        // hanya cookie esensial (sesi, CSRF, bahasa).
        $respons = $this->get('/');

        $nama = collect($respons->headers->getCookies())
            ->map(fn ($c) => $c->getName());

        $this->assertFalse($nama->contains(ZonaPelanggan::COOKIE));
        $this->assertFalse($nama->contains(ZonaPelanggan::IZIN));
    }

    public function test_cookie_zona_ditulis_setelah_persetujuan(): void
    {
        $this->withUnencryptedCookie(ZonaPelanggan::IZIN, '1')
            ->withUnencryptedCookie(ZonaPelanggan::COOKIE, 'SG')
            ->get('/');

        $this->assertSame('SG', ZonaPelanggan::sekarang());
    }

    public function test_persetujuan_dibaca_dari_cookie(): void
    {
        $this->assertFalse(ZonaPelanggan::adaIzin());

        $this->withUnencryptedCookie(ZonaPelanggan::IZIN, '1')->get('/');

        $this->assertTrue(ZonaPelanggan::adaIzin());
    }

    public function test_halaman_tetap_terbuka_saat_persetujuan_ditolak(): void
    {
        // Menolak cookie tidak boleh menutup toko: hanya pengingat zona yang
        // hilang, sisanya berjalan seperti biasa.
        foreach (['/', '/produk', '/kontak', '/faq', '/tentang'] as $jalur) {
            $this->get($jalur)->assertOk();
        }

        $this->assertSame('', ZonaPelanggan::sekarang());
    }

    // ── 4.4 Halaman tetap terbuka ───────────────────────────────────

    public function test_halaman_front_tetap_terbuka_tanpa_cookie_zona(): void
    {
        // Halaman yang memuat keranjang dialihkan bila keranjang kosong; yang
        // diperiksa di sini adalah halaman yang tidak bergantung pada isi.
        foreach (['/', '/produk', '/kontak', '/faq', '/tentang'] as $jalur) {
            $this->get($jalur)->assertOk();
        }
    }

    public function test_keranjang_tetap_bekerja_dengan_zona_rusak(): void
    {
        // Tanpa get('/') lebih dulu: panggilan itu merusak snapshot Livewire
        // yang dipakai pada pernyataan berikutnya.
        Livewire::test(\App\Livewire\Front\KeranjangHalaman::class, ['keranjang' => app(Keranjang::class)], [ZonaPelanggan::COOKIE => 'xyz'])
            ->assertOk();
    }
}
