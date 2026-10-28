<?php

namespace Tests\Feature;

use App\Livewire\Front\SukaProduk;
use App\Models\Product;
use App\Models\ProductLike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tombol suka produk.
 *
 * Yang diperiksa adalah aturan yang membuat hitungannya bisa DIPERCAYA:
 * tekan dua kali harus membatalkan (bukan dua baris), tamu dikenali lewat
 * sesi (bukan diabaikan), dan batas laju menahan satu skrip yang menggerakkan
 * angka iklan seluruh katalog.
 */
class SukaProdukTest extends TestCase
{
    use RefreshDatabase;

    private function produk(): Product
    {
        return Product::factory()->create();
    }

    public function test_menekan_suka_membuat_satu_baris(): void
    {
        $produk = $this->produk();

        Livewire::test(SukaProduk::class, ['produkId' => $produk->id])
            ->call('alihkan')
            ->assertSet('disukai', true)
            ->assertSet('jumlah', 1);

        $this->assertSame(1, ProductLike::query()->where('product_id', $produk->id)->count());
    }

    public function test_mount_tanpa_props_tidak_menghitung_kueri_sendiri(): void
    {
        // Anggaran kueri: komponen ini TIDAK boleh membaca basis data saat
        // mount. Versi pertama menghitung sendiri, dan halaman katalog dengan
        // dua belas kartu menembus anggaran 15 kueri.
        $produk = $this->produk();

        $jumlah = 0;
        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        Livewire::test(SukaProduk::class, ['produkId' => $produk->id]);

        $this->assertSame(0, $jumlah, 'mount() tidak boleh membaca basis data.');
    }

    public function test_props_dari_induk_dipakai_apa_adanya(): void
    {
        $produk = $this->produk();

        Livewire::test(SukaProduk::class, [
            'produkId' => $produk->id,
            'disukai' => true,
            'jumlah' => 7,
        ])
            ->assertSet('disukai', true)
            ->assertSet('jumlah', 7);
    }

    public function test_props_untuk_banyak_produk_cuma_dua_kueri(): void
    {
        // Inti anggaran: satu kueri hitung + satu kueri status untuk SEMUA
        // produk, bukan dua per produk.
        $produk = collect(range(1, 12))->map(fn () => $this->produk());

        $jumlah = 0;
        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $props = ProductLike::propsUntuk($produk, null, 'sesi-uji');

        $this->assertSame(2, $jumlah);

        // Produk tanpa suka tidak muncul di hasil agregat — itu memang cara
        // groupBy bekerja. Yang dijaga anggaran adalah jumlah KUERI, bukan
        // jumlah entri: peta yang lengkap dibuat di blade lewat `?? 0`.
        $this->assertSame([], $props['hitungan']);
        $this->assertSame([], $props['disukai']);

        // Setelah ada suka, agregat memuat entri untuk produk yang bersangkutan.
        ProductLike::alihkan($produk->first(), null, 'sesi-uji');

        $props = ProductLike::propsUntuk($produk, null, 'sesi-uji');

        $this->assertSame(1, $props['hitungan'][$produk->first()->id]);
    }

    public function test_menekan_dua_kali_membatalkan_bukan_menduplikasi(): void
    {
        $produk = $this->produk();

        Livewire::test(SukaProduk::class, ['produkId' => $produk->id])
            ->call('alihkan')
            ->assertSet('disukai', true)
            ->call('alihkan')
            ->assertSet('disukai', false)
            ->assertSet('jumlah', 0);

        $this->assertSame(0, ProductLike::query()->where('product_id', $produk->id)->count());
    }

    public function test_pengguna_terautentikasi_dicatat_sebagai_user_bukan_sesi(): void
    {
        $produk = $this->produk();
        $pengguna = \App\Models\User::factory()->create();

        $this->actingAs($pengguna);

        Livewire::test(SukaProduk::class, ['produkId' => $produk->id])->call('alihkan');

        $this->assertSame(1, ProductLike::query()->where('product_id', $produk->id)->where('user_id', $pengguna->id)->count());
        $this->assertSame(0, ProductLike::query()->where('product_id', $produk->id)->whereNotNull('session_id')->count());
    }

    public function test_batas_laju_menahan_tekanan_berulang(): void
    {
        $produk = $this->produk();

        $komponen = Livewire::test(SukaProduk::class, ['produkId' => $produk->id]);

        for ($i = 0; $i < 60; $i++) {
            $komponen->call('alihkan');
        }

        // Tekanan ke-61 tidak menambah baris.
        $komponen->call('alihkan');

        $this->assertLessThanOrEqual(60, ProductLike::query()->where('product_id', $produk->id)->count());
    }

    public function test_suka_tercatat_untuk_berbagai_produk_secara_terpisah(): void
    {
        $a = $this->produk();
        $b = $this->produk();

        Livewire::test(SukaProduk::class, ['produkId' => $a->id])->call('alihkan');

        $komponen = Livewire::test(SukaProduk::class, ['produkId' => $b->id]);
        $komponen->assertSet('disukai', false);

        $komponen->call('alihkan')->assertSet('disukai', true);

        $this->assertSame(1, ProductLike::query()->where('product_id', $a->id)->count());
        $this->assertSame(1, ProductLike::query()->where('product_id', $b->id)->count());
    }
}
