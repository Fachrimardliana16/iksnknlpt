<?php

namespace Tests\Feature;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Livewire\Front\Checkout;
use App\Livewire\Front\DetailProduk;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alur belanja ujung ke ujung (checklist F11-11).
 *
 * Berbeda dari test lain yang menguji satu komponen, di sini alurnya dijalankan
 * berurutan seperti pembeli sungguhan: pilih varian, masuk keranjang, terapkan
 * kupon, isi data pengiriman, buat pesanan, lalu unggah bukti dan verifikasi.
 *
 * Yang paling penting dibuktikan di sini adalah ATURAN STOK sepanjang alur itu,
 * karena di situlah uang bertemu barang:
 *
 *   dibuat   -> stok fisik tetap, tahanan bertambah
 *   lunas    -> stok fisik tetap, tahanan tetap
 *   selesai  -> stok fisik berkurang, tahanan berkurang
 *   batal    -> stok fisik tetap, tahanan dilepas
 *
 * Melewati salah satunya berarti stok bisa berkurang dua kali, atau barang
 * terkirim tanpa stok pernah berkurang.
 */
class AlurBelanjaTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $varian;

    private Product $produk;

    private function siapkanKatalog(int $stok = 10, float $harga = 250000): void
    {
        Queue::fake();

        $kategori = Category::query()->create([
            'name_id' => 'Knalpot',
            'slug' => 'knalpot',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $this->produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'ALUR-001',
            'title_id' => 'Knalpot Full System',
            'slug' => 'knalpot-full-system',
            'base_price' => $harga,
            'weight_gram' => 3000,
            'is_published' => true,
            'is_featured' => true,
            'display_order' => 1,
        ]);

        \App\Models\ProductMedia::query()->create([
            'product_id' => $this->produk->id,
            'type' => 'image',
            'path' => 'products/alur/card.webp',
            'is_primary' => true,
            'display_order' => 1,
        ]);

        $this->varian = ProductVariant::query()->create([
            'product_id' => $this->produk->id,
            'sku_variant' => 'ALUR-001-SS',
            'variant_name_id' => 'Stainless',
            'price_adjustment' => 50000,
            'stock_quantity' => $stok,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    private function keadaanStok(): array
    {
        $v = $this->varian->fresh();

        return ['fisik' => $v->stock_quantity, 'tahan' => $v->reserved_quantity];
    }

    private function isiCheckout(array $ganti = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'telepon' => '081234567890',
            'alamat1' => 'Jl. Merdeka 10',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'kodePos' => '40111',
            'kurir' => 'JNE',
            'metode' => 'manual',
        ], $ganti);
    }

    // ── 1. Katalog sampai keranjang ─────────────────────────────────

    public function test_beranda_dan_daftar_produk_menampilkan_barang(): void
    {
        $this->siapkanKatalog();

        $this->get('/')->assertOk()->assertSee('Knalpot Full System', false);
        $this->get('/produk')->assertOk()->assertSee('Knalpot Full System', false);

        $this->get('/produk/knalpot-full-system')
            ->assertOk()
            ->assertSee('Knalpot Full System', false);
    }

    public function test_tambah_ke_keranjang_lewat_halaman_detail(): void
    {
        $this->siapkanKatalog();

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 2)
            ->call('tambah')
            ->assertHasNoErrors();

        // Keranjang belum menahan stok: tahanan baru naik saat pesanan dibuat.
        $this->assertSame(['fisik' => 10, 'tahan' => 0], $this->keadaanStok());

        $keranjang = app(Keranjang::class);

        $this->assertSame(2, $keranjang->jumlahItem());
        // Harga jual = harga dasar 250.000 + penyesuaian 50.000.
        $this->assertSame(600000.0, (float) $keranjang->subtotal());
    }

    public function test_keranjang_tidak_melebihi_stok_tersedia(): void
    {
        $this->siapkanKatalog(stok: 3);

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 99)
            ->call('tambah');

        // Keranjang dibatasi stok, bukan menerima pesanan yang tidak mungkin
        // dipenuhi lalu menolaknya di checkout.
        $this->assertSame(3, app(Keranjang::class)->jumlahItem());
    }

    public function test_varian_habis_tidak_dapat_ditambahkan(): void
    {
        $this->siapkanKatalog(stok: 0);

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 1)
            ->call('tambah');

        $this->assertSame(0, app(Keranjang::class)->jumlahItem());
    }

    // ── 2. Kupon ────────────────────────────────────────────────────

    public function test_kupon_mengurangi_total_pesanan(): void
    {
        $this->siapkanKatalog();

        $kupon = Coupon::query()->create([
            'code' => 'ALUR10',
            'type' => 'percent',
            'value' => 10,
            'min_spend' => 0,
            'max_discount' => 100000,
            'quota' => 10,
            'used_count' => 0,
            'is_active' => true,
        ]);

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 2)
            ->call('tambah');

        $keranjang = app(Keranjang::class);
        $keranjang->terapkanKupon($kupon);

        // subtotal() adalah jumlah barang SEBELUM potongan; yang sudah
        // dipotong adalah ringkasan()['total']. 600.000 - 10% = 540.000, masih
        // di bawah batas potongan 100.000.
        $ringkas = $keranjang->ringkasan();

        $this->assertSame(600000.0, (float) $ringkas['subtotal'], 'subtotal sebelum potongan');
        $this->assertSame(60000.0, (float) $ringkas['diskon'], 'potongan 10%');
        $this->assertSame(540000.0, (float) $ringkas['total'], 'total setelah potongan');

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $pesanan = Order::query()->firstOrFail();

        $this->assertSame(540000.0, (float) $pesanan->grand_total);
        $this->assertSame($kupon->id, $pesanan->coupon_id);
        $this->assertSame(1, $kupon->fresh()->used_count);
    }

    // ── 3. Checkout sampai pesanan jadi ─────────────────────────────

    public function test_checkout_membuat_pesanan_dan_menahan_stok(): void
    {
        $this->siapkanKatalog();

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 2)
            ->call('tambah');

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $pesanan = Order::query()->with('items')->firstOrFail();

        $this->assertSame(StatusPesanan::Pending, $pesanan->status);
        $this->assertSame(StatusPembayaran::Unpaid, $pesanan->payment_status);
        $this->assertSame(MetodePembayaran::Manual, $pesanan->payment_method);
        $this->assertCount(1, $pesanan->items);
        $this->assertSame('Budi Santoso', $pesanan->shipping_address_snapshot['recipient_name']);

        // Stok fisik TIDAK berkurang; yang naik adalah tahanan.
        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok());

        // Keranjang kosong setelah checkout.
        $this->assertSame(0, app(Keranjang::class)->jumlahItem());

        Queue::assertPushed(KirimNotifikasiPesanan::class);
    }

    public function test_checkout_tanpa_nama_ditolak(): void
    {
        $this->siapkanKatalog();

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->call('tambah');

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout(['nama' => '']))
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan')
            ->assertHasErrors(['nama']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_terlalu_cepat_ditolak(): void
    {
        $this->siapkanKatalog();

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->call('tambah');

        // Jebakan bot: formulir yang terisi dalam hitungan milidetik dikirim
        // mesin, bukan orang.
        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->timestamp)
            ->call('buatPesanan');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_memakai_harga_dari_basis_data_bukan_keranjang(): void
    {
        $this->siapkanKatalog(harga: 250000);

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->call('tambah');

        // Harga diubah setelah barang masuk keranjang, seperti saat admin
        // memperbarui harga ketika pembeli sedang mengisi formulir.
        $this->produk->update(['base_price' => 300000]);

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan');

        // Yang dipakai harga saat checkout, bukan yang tersimpan di keranjang.
        $this->assertSame(350000.0, (float) Order::query()->firstOrFail()->grand_total);
    }

    public function test_checkout_ditolak_bila_stok_berkurang_sebelum_dibayar(): void
    {
        $this->siapkanKatalog(stok: 5);

        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 4)
            ->call('tambah');

        // Pembeli lain membeli lebih dulu.
        $this->varian->update(['stock_quantity' => 2]);

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(['fisik' => 2, 'tahan' => 0], $this->keadaanStok());
    }

    // ── 4. Siklus status dan stok ───────────────────────────────────

    private function buatPesanan(): Order
    {
        Livewire::test(DetailProduk::class, ['product' => $this->produk])
            ->set('variantId', $this->varian->id)
            ->set('jumlah', 2)
            ->call('tambah');

        Livewire::test(Checkout::class)
            ->set($this->isiCheckout())
            ->set('dimulaiPada', now()->subSeconds(10)->timestamp)
            ->call('buatPesanan');

        return Order::query()->with('items')->firstOrFail();
    }

    public function test_siklus_lengkap_sampai_selesai(): void
    {
        $this->siapkanKatalog();
        $pesanan = $this->buatPesanan();

        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok(), 'setelah dibuat');

        $layanan = app(\App\Services\StatusPesananService::class);

        $layanan->pindah($pesanan, StatusPesanan::Paid);
        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok(), 'setelah lunas');

        $layanan->pindah($pesanan->fresh(), StatusPesanan::Processing);
        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok(), 'setelah diproses');

        $layanan->pindah($pesanan->fresh(), StatusPesanan::Shipped);
        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok(), 'setelah dikirim');

        $layanan->pindah($pesanan->fresh(), StatusPesanan::Completed);
        $this->assertSame(['fisik' => 8, 'tahan' => 0], $this->keadaanStok(), 'setelah selesai');
    }

    public function test_pembatalan_melepas_tahanan_tanpa_menyentuh_stok_fisik(): void
    {
        $this->siapkanKatalog();
        $pesanan = $this->buatPesanan();

        app(\App\Services\StatusPesananService::class)->pindah($pesanan, StatusPesanan::Cancelled);

        $this->assertSame(['fisik' => 10, 'tahan' => 0], $this->keadaanStok());
    }

    public function test_perpindahan_status_tidak_sah_ditolak(): void
    {
        $this->siapkanKatalog();
        $pesanan = $this->buatPesanan();

        // Pending tidak dapat langsung ke Selesai.
        $this->expectException(\RuntimeException::class);

        app(\App\Services\StatusPesananService::class)
            ->pindah($pesanan, StatusPesanan::Completed);
    }

    public function test_pembayaran_manual_menuntaskan_alur_sampai_selesai(): void
    {
        $this->siapkanKatalog(5);
        $pesanan = $this->buatPesanan();

        // 1. Pembeli mengunggah bukti.
        $manual = app(\App\Services\Pembayaran\PembayaranManual::class);

        \Illuminate\Support\Facades\Storage::fake('local');

        $manual->unggahBukti(
            $pesanan,
            \Illuminate\Http\UploadedFile::fake()->image('bukti.png', 600, 800),
        );

        $this->assertSame(StatusPembayaran::Pending, $pesanan->fresh()->payment_status);
        $this->assertSame(['fisik' => 5, 'tahan' => 2], $this->keadaanStok(), 'bukti belum mengubah stok apapun');

        // 2. Admin memverifikasi.
        $bayar = \App\Models\Payment::query()->where('order_id', $pesanan->id)->firstOrFail();
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);

        $manual->verifikasi($bayar, $admin->id);

        $this->assertSame(StatusPesanan::Paid, $pesanan->fresh()->status);
        $this->assertSame(StatusPembayaran::Paid, $pesanan->fresh()->payment_status);
        $this->assertSame(['fisik' => 5, 'tahan' => 2], $this->keadaanStok(), 'verifikasi tidak menyentuh stok');

        // 3. Dikirim lalu selesai.
        $layanan = app(\App\Services\StatusPesananService::class);
        $layanan->pindah($pesanan->fresh(), StatusPesanan::Processing, $admin);
        $layanan->pindah($pesanan->fresh(), StatusPesanan::Shipped, $admin);
        $layanan->pindah($pesanan->fresh(), StatusPesanan::Completed, $admin);

        $this->assertSame(['fisik' => 3, 'tahan' => 0], $this->keadaanStok(), 'setelah selesai');
    }

    // ── 5. Muat ulang dari nol ──────────────────────────────────────

    public function test_seluruh_alur_berjalan_setelah_cache_dikosongkan(): void
    {
        $this->siapkanKatalog();
        Cache::flush();

        // Beranda dan daftar produk harus tetap benar meski cache katalog baru
        // saja dikosongkan — inilah keadaan setelah penerapan atau perubahan
        // pengaturan.
        $this->get('/')->assertOk()->assertSee('Knalpot Full System', false);
        $this->get('/produk')->assertOk()->assertSee('Knalpot Full System', false);

        $pesanan = $this->buatPesanan();

        $this->assertSame(['fisik' => 10, 'tahan' => 2], $this->keadaanStok());
        $this->assertSame(StatusPesanan::Pending, $pesanan->status);
    }
}
