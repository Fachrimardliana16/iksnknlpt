<?php

namespace Tests\Feature;

use App\Livewire\Admin\LoncengNotifikasi;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\NotifikasiAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lencana lonceng notifikasi panel (status "sudah dilihat").
 *
 * Yang diuji adalah janji yang mudah rusak tanpa ketahuan:
 *
 * 1. Lencana menghitung butir yang BELUM dilihat. Membuka satu butir
 *    mengurangi angkanya satu.
 * 2. Tanda baca tidak berlaku selamanya. Bila isi butirnya berubah — satu
 *    varian lagi kehabisan stok — butir itu kembali dihitung sebagai belum
 *    dilihat. Tanpa ini, admin akan melewatkan stok habis yang baru terjadi
 *    karena sudah menutup notifikasi lama.
 * 3. Tanda baca milik masing-masing admin. Admin A membuka notifikasi tidak
 *    boleh menghapus lencana admin B.
 */
class LoncengNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $surel = 'admin@contoh.test'): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => $surel]);
    }

    /** Satu varian kehabisan stok = satu butir 'stok-habis'. */
    private function varian(array $atribut = []): ProductVariant
    {
        $produk = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        return ProductVariant::factory()->create($atribut + [
            'product_id' => $produk->id,
            'is_active' => true,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_lencana_hanya_menghitung_butir_yang_belum_dilihat(): void
    {
        $this->actingAs($this->admin());

        // Dua butir berbeda: satu habis, satu menipis.
        $this->varian(['stock_quantity' => 0]);
        $this->varian(['stock_quantity' => 2]);

        $this->assertSame(2, NotifikasiAdmin::jumlahBelumDilihat());

        Livewire::test(LoncengNotifikasi::class)
            ->assertViewHas('belum', 2)
            ->call('bukaButir', 'stok-habis')
            ->assertViewHas('belum', 1);

        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat());

        $this->assertDatabaseHas('notification_reads', [
            'notification_key' => 'stok-habis',
            'kind' => 'stok',
        ]);
    }

    public function test_butir_tetap_tampil_setelah_dilihat_tetapi_tanpa_lencana(): void
    {
        $this->actingAs($this->admin());

        $this->varian(['stock_quantity' => 0]);

        Livewire::test(LoncengNotifikasi::class)
            ->call('bukaButir', 'stok-habis')
            ->assertViewHas('belum', 0)
            // Butirnya masih ada di daftar — hanya statusnya berubah.
            ->assertViewHas('butir', fn ($butir) => $butir->count() === 1
                && $butir->first()['dilihat'] === true
                && $butir->first()['dilihat_pada'] !== null);
    }

    public function test_butir_kembali_belum_dilihat_bila_isinya_berubah(): void
    {
        $this->actingAs($this->admin());

        $this->varian(['stock_quantity' => 0]);

        NotifikasiAdmin::tandaiDilihat('stok-habis');
        $this->assertSame(0, NotifikasiAdmin::jumlahBelumDilihat());

        // Satu varian lagi kehabisan stok: teksnya berubah, jadi tanda baca
        // yang lama tidak boleh lagi dianggap sah.
        $this->varian(['stock_quantity' => 0]);

        $butir = NotifikasiAdmin::semua()->firstWhere('kunci', 'stok-habis');

        $this->assertSame(2, $butir['jumlah']);
        $this->assertFalse($butir['dilihat']);
        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat());
    }

    public function test_tanda_baca_terpisah_antar_admin(): void
    {
        $saya = $this->admin('saya@contoh.test');
        $lain = $this->admin('lain@contoh.test');

        $this->varian(['stock_quantity' => 0]);

        $this->actingAs($saya);
        NotifikasiAdmin::tandaiDilihat('stok-habis');
        $this->assertSame(0, NotifikasiAdmin::jumlahBelumDilihat());

        $this->actingAs($lain);
        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat());
    }

    public function test_tandai_semua_mengosongkan_lencana(): void
    {
        $this->actingAs($this->admin());

        $this->varian(['stock_quantity' => 0]);
        $this->varian(['stock_quantity' => 1]);

        Livewire::test(LoncengNotifikasi::class)
            ->assertViewHas('belum', 2)
            ->call('tandaiSemua')
            ->assertViewHas('belum', 0);
    }

    public function test_jumlah_dilaporkan_penuh_walau_contohnya_dibatasi(): void
    {
        $this->actingAs($this->admin());

        // 25 varian habis: lebih banyak daripada batas 20 contoh yang diambil
        // untuk keterangan "rinci". Angka yang dilaporkan harus 25, bukan 20.
        for ($i = 0; $i < 25; $i++) {
            $this->varian(['stock_quantity' => 0]);
        }

        $butir = NotifikasiAdmin::semua()->firstWhere('kunci', 'stok-habis');

        $this->assertSame(25, $butir['jumlah']);
        $this->assertStringContainsString('25 varian kehabisan stok', $butir['teks']);
    }

    public function test_banyak_butir_yang_sudah_dibaca_tidak_muncul_lagi(): void
    {
        $this->actingAs($this->admin());

        // Empat butir sekaligus: stok habis, stok menipis, pesanan siap kirim,
        // dan keranjang. Semuanya ditandai, lalu komponen dirender ULANG
        // berkali-kali — meniru lonceng yang menyegarkan diri sendiri. Angkanya
        // harus tetap 0: butir yang sudah dibaca tidak boleh muncul kembali.
        $this->varian(['stock_quantity' => 0]);
        $this->varian(['stock_quantity' => 1]);

        $this->assertSame(2, NotifikasiAdmin::jumlahBelumDilihat());

        // Penanda: sidik butir TIDAK ikut berubah hanya karena waktu berjalan
        // (tidak ada "2 menit lalu" di dalamnya). Kalau ikut berubah, tanda
        // baca akan gugur sendiri setiap render dan lencana tidak pernah nol.
        $sidikAwal = NotifikasiAdmin::semua()->pluck('sidik', 'kunci')->all();

        NotifikasiAdmin::tandaiSemuaDilihat();
        $this->assertSame(0, NotifikasiAdmin::jumlahBelumDilihat());

        for ($i = 0; $i < 3; $i++) {
            $this->travel(9)->minutes();

            Livewire::test(LoncengNotifikasi::class)->assertViewHas('belum', 0);

            $this->assertSame(0, NotifikasiAdmin::jumlahBelumDilihat());
            $this->assertSame(
                $sidikAwal,
                NotifikasiAdmin::semua()->pluck('sidik', 'kunci')->all(),
                'sidik butir berubah sendiri seiring waktu: tanda baca akan gugur tiap render',
            );
        }
    }

    public function test_tamu_tidak_punya_tanda_baca(): void
    {
        $this->varian(['stock_quantity' => 0]);

        // Tanpa pengguna masuk, semua butir dihitung belum dilihat dan tidak
        // ada yang bisa ditandai — bukan menebak milik siapa.
        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat());
        $this->assertFalse(NotifikasiAdmin::tandaiDilihat('stok-habis'));
        $this->assertDatabaseCount('notification_reads', 0);
    }
}
