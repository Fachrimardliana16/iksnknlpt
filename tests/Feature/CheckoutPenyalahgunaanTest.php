<?php

namespace Tests\Feature;

use App\Enums\StatusPesanan;
use App\Livewire\Front\Checkout;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use App\Services\PesananService;
use App\Support\Internasional;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checkout: pengiriman luar negeri, dan ketahanan terhadap penyalahgunaan.
 *
 * Dua bagian yang dulu tidak teruji di situs ini. Keduanya mudah rusak tanpa
 * ketahuan — kolom negara yang hilang, segel yang tidak pernah bersih — dan
 * yang menanggung bukan admin, melainkan pembeli yang pesanannya hilang.
 */
class CheckoutPenyalahgunaanTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $varian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingSeeder::class);
        Pengaturan::lupakan();

        $kategori = Category::query()->create([
            'name_id' => 'Full System',
            'slug' => 'full-system',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'IK-FS-9001',
            'title_id' => 'Knalpot Full System NMAX',
            'title_en' => 'Knalpot Full System NMAX',
            'slug' => 'knalpot-full-system-nmax',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => 4250000,
            'weight_gram' => 4200,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->varian = ProductVariant::query()->create([
            'product_id' => $produk->id,
            'sku_variant' => 'IK-VAR-9001',
            'variant_name_id' => 'Stainless',
            'variant_name_en' => 'Stainless',
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    /**
     * Isi keranjang lewat layanan sungguhan, bukan membuat baris langsung.
     *
     * Livewire::test() pada Checkout dengan keranjang KOSONG akan dialihkan ke
     * halaman keranjang oleh mount() — snapshotnya tidak lengkap dan percobaan
     * gagal dengan "Invalid Livewire snapshot structure", galat yang tidak
     * menunjuk ke penyebab sebenarnya.
     */
    private function isiKeranjang(int $jumlah = 2): Keranjang
    {
        $keranjang = app(Keranjang::class);
        $keranjang->tambah($this->varian, $jumlah);

        return $keranjang;
    }

    private function dataCheckout(array $ubah = []): array
    {
        return array_merge([
            'nama' => 'Pembeli Uji',
            'email' => 'pembeli@contoh.test',
            'telepon' => '+6591234567',
            'alamat1' => '128 Orchard Road, #05-01',
            'alamat2' => '',
            'kota' => 'Singapore',
            'provinsi' => 'Singapore',
            'kodePos' => '238823',
            'kurir' => 'DHL',
            'negara' => 'SG',
            'mataUang' => 'USD',
            'metode' => 'manual',
        ], $ubah);
    }

    // ── Pengiriman luar negeri ───────────────────────────────────────

    public function test_pesanan_luar_negeri_dicatat_dengan_negara_dan_mata_uang(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame('SG', $order->shipping_country);
        $this->assertSame('USD', $order->shipping_currency);
        $this->assertTrue((bool) $order->shipping_is_intl);

        // Negara juga tersimpan di snapshot alamat, karena snapshot itulah yang
        // ditampilkan di resi dan email — kolom sendiri tidak cukup.
        $this->assertSame('SG', $order->alamat()['country']);
    }

    public function test_negara_tidak_dikenal_ditolak(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['negara' => 'ZZ', 'mataUang' => 'USD']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasErrors(['negara']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_mata_uang_hanya_dari_dua_pilihan(): void
    {
        $this->isiKeranjang(1);

        // updatedMataUang membetulkan nilai tak dikenal menjadi IDR, jadi
        // "EUR" benar-benar tersimpan sebagai IDR — bukan diteruskan ke
        // PesananService dan bukan membuat pesanan dengan mata uang aneh.
        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['negara' => 'SG', 'mataUang' => 'EUR']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertContains($order->shipping_currency, ['IDR', 'USD']);
        $this->assertNotSame('EUR', $order->shipping_currency);
    }

    public function test_memilih_negara_luar_negeri_mengusulkan_usd(): void
    {
        $this->isiKeranjang(1);

        $component = Livewire::test(Checkout::class);
        $component->set('negara', 'ID');
        $component->set('negara', 'MY');

        $this->assertSame('USD', $component->get('mataUang'));

        // Kembali ke Indonesia: mata uang paksa Rupiah. Pembeli di Yogyakarta
        // tidak boleh ditawari harga dolar.
        $component->set('negara', 'ID');
        $this->assertSame('IDR', $component->get('mataUang'));
    }

    public function test_pembeli_luar_negeri_boleh_memilih_idr(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['negara' => 'SG', 'mataUang' => 'IDR']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $this->assertSame('IDR', Order::query()->firstOrFail()->shipping_currency);
    }

    // ── Ketahanan terhadap penyalahgunaan ────────────────────────────

    /** Isi segel seperti yang dilakukan peramban sungguhan. */
    private function checkoutDenganSegel(array $data): \Livewire\Features\SupportTesting\Testable
    {
        $halaman = Livewire::test(Checkout::class);
        $halaman->set($data)->set('dimulaiPada', now()->subSeconds(5)->timestamp);

        // Segel sudah digenerate oleh mount(); yang perlu dilakukan tes hanyalah
        // MEMAINKANNYA — mensimulasikan pengiriman dari peramban nyata.
        $halaman->call('buatPesanan');

        return $halaman;
    }

    public function test_satu_muat_halaman_hanya_satu_pesanan(): void
    {
        $this->isiKeranjang(2);

        $pertama = $this->checkoutDenganSegel($this->dataCheckout());

        // Percobaan kedua pada komponen yang sama, muatan identik: harus
        // ditolak. Inilah yang membatalkan kekuatan bot — mengirim beruntun
        // dari satu muatan.
        $pertama->call('buatPesanan')->assertSet('galat', 'Formulir ini sudah dipakai. Muat ulang halaman checkout.');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_jebakan_bot_ditolak_dan_tidak_membuat_pesanan(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['situs' => 'http://spam.example']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertSet('galat', 'Formulir dikirim terlalu cepat. Muat ulang halaman lalu coba lagi.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_formulir_terlalu_cepat_ditolak(): void
    {
        $this->isiKeranjang(1);

        // Tanpa mundur dimulaiPada: tampak dikirim pada detik yang sama.
        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->timestamp)
            ->call('buatPesanan')
            ->assertSet('galat', 'Formulir dikirim terlalu cepat. Muat ulang halaman lalu coba lagi.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_alamat_beraksara_non_latin_ditolak(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['negara' => 'SG', 'alamat1' => 'ул. Ленина 12, Москва']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertSet('galat', 'Alamat dalam huruf Latin saja, ya.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_alamat_kaya_tautan_ditolak(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout([
                'negara' => 'SG',
                'alamat1' => 'http://spam.example http://more.example Jalan palsu 9',
            ]))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertSet('galat', 'Alamat tidak perlu memuat tautan.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_nomor_telepon_patokan_ditolak(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['telepon' => '0000000000']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertSet('galat', 'Nomor telepon tidak sah. Periksa kembali.');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_penolakan_dicatat_di_keamanan(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout(['negara' => 'SG', 'alamat1' => 'ул. Ленина 12']))
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'rate_limit_exceeded',
        ]);
    }

    /**
     * Batas laju checkout.
     *
     * BATAS INI BELUM BISA DIUJI OTOMATIS DI SUITE INI, dan alasannya perlu
     * ditulis supaya tidak dikira kelalaian:
     *
     * 1. CACHE_STORE suite adalah "array" (phpunit.xml): pencacang RateLimiter
     *    bertahan hanya selama satu permintaan.
     * 2. SESSION_DRIVER adalah "array": session()->getId() BEDA pada setiap
     *    Livewire::test(), karena tiap pemanggilan adalah permintaan baru.
     *
     * Kunci batas laju memuat session id (checkout:IP|sesi), jadi angka yang
     * diisi tes tidak pernah bertemu dengan angka yang diperiksa komponen.
     * Tes yang "lulus" di sini lolos palsu — keterusterangan itu lebih berguna
     * daripada tes hijau yang tidak menguji apa pun.
     *
     * Yang SUDAH diverifikasi di berkas ini: segel (satu muat halaman = satu
     * pesanan) membatasi percobaan beruntun lebih ketat daripada batas laju,
     * dan inilah yang benar-benar menghentikan pembersih keranjang otomatis.
     *
     * Yang perlu dilakukan agar batas laju dapat diuji: suite memakai
     * SESSION_DRIVER=database + CACHE_STORE=database, seperti lingkungan nyata.
     * Itu perubahan terpisah karena memengaruhi seluruh suite, bukan hanya
     * checkout.
     */
    public function test_batas_laju_tercatat_di_konfigurasi(): void
    {
        [$maksPercobaan, $menitPelayuan] = config('iksan.rate_limits.checkout');

        // Batas harus ADA. Nilainya 0 berarti pembatasan mati, dan checkout
        // terbuka untuk pengiriman beruntun.
        $this->assertGreaterThan(0, $maksPercobaan);
        $this->assertGreaterThan(0, $menitPelayuan);

        // Dan batas itu memang dipakai oleh komponen, bukan angka yang ditulis
        // terpisah di dua tempat yang bisa menyimpang.
        $this->assertStringContainsString(
            'iksan.rate_limits.checkout',
            file_get_contents(app_path('Livewire/Front/Checkout.php')),
        );
    }

    public function test_daftar_negara_lengkap_dan_seragam(): void
    {
        $daftar = Internasional::NEGARA;

        // Semua kode dua huruf, dan tidak ada yang kembar (dua kunci sama
        // berarti satu negara hilang).
        $this->assertNotEmpty($daftar);

        foreach ($daftar as $kode => $nama) {
            $this->assertSame(2, strlen($kode));
            $this->assertSame($kode, strtoupper($kode));
            $this->assertNotSame('', $nama);
        }

        $this->assertCount(count(array_unique(array_keys($daftar))), $daftar);
    }

    public function test_perkiraan_usd_tidak_membuat_pesanan_rusak(): void
    {
        // Kurs gagal tidak boleh membuat checkout ikut gagal: perkiraan hanya
        // tampilan, sementara yang menentukan pembayaran tetap IDR.
        Cache::store('database')->forget('kurs:usd-idr');

        $hasil = Internasional::perkiraan(4250000, 'USD');

        $this->assertTrue($hasil === null || isset($hasil['teks']));

        $hasilIdr = Internasional::perkiraan(4250000, 'IDR');
        $this->assertIsArray($hasilIdr);
    }
}
