<?php

namespace Tests\Feature;

use App\Livewire\Front\Kontak;
use App\Livewire\Front\SukaProduk;
use App\Livewire\Front\TambahKeranjangCepat;
use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLike;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Skenario pelanggan: beli, chat, suka.
 *
 * Diuji dari sisi PELANGGAN, bukan dari sisi panel: yang dibuktikan adalah
 * pembeli tanpa akun dapat menyelesaikan ketiganya, dan datanya masuk dengan
 * benar.
 */
class SkenarioPelangganTest extends TestCase
{
    use RefreshDatabase;

    private function produk(int $stok = 10): array
    {
        $p = Product::factory()->create(['is_published' => true, 'base_price' => 250000]);

        $v = ProductVariant::query()->create([
            'product_id' => $p->id,
            'sku_variant' => $p->sku.'-A',
            'variant_name_id' => 'Standar',
            'price_adjustment' => 0,
            'stock_quantity' => $stok,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);

        return [$p, $v];
    }

    // ── BELI ────────────────────────────────────────────────────────

    public function test_tambah_ke_keranjang_dari_kartu_katalog(): void
    {
        [, $v] = $this->produk(5);

        Livewire::test(TambahKeranjangCepat::class, ['varianId' => $v->id])
            ->call('tambah')
            ->assertSet('jumlahDiKeranjang', 1);

        $this->assertSame(1, DB::table('cart_items')->count());
        $this->assertSame(1, (int) DB::table('cart_items')->value('quantity'));
    }

    public function test_menambah_dua_kali_menambah_jumlah_bukan_baris(): void
    {
        [, $v] = $this->produk(5);

        $c = Livewire::test(TambahKeranjangCepat::class, ['varianId' => $v->id]);
        $c->call('tambah');
        $c->call('tambah');

        // Satu baris per varian — dijamin juga oleh indeks unik cart_id +
        // product_variant_id. Dua baris akan menggandakan subtotal.
        $this->assertSame(1, DB::table('cart_items')->count());
        $this->assertSame(2, (int) DB::table('cart_items')->value('quantity'));
    }

    public function test_stok_habis_tidak_bisa_ditambahkan(): void
    {
        [, $v] = $this->produk(0);

        Livewire::test(TambahKeranjangCepat::class, ['varianId' => $v->id])
            ->call('tambah')
            ->assertSet('nada', 'danger');

        $this->assertSame(0, DB::table('cart_items')->count());
    }

    public function test_keranjang_tamu_tidak_bisa_dibaca_tamu_lain(): void
    {
        [, $v] = $this->produk(5);

        // Tamu A menambah barang.
        Livewire::test(TambahKeranjangCepat::class, ['varianId' => $v->id])->call('tambah');

        $milikA = Cart::query()->first();
        $this->assertNotNull($milikA);

        // Tamu B membuka halaman keranjang: isinya harus kosong.
        $this->get('/keranjang')->assertOk()->assertDontSee($milikA->ulid);
    }

    public function test_checkout_keranjang_kosong_ditolak(): void
    {
        $this->get('/checkout')->assertRedirect('/keranjang');
    }

    public function test_pesanan_tidak_bisa_dilacak_dengan_nomor_ngawur(): void
    {
        $this->get('/lacak?nomor=IK-PALSU-9999&email=a@b.test')
            ->assertOk()
            ->assertDontSee('IK-PALSU-9999');
    }

    // ── CHAT ────────────────────────────────────────────────────────

    public function test_kirim_pesan_kontak_membuat_baris(): void
    {
        Livewire::test(Kontak::class)
            ->set('dimulaiPada', now()->timestamp - 60)
            ->set('nama', 'Budi')
            ->set('email', 'budi@contoh.test')
            ->set('subjek', 'Tanya harga')
            ->set('pesan', 'Berapa harga knalpot NMAX?')
            ->call('kirim')
            ->assertHasNoErrors();

        $this->assertSame(1, ContactMessage::query()->count());
    }

    public function test_pesan_kontak_menolak_email_ngawur(): void
    {
        Livewire::test(Kontak::class)
            ->set('nama', 'Budi')
            ->set('email', 'bukan-email')
            ->set('subjek', 'Tanya')
            ->set('pesan', 'Halo')
            ->call('kirim')
            ->assertHasErrors('email');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_pesan_kontak_menolak_isi_kosong(): void
    {
        Livewire::test(Kontak::class)
            ->set('nama', '')
            ->set('email', 'budi@contoh.test')
            ->set('pesan', '')
            ->call('kirim')
            ->assertHasErrors(['nama', 'pesan']);
    }

    public function test_pesan_kontak_memiliki_batas_laju(): void
    {
        // Batas laju kontak 3 per jam per alamat (config iksan.rate_limits.contact).
        // Penolakannya muncul sebagai pesan di $galat, BUKAN validation error —
        // itu pilihan komponennya, dan test ini mengikuti perilaku itu.
        //
        // Komponen dibuat ulang tiap putaran karena ada jeda minimum sejak form
        // dibuka (min_form_seconds) yang juga menghalangi pengiriman cepat.
        // Waktu digeser supaya yang diuji benar-benar batas lajunya.
        $terkirim = 0;
        $ditolak = false;

        for ($i = 0; $i < 10; $i++) {
            \Illuminate\Support\Carbon::setTestNow(now()->addMinutes($i));

            $c = Livewire::test(Kontak::class);
            $c->set('dimulaiPada', now()->timestamp - 60)
                ->set('nama', 'Pengirim '.$i)
                ->set('email', 'kirim'.$i.'@contoh.test')
                ->set('subjek', 'Pertanyaan')
                ->set('pesan', 'Isi pesan nomor '.$i)
                ->call('kirim');

            if (filled($c->get('galat'))) {
                $ditolak = true;
                break;
            }

            $terkirim++;
        }

        \Illuminate\Support\Carbon::setTestNow();

        $this->assertTrue($ditolak, 'Pengiriman berulang seharusnya dihentikan batas laju.');
        $this->assertSame(3, $terkirim, 'Batas laju kontak adalah 3 per jam.');
        $this->assertSame(3, ContactMessage::query()->count());
    }

    // ── SUKA ────────────────────────────────────────────────────────

    public function test_suka_sebagai_tamu_membuat_satu_baris(): void
    {
        [$p] = $this->produk();

        Livewire::test(SukaProduk::class, ['produkId' => $p->id])
            ->call('alihkan')
            ->assertSet('disukai', true)
            ->assertSet('jumlah', 1);

        $this->assertSame(1, ProductLike::query()->where('product_id', $p->id)->count());
        $this->assertNotNull(ProductLike::query()->value('session_id'));
        $this->assertNull(ProductLike::query()->value('user_id'));
    }

    public function test_suka_dua_kali_membatalkan(): void
    {
        [$p] = $this->produk();

        $c = Livewire::test(SukaProduk::class, ['produkId' => $p->id]);
        $c->call('alihkan')->assertSet('disukai', true);
        $c->call('alihkan')->assertSet('disukai', false)->assertSet('jumlah', 0);

        $this->assertSame(0, ProductLike::query()->count());
    }

    public function test_angka_suka_tampil_di_katalog_dan_detail(): void
    {
        [$p] = $this->produk();

        ProductLike::alihkan($p, null, 'sesi-uji');

        $katalog = $this->get('/produk')->assertOk()->getContent();
        $this->assertStringContainsString('front.suka-produk', $katalog);

        $detail = $this->get('/produk/'.$p->slug)->assertOk()->getContent();
        $this->assertStringContainsString('Sukai produk ini', $detail);
    }

    public function test_suka_muncul_di_panel_keranjang_ditinggalkan(): void
    {
        [$p] = $this->produk();

        for ($i = 0; $i < 3; $i++) {
            ProductLike::alihkan($p, null, 'sesi-'.$i);
        }

        $this->actingAs(\App\Models\User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@contoh.test',
        ]));

        Livewire::test(\App\Livewire\Admin\KeranjangTerbuka::class)
            ->assertSee('Paling banyak disukai')
            ->assertSee($p->title_id);
    }
}
