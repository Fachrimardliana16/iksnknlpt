<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProdukDaftar;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saringan panel Produk setelah disederhanakan.
 *
 * Latar masalahnya: halaman ini dulu punya sembilan kontrol saringan — empat
 * tombol cepat (Terbit, Draf, Stok habis, Menipis) ditambah select kategori,
 * stok, publikasi, urutan, dan Bersihkan. Empat tombol itu sepenuhnya
 * redundan dengan select yang sudah ada.
 *
 * Yang diuji di sini bukan tampilannya, melainkan dua hal yang mudah rusak
 * ketika kontrol digabung:
 *
 *   1. Tautan LAMA harus tetap bekerja. ?stok=habis dipakai lonceng
 *      notifikasi dan ?terbit=draf dipakai halaman Kategori. Menghapus
 *      tombolnya tidak boleh ikut mematikan tautan itu.
 *   2. Select pengganti harus MENUNJUKKAN saringan yang sedang menyala.
 *      Kalau ia tetap bertuliskan "Semua kondisi" sementara daftar sudah
 *      tersaring, admin membaca daftar pendek sebagai "stoknya memang
 *      kosong" — bukan "saya sedang menyaring".
 *
 * CATATAN CARA MENGUJI: memeriksa teks judul produk di HTML TIDAK bisa
 * dipakai untuk membuktikan saringan bekerja, karena teks yang sama muncul
 * sebagai <option> di select Kondisi ("Ada stok", "Terbit", "Stok habis").
 * Yang diperiksa karena itu adalah ISI PAGINATORNYA — daftar produk yang
 * benar-benar dikirim ke tampilan.
 */
class SaringanProdukSederhanaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    private function produk(string $judul, bool $terbit, int $stok): Product
    {
        $kategori = Category::query()->first()
            ?? Category::query()->create([
                'name_id' => 'Knalpot',
                'slug' => 'knalpot',
                'display_order' => 1,
                'is_active' => true,
            ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'UJI-'.strtoupper(substr(md5($judul), 0, 6)),
            'title_id' => $judul,
            'slug' => 'uji-'.substr(md5($judul), 0, 8),
            'base_price' => 150000,
            'weight_gram' => 2000,
            'is_published' => $terbit,
            'display_order' => 1,
        ]);

        $produk->variants()->create([
            'sku_variant' => $produk->sku.'-A',
            'variant_name_id' => 'Standar',
            'price_adjustment' => 0,
            'stock_quantity' => $stok,
            'reserved_quantity' => 0,
            'is_active' => true,
            'display_order' => 1,
        ]);

        return $produk;
    }

    /** Judul produk yang benar-benar lolos saringan, diambil dari paginator. */
    private function judulTampil(array $parameter = []): array
    {
        return Livewire::test(ProdukDaftar::class, $parameter)
            ->viewData('daftar')
            ->pluck('title_id')
            ->all();
    }

    public function test_tautan_lama_stok_habis_masih_menyaring(): void
    {
        $this->actingAs($this->admin());

        $this->produk('Barang Kosong', true, 0);
        $this->produk('Barang Tersedia', true, 20);

        $this->assertSame(['Barang Kosong'], $this->judulTampil(['stok' => 'habis']));
    }

    public function test_tautan_lama_terbit_draf_masih_menyaring(): void
    {
        $this->actingAs($this->admin());

        $this->produk('Masih Draf', false, 5);
        $this->produk('Sudah Terbit', true, 5);

        $this->assertSame(['Masih Draf'], $this->judulTampil(['terbit' => 'draf']));
    }

    public function test_select_kondisi_menunjuk_saringan_yang_datang_dari_tautan(): void
    {
        $this->actingAs($this->admin());

        $this->produk('Sebuah Produk', true, 0);

        // Inilah inti penyederhanaan: satu kontrol harus mencerminkan saringan
        // yang menyala. Tanpa sinkronisasi di mount(), selectnya tetap
        // "Semua kondisi" padahal daftar sudah tersaring stok habis.
        Livewire::test(ProdukDaftar::class, ['stok' => 'habis'])
            ->assertSet('kondisi', 'habis');

        Livewire::test(ProdukDaftar::class, ['terbit' => 'draf'])
            ->assertSet('kondisi', 'draf');
    }

    public function test_memilih_kondisi_menulis_ke_properti_yang_dipakai_kueri(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ProdukDaftar::class)
            ->set('kondisi', 'menipis')
            ->assertSet('stok', 'menipis')
            ->assertSet('terbit', 'semua');

        Livewire::test(ProdukDaftar::class)
            ->set('kondisi', 'draf')
            ->assertSet('terbit', 'draf')
            ->assertSet('stok', 'semua');
    }

    public function test_kondisi_semua_mengosongkan_kedua_saringan(): void
    {
        $this->actingAs($this->admin());

        // Tanpa ini, memilih "Semua kondisi" setelah menyaring draf akan
        // menyisakan terbit=draf dan daftar tetap tersembunyi.
        Livewire::test(ProdukDaftar::class, ['terbit' => 'draf'])
            ->assertSet('terbit', 'draf')
            ->set('kondisi', 'semua')
            ->assertSet('terbit', 'semua')
            ->assertSet('stok', 'semua');
    }

    public function test_nilai_kondisi_ngawur_dari_url_tidak_merusak_daftar(): void
    {
        $this->actingAs($this->admin());

        // #[Url] mengisi properti langsung dari query string; nilai asing
        // harus jatuh ke "semua", bukan menghasilkan daftar kosong tanpa sebab.
        Livewire::test(ProdukDaftar::class, ['terbit' => 'ngawur', 'stok' => 'ngawur'])
            ->assertSet('terbit', 'semua')
            ->assertSet('stok', 'semua')
            ->assertSet('kondisi', 'semua');
    }

    public function test_empat_tombol_redundan_sudah_hilang(): void
    {
        $this->actingAs($this->admin());

        $html = Livewire::test(ProdukDaftar::class)->html();

        // Tautan cepat lama tidak boleh ada lagi. Kalau salah satunya kembali,
        // kontrolnya bertambah tanpa menambah kemampuan.
        $this->assertStringNotContainsString('?terbit=terbit', $html);
        $this->assertStringNotContainsString('?stok=menipis', $html);
        $this->assertStringNotContainsString('?stok=habis', $html);
    }

    public function test_baris_saringan_flexbox_bukan_tumpukan(): void
    {
        $this->actingAs($this->admin());

        $html = Livewire::test(ProdukDaftar::class)->html();

        // Regresi yang pernah terjadi: `flex-wrap` dipakai tanpa `flex`.
        // flex-wrap hanya mengatur pembungkusan, bukan display — tanpa `flex`
        // seluruh tombol dan select jatuh ke display:block dan menumpuk
        // vertikal. Tidak ada galat, hanya tampilan yang berantakan.
        $this->assertStringContainsString('class="flex flex-wrap items-center gap-2"', $html);
    }
}
