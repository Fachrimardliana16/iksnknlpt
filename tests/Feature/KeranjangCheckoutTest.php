<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Livewire\Front\Checkout;
use App\Livewire\Front\KeranjangHalaman;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Keranjang;
use App\Services\PesananService;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F7 - keranjang dan checkout (checklist F7-01 sampai F7-12).
 *
 * Yang diuji di sini adalah aturan yang menentukan uang dan barang: berapa yang
 * ditahan, apa yang terjadi saat stok berkurang setelah barang masuk keranjang,
 * dan bagaimana dua klik pada tombol yang sama tidak menghasilkan dua pesanan.
 */
class KeranjangCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

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

        $this->produk = Product::query()->create([
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
            'product_id' => $this->produk->id,
            'sku_variant' => 'IK-FS-9001-V1',
            'variant_name_id' => 'Stainless',
            'variant_name_en' => 'Stainless',
            'price_adjustment' => 0,
            'stock_quantity' => 10,
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    private function isiKeranjang(int $jumlah = 1): Keranjang
    {
        /** @var Keranjang $keranjang */
        $keranjang = app(Keranjang::class);
        $keranjang->tambah($this->varian, $jumlah);

        return $keranjang;
    }

    private function dataCheckout(array $ubah = []): array
    {
        return array_merge([
            'nama' => 'Pembeli Uji',
            'email' => 'pembeli@contoh.test',
            'telepon' => '081234567890',
            'alamat1' => 'Jl. Ringroad Utara 128',
            'alamat2' => '',
            'kota' => 'Sleman',
            'provinsi' => 'DI Yogyakarta',
            'kodePos' => '55283',
            'kurir' => 'JNE',
            // 'catatan' sengaja TIDAK ada di sini. Mengisinya dengan string
            // kosong berarti menimpa catatan yang sudah diteruskan dari halaman
            // keranjang — dan itulah yang terjadi pada percobaan pertama:
            // pesanannya kosong, bukan karena kodenya, melainkan karena alat
            // ujinya sendiri yang menghapusnya.
            'metode' => 'manual',
        ], $ubah);
    }

    // ── F7-01: keranjang tamu dan pengguna ───────────────────────────

    public function test_keranjang_tamu_disimpan_pada_baris_dengan_session_id(): void
    {
        $this->isiKeranjang(2);

        $cart = Cart::query()->first();

        $this->assertNotNull($cart);
        $this->assertNull($cart->user_id);
        $this->assertSame(session()->getId(), $cart->session_id);
        $this->assertSame(2, (int) CartItem::query()->first()->quantity);
    }

    public function test_pengguna_login_memakai_baris_keranjang_miliknya(): void
    {
        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);

        $this->actingAs($user);

        app(Keranjang::class)->tambah($this->varian, 3);

        $cart = Cart::query()->first();

        $this->assertSame($user->id, $cart->user_id);
        $this->assertNull($cart->session_id);
    }

    // ── F7-02: penggabungan saat login ───────────────────────────────

    public function test_keranjang_tamu_digabungkan_ke_akun_tanpa_duplikat_varian(): void
    {
        $this->isiKeranjang(2);

        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);

        // Keranjang akun sudah berisi varian yang sama, sebanyak tiga.
        $akun = Cart::query()->create(['user_id' => $user->id]);
        $akun->items()->create([
            'product_id' => $this->produk->id,
            'product_variant_id' => $this->varian->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user);

        app(Keranjang::class)->gabungkanKe($user);

        $this->assertSame(1, Cart::query()->count(), 'Keranjang tamu harus dilebur, bukan dibiarkan.');

        $baris = CartItem::query()->get();

        $this->assertCount(1, $baris, 'Varian yang sama tidak boleh menjadi dua baris.');
        $this->assertSame(5, (int) $baris->first()->quantity, 'Jumlah digabung: 2 + 3.');
    }

    public function test_penggabungan_tidak_melebihi_stok_tersedia(): void
    {
        $this->isiKeranjang(4);

        $this->varian->update(['stock_quantity' => 6]);

        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);

        $akun = Cart::query()->create(['user_id' => $user->id]);
        $akun->items()->create([
            'product_id' => $this->produk->id,
            'product_variant_id' => $this->varian->id,
            'quantity' => 5,
        ]);

        $this->actingAs($user);

        app(Keranjang::class)->gabungkanKe($user);

        $this->assertSame(6, (int) CartItem::query()->first()->quantity, 'Dibatasi stok, bukan 9.');
    }

    // ── F7-03: ubah jumlah, hapus, ringkasan, catatan ─────────────────

    public function test_ubah_jumlah_dan_hapus_baris(): void
    {
        $this->isiKeranjang(2);

        $item = CartItem::query()->first();

        Livewire::test(KeranjangHalaman::class)->call('ubahJumlah', $item->id, 5);

        $this->assertSame(5, (int) $item->fresh()->quantity);

        Livewire::test(KeranjangHalaman::class)->call('hapusItem', $item->id);

        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_ringkasan_menghitung_ongkir_dan_pajak(): void
    {
        $this->seed(\Database\Seeders\SettingSeeder::class);

        \App\Models\Setting::query()->where('key', 'shipping_flat_rate')->update(['value' => '25000']);
        \App\Models\Setting::query()->where('key', 'tax_percent')->update(['value' => '11']);
        Pengaturan::lupakan();

        $this->isiKeranjang(1);

        $ringkasan = app(Keranjang::class)->ringkasan();

        $this->assertSame(4250000.0, (float) $ringkasan['subtotal']);
        $this->assertSame(25000.0, (float) $ringkasan['ongkir']);
        $this->assertSame(467500.0, (float) $ringkasan['pajak']);
        $this->assertSame(4742500.0, (float) $ringkasan['total']);
    }

    public function test_catatan_keranjang_tersimpan_dan_diteruskan_ke_checkout(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)
            ->set('catatan', 'Titip di pos keamanan')
            ->assertHasNoErrors();

        $this->assertSame('Titip di pos keamanan', app(Keranjang::class)->catatan());

        Livewire::test(Checkout::class)->assertSet('catatan', 'Titip di pos keamanan');
    }

    public function test_catatan_ikut_tersimpan_ke_pesanan(): void
    {
        $this->isiKeranjang(1);
        app(Keranjang::class)->simpanCatatan('Titip di pos keamanan');

        $cek = Livewire::test(Checkout::class);
        $this->assertSame('Titip di pos keamanan', $cek->get('catatan'), 'Catatan diteruskan dari keranjang.');

        $cek->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $order = Order::query()->first();
        $this->assertSame('Titip di pos keamanan', $order?->customer_note);
    }

    // ── F7-04: validasi stok saat keranjang dibuka ───────────────────

    public function test_stok_berkurang_menyesuaikan_jumlah_dengan_pemberitahuan(): void
    {
        $this->isiKeranjang(6);

        $this->varian->update(['stock_quantity' => 2]);

        $halaman = Livewire::test(KeranjangHalaman::class);

        $this->assertSame(2, (int) CartItem::query()->first()->quantity);
        $this->assertStringContainsString('Knalpot Full System NMAX', (string) $halaman->get('pesanStok'));
        $this->assertStringContainsString('2', (string) $halaman->get('pesanStok'));
    }

    public function test_stok_habis_menghapus_baris_dan_menyebut_namanya(): void
    {
        $this->isiKeranjang(2);

        $this->varian->update(['stock_quantity' => 0]);

        $halaman = Livewire::test(KeranjangHalaman::class);

        $this->assertSame(0, CartItem::query()->count());
        $this->assertStringContainsString('Knalpot Full System NMAX', (string) $halaman->get('pesanStok'));
        $this->assertStringContainsString('habis', (string) $halaman->get('pesanStok'));
    }

    public function test_ubah_jumlah_melebihi_stok_dipotong_di_server(): void
    {
        $this->isiKeranjang(1);

        $item = CartItem::query()->first();

        Livewire::test(KeranjangHalaman::class)
            ->call('ubahJumlah', $item->id, 99);

        $this->assertSame(10, (int) $item->fresh()->quantity, 'Dipotong ke stok tersedia.');
    }

    public function test_stok_yang_ditahan_pesanan_lain_ikut_dihitung(): void
    {
        $this->varian->update(['stock_quantity' => 5, 'reserved_quantity' => 4]);

        $this->isiKeranjang(1);

        $item = CartItem::query()->first();

        Livewire::test(KeranjangHalaman::class)->call('ubahJumlah', $item->id, 9);

        $this->assertSame(1, (int) $item->fresh()->quantity, 'Hanya 1 yang benar-benar dapat dijual.');
    }

    // ── F7-05: tiga tipe kupon ───────────────────────────────────────

    public function test_kupon_persen_mengurangi_subtotal(): void
    {
        $this->buatKupon('PERSEN10', 'percent', 10, ['min_spend' => 500000]);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)
            ->set('kodeKupon', 'persen10')
            ->call('terapkanKupon')
            ->assertSee('PERSEN10');

        $this->assertSame(425000.0, (float) app(Keranjang::class)->ringkasan()['diskon']);
    }

    public function test_kupon_nominal_dipotong_dari_subtotal(): void
    {
        $this->buatKupon('POTONG200', 'fixed', 200000);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)->set('kodeKupon', 'POTONG200')->call('terapkanKupon');

        $this->assertSame(200000.0, (float) app(Keranjang::class)->ringkasan()['diskon']);
    }

    public function test_kupon_gratis_ongkir_menghapus_ongkir_tanpa_menyentuh_subtotal(): void
    {
        \App\Models\Setting::query()->where('key', 'shipping_flat_rate')->update(['value' => '25000']);
        Pengaturan::lupakan();

        $this->buatKupon('GRATISONGKIR', 'free_shipping', 0);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)->set('kodeKupon', 'GRATISONGKIR')->call('terapkanKupon');

        $ringkasan = app(Keranjang::class)->ringkasan();

        $this->assertSame(0.0, (float) $ringkasan['diskon'], 'Subtotal tidak boleh ikut terpotong.');
        $this->assertSame(0.0, (float) $ringkasan['ongkir'], 'Ongkir menjadi nol.');
    }

    public function test_kupon_belum_mencapai_minimal_ditolak(): void
    {
        $this->buatKupon('MINIMAL10JT', 'percent', 10, ['min_spend' => 10000000]);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)
            ->set('kodeKupon', 'MINIMAL10JT')
            ->call('terapkanKupon')
            ->assertSet('nadaKupon', 'danger')
            ->assertSee('Minimal belanja');

        $this->assertNull(app(Keranjang::class)->kupon());
    }

    public function test_kupon_kedaluwarsa_ditolak(): void
    {
        $this->buatKupon('BASI', 'percent', 10, ['ends_at' => now()->subDay()]);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)
            ->set('kodeKupon', 'BASI')
            ->call('terapkanKupon')
            ->assertSee('kedaluwarsa');

        $this->assertNull(app(Keranjang::class)->kupon());
    }

    public function test_kupon_kuota_habis_ditolak(): void
    {
        $this->buatKupon('HABIS', 'percent', 10, ['quota' => 5, 'used_count' => 5]);
        $this->isiKeranjang(1);

        Livewire::test(KeranjangHalaman::class)
            ->set('kodeKupon', 'HABIS')
            ->call('terapkanKupon')
            ->assertSee('Kuota');
    }

    public function test_kupon_yang_sudah_tidak_sah_tidak_menempel_di_ringkasan(): void
    {
        // Minimal belanja di antara harga satu unit dan harga dua unit, supaya
        // syaratnya terpenuhi saat kupon dipasang lalu tidak lagi terpenuhi
        // setelah jumlahnya diturunkan.
        $this->buatKupon('MENDEKATI', 'percent', 10, ['min_spend' => 5000000]);
        $this->isiKeranjang(2);

        Livewire::test(KeranjangHalaman::class)
            ->set('kodeKupon', 'MENDEKATI')
            ->call('terapkanKupon')
            ->assertSet('nadaKupon', 'ok');

        $this->assertNotNull(app(Keranjang::class)->kupon());

        app(Keranjang::class)->perbarui(CartItem::query()->first(), 1);

        $this->assertNull(
            app(Keranjang::class)->kupon(),
            'Kupon yang tidak lagi memenuhi syarat tidak boleh tetap dihitung.',
        );
    }

    private function buatKupon(string $kode, string $tipe, float $nilai, array $ubah = []): Coupon
    {
        return Coupon::query()->create(array_merge([
            'code' => $kode,
            'type' => $tipe,
            'value' => $nilai,
            'is_active' => true,
        ], $ubah));
    }

    /** Nomor dari halaman sukses, dipakai untuk mengunci percobaan kedua. */
    private function nomorPesananTerakhir(): string
    {
        return (string) Order::query()->latest('id')->value('order_number');
    }

    // ── F7-06: checkout tamu dan isian otomatis ──────────────────────

    public function test_checkout_mengisi_otomatis_dari_alamat_utama_pengguna(): void
    {
        $user = User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);

        $user->addresses()->create([
            'label' => 'Rumah',
            'recipient_name' => 'Ibu Uji',
            'phone_number' => '089876543210',
            'address_line_1' => 'Jl. Kaliurang KM 5',
            'city' => 'Sleman',
            'state_province' => 'DI Yogyakarta',
            'postal_code' => '55581',
            'is_primary' => true,
        ]);

        $this->actingAs($user);
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->assertSet('nama', 'Ibu Uji')
            ->assertSet('email', 'pelanggan@contoh.test')
            ->assertSet('telepon', '089876543210')
            ->assertSet('alamat1', 'Jl. Kaliurang KM 5')
            ->assertSet('kota', 'Sleman');
    }

    public function test_daftar_kurir_dibaca_dari_pengaturan(): void
    {
        \App\Models\Setting::query()->where('key', 'shipping_couriers')
            ->update(['value' => json_encode(['AnterAja', 'Ninja Xpress'])]);
        \App\Models\Setting::query()->where('key', 'shipping_courier_default')
            ->update(['value' => 'AnterAja']);
        Pengaturan::lupakan();

        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->assertSet('kurir', 'AnterAja')
            ->assertSee('Ninja Xpress');
    }

    public function test_metode_pembayaran_mengikuti_mode_di_pengaturan(): void
    {
        \App\Models\Setting::query()->where('key', 'payment_mode')->update(['value' => 'paypal']);
        Pengaturan::lupakan();

        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->assertSet('metode', 'paypal')
            ->assertSee('PayPal');
    }

    public function test_checkout_tanpa_barang_dialihkan_ke_keranjang(): void
    {
        Livewire::test(Checkout::class)->assertRedirect(route('front.cart'));
    }

    // ── F7-07: pesanan dalam satu transaksi ──────────────────────────

    public function test_pesanan_menyimpan_snapshot_dan_menahan_stok(): void
    {
        $this->isiKeranjang(2);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $order = Order::query()->first();
        $item = OrderItem::query()->first();

        $this->assertNotNull($order);
        $this->assertSame('Pembeli Uji', $order->namaPenerima());
        $this->assertSame('Knalpot Full System NMAX', $item->product_name);
        $this->assertSame('Stainless', $item->variant_name);
        $this->assertSame('4250000.00', (string) $item->unit_price);
        $this->assertSame(8500000.0, (float) $order->grand_total);

        // Stok fisik TIDAK berkurang; yang bertambah hanya tahanannya.
        $this->assertSame(10, (int) $this->varian->fresh()->stock_quantity);
        $this->assertSame(2, (int) $this->varian->fresh()->reserved_quantity);
    }

    public function test_dua_klik_tidak_menghasilkan_dua_pesanan(): void
    {
        $this->isiKeranjang(1);

        $komponen = Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan')
            ->assertHasNoErrors();

        $this->assertSame(1, Order::query()->count());

        // Klik kedua dari komponen yang sama — inilah yang terjadi pada layar
        // ketika tombol ditekan dua kali sebelum halaman berpindah. Keranjang
        // sudah kosong, jadi tanpa penanda idempoten hasilnya adalah pesan
        // "Keranjang kosong." untuk pembeli yang justru baru saja berhasil.
        $komponen->call('buatPesanan');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, (int) $this->varian->fresh()->reserved_quantity);
    }

    public function test_tidak_bisa_oversell_meski_dua_pesanan_bersamaan(): void
    {
        $this->varian->update(['stock_quantity' => 1]);

        /** @var PesananService $pesanan */
        $pesanan = app(PesananService::class);

        $this->isiKeranjang(1);

        $pesanan->buatDariKeranjang(app(Keranjang::class), $this->dataCheckout(), \App\Enums\MetodePembayaran::Manual);

        // Keranjang baru untuk pembeli kedua, meminta barang yang sama.
        $cart = Cart::query()->create(['session_id' => 'sesi-kedua']);
        $cart->items()->create([
            'product_id' => $this->produk->id,
            'product_variant_id' => $this->varian->id,
            'quantity' => 1,
        ]);

        $this->expectException(\RuntimeException::class);

        $pesanan->buatDariKeranjang(
            new Keranjang(),
            $this->dataCheckout(['email' => 'kedua@contoh.test']),
            \App\Enums\MetodePembayaran::Manual,
        );
    }

    // ── F7-08: nomor pesanan ─────────────────────────────────────────

    public function test_nomor_pesanan_berpola_dan_tidak_berurutan(): void
    {
        $this->isiKeranjang(1);

        /** @var PesananService $pesanan */
        $pesanan = app(PesananService::class);

        $nomor = [];

        for ($i = 0; $i < 5; $i++) {
            $nomor[] = $pesanan->nomorPesanan();
        }

        foreach ($nomor as $satu) {
            $this->assertMatchesRegularExpression('/^IK-\d{8}-[A-Z0-9]{6}$/', $satu);
        }

        $this->assertCount(5, array_unique($nomor), 'Nomor harus unik.');
        $this->assertGreaterThan(1, count(array_unique(array_map(fn ($n) => substr($n, -6), $nomor))));
    }

    // ── F7-09: halaman sukses ────────────────────────────────────────

    public function test_halaman_sukses_menampilkan_instruksi_sesuai_mode(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();

        $this->get(route('front.order.success', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('BCA');
    }

    // ── F7-10: halaman lacak ─────────────────────────────────────────

    public function test_lacak_dengan_nomor_dan_email_benar_menampilkan_status(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();

        Livewire::test(\App\Livewire\Front\LacakPesanan::class)
            ->set('nomor', $order->order_number)
            ->set('email', 'pembeli@contoh.test')
            ->call('lacak')
            ->assertSet('galat', null)
            ->assertSee($order->order_number);
    }

    public function test_lacak_dengan_email_salah_memberi_pesan_seragam(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();

        Livewire::test(\App\Livewire\Front\LacakPesanan::class)
            ->set('nomor', $order->order_number)
            ->set('email', 'oranglain@contoh.test')
            ->call('lacak')
            ->assertSet('hasil', []);

        // Pesan untuk nomor yang tidak ada dan email yang salah harus sama,
        // supaya halaman ini tidak menjadi alat menebak nomor pesanan.
        $pesanSalah = Livewire::test(\App\Livewire\Front\LacakPesanan::class)
            ->set('nomor', $order->order_number)
            ->set('email', 'oranglain@contoh.test')
            ->call('lacak')
            ->get('galat');

        $pesanTidakAda = Livewire::test(\App\Livewire\Front\LacakPesanan::class)
            ->set('nomor', 'IK-20260101-ZZZZZZ')
            ->set('email', 'oranglain@contoh.test')
            ->call('lacak')
            ->get('galat');

        $this->assertSame($pesanSalah, $pesanTidakAda);
    }

    public function test_lacak_dibatasi_sepuluh_percobaan_per_menit(): void
    {
        RateLimiter::clear('laju:track_order:'.hash('sha256', '127.0.0.1'));

        for ($i = 0; $i < 10; $i++) {
            $this->get('/lacak')->assertOk();
        }

        // Percobaan ke-11 ditolak middleware dengan 429 sungguhan, bukan hanya
        // diberi pesan di dalam halaman. Pembatas di dalam komponen tidak dapat
        // melakukan ini: responsnya tetap 200.
        $this->get('/lacak')->assertStatus(429);
    }

    // ── F7-11: batas laju ────────────────────────────────────────────

    public function test_pencarian_dibatasi_setelah_tiga_puluh_kata_per_menit(): void
    {
        RateLimiter::clear('laju:search:'.hash('sha256', '127.0.0.1'));

        for ($i = 0; $i < 30; $i++) {
            $this->get('/produk?q=knalpot'.$i)->assertOk();
        }

        $this->get('/produk?q=knalpot-akhir')->assertStatus(429);
    }

    public function test_membuka_katalog_tanpa_kata_kunci_tidak_terhitung_pencarian(): void
    {
        RateLimiter::clear('laju:search:'.hash('sha256', '127.0.0.1'));

        for ($i = 0; $i < 40; $i++) {
            $this->get('/produk')->assertOk();
        }
    }

    public function test_tambah_keranjang_dibatasi_enam_puluh_per_menit(): void
    {
        RateLimiter::clear('keranjang-tambah:'.session()->getId());

        $keranjang = app(Keranjang::class);

        for ($i = 0; $i < 60; $i++) {
            $keranjang->tambah($this->varian, 1);
        }

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $keranjang->tambah($this->varian, 1);
    }

    // ── F7-12: pembatalan otomatis ───────────────────────────────────

    public function test_penjadwal_membatalkan_pesanan_kedaluwarsa_dan_melepas_stok(): void
    {
        $this->isiKeranjang(2);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();

        // Waktu dimundurkan, bukan ditunggu: pesanan dibuat 25 jam lalu.
        $order->forceFill(['expires_at' => now()->subHour()])->save();

        $this->assertSame(2, (int) $this->varian->fresh()->reserved_quantity);

        $this->artisan('iksan:batalkan-kedaluwarsa')->assertSuccessful();

        $order->refresh();

        $this->assertSame(StatusPesanan::Cancelled, $order->status);
        $this->assertSame(0, (int) $this->varian->fresh()->reserved_quantity, 'Tahanan harus dilepas.');
        $this->assertSame(10, (int) $this->varian->fresh()->stock_quantity, 'Stok fisik tidak berkurang.');
        $this->assertNotNull($order->cancelled_at);

        $riwayat = $order->statusHistory()->latest('id')->first();
        $this->assertSame(StatusPesanan::Cancelled->value, $riwayat->to_status->value);
        $this->assertStringContainsString('belum dibayar', (string) $riwayat->note);
    }

    public function test_pesanan_yang_belum_lewat_batas_tidak_dibatalkan(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $this->artisan('iksan:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame(StatusPesanan::Pending, Order::query()->first()->status);
        $this->assertSame(1, (int) $this->varian->fresh()->reserved_quantity);
    }

    public function test_pesanan_yang_sudah_dibatalkan_manual_tidak_disentuh_lagi(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();

        app(\App\Services\StatusPesananService::class)
            ->pindah($order, StatusPesanan::Cancelled, null, 'Dibatalkan pembeli.');

        $sebelum = $order->fresh()->statusHistory()->count();

        $this->artisan('iksan:batalkan-kedaluwarsa')->assertSuccessful();

        $this->assertSame($sebelum, $order->fresh()->statusHistory()->count(), 'Riwayat tidak bertambah.');
        $this->assertSame('Dibatalkan pembeli.', $order->fresh()->statusHistory()->latest('id')->first()->note);
    }

    public function test_opsi_uji_tidak_mengubah_apa_pun(): void
    {
        $this->isiKeranjang(1);

        Livewire::test(Checkout::class)
            ->set($this->dataCheckout())
            ->set('dimulaiPada', now()->subSeconds(5)->timestamp)
            ->call('buatPesanan');

        $order = Order::query()->first();
        $order->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('iksan:batalkan-kedaluwarsa', ['--uji' => true])->assertSuccessful();

        $this->assertSame(StatusPesanan::Pending, $order->fresh()->status);
        $this->assertSame(1, (int) $this->varian->fresh()->reserved_quantity);
    }
}
