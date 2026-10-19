<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Anggaran 15 kueri per halaman (checklist F11-02, PRD 13.1).
 *
 * Kueri dihitung pada MUAT KEDUA, bukan yang pertama.
 *
 * Alasannya penting: muat pertama juga mengisi cache pengaturan, kategori, dan
 * daftar produk. Menghitung muat pertama berarti mengukur pekerjaan yang hanya
 * terjadi sekali, bukan beban yang ditanggung setiap pengunjung.
 *
 * Di lingkungan uji, cache memakai penyimpanan array yang dikosongkan pada
 * setiap test. Karena itu muat pertama di sini berperan sebagai pengisi, dan
 * muat kedua sebagai objek ukur.
 */
class AnggaranKueriTest extends TestCase
{
    use RefreshDatabase;

    private function isiData(int $jumlahProduk = 12): void
    {
        $kategori = Category::query()->create([
            'name_id' => 'Knalpot',
            'slug' => 'knalpot',
            'display_order' => 1,
            'is_active' => true,
        ]);

        for ($i = 0; $i < $jumlahProduk; $i++) {
            $produk = Product::query()->create([
                'category_id' => $kategori->id,
                'sku' => 'UJI-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'title_id' => 'Produk uji '.$i,
                'slug' => 'produk-uji-'.$i,
                'base_price' => 150000,
                'weight_gram' => 2000,
                'is_published' => true,
                'is_featured' => $i < 4,
                'display_order' => $i,
            ]);

            ProductVariant::query()->create([
                'product_id' => $produk->id,
                'sku_variant' => 'UJI-'.$i.'-A',
                'variant_name_id' => 'Standar',
                'price_adjustment' => 0,
                'stock_quantity' => 20,
                'reserved_quantity' => 0,
                'is_active' => true,
                'display_order' => 1,
            ]);
        }
    }

    private function hitungKueri(string $jalur): int
    {
        $jumlah = 0;

        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $this->get($jalur)->assertOk();

        return $jumlah;
    }

    /**
     * Menjalankan halaman dua kali: sekali untuk mengisi cache, sekali untuk
     * mengukur. Angka yang dikembalikan adalah muat kedua.
     */
    private function hitungMuatKedua(string $jalur): int
    {
        // Muat pertama: mengisi cache pengaturan, kategori, dan produk.
        $this->get($jalur)->assertOk();

        // Memastikan yang diukur benar-benar memakai cache. Pengaturan
        // menyimpan hasilnya di memori statis per permintaan, jadi permintaan
        // berikutnya tetap membaca dari cache tanpa perlu dikosongkan.
        return $this->hitungKueri($jalur);
    }

    public function test_beranda_di_bawah_anggaran(): void
    {
        $this->isiData();

        $jumlah = $this->hitungMuatKedua('/');

        $this->assertLessThanOrEqual(
            15,
            $jumlah,
            "Beranda memakai $jumlah kueri, melebihi anggaran 15.",
        );
    }

    public function test_daftar_produk_di_bawah_anggaran(): void
    {
        $this->isiData();

        $jumlah = $this->hitungMuatKedua('/produk');

        $this->assertLessThanOrEqual(
            15,
            $jumlah,
            "Daftar produk memakai $jumlah kueri, melebihi anggaran 15.",
        );
    }

    public function test_catatan_angka_tiap_halaman(): void
    {
        $this->isiData();

        $angka = [];

        foreach (['/' => 'Beranda', '/produk' => 'Daftar produk', '/keranjang' => 'Keranjang', '/kontak' => 'Kontak'] as $jalur => $nama) {
            $angka[$nama] = $this->hitungMuatKedua($jalur);
        }

        // Bukan pemeriksaan lulus/gagal, melainkan catatan angka yang dapat
        // dibandingkan antar perubahan. Ambangnya tetap 15.
        $laporan = implode(', ', array_map(
            fn (string $nama, int $n): string => "$nama=$n",
            array_keys($angka),
            array_values($angka),
        ));

        fwrite(STDERR, "\n  Kueri per halaman (muat kedua): $laporan\n");

        $this->assertTrue(true);
    }

    public function test_jumlah_kueri_tidak_tumbuh_bersama_jumlah_produk(): void
    {
        // Ini inti anggarannya: biaya kueri harus tetap, bukan tumbuh mengikuti
        // jumlah baris. Kalau tumbuh, halaman akan makin lambat seiring toko
        // bertambah besar — dan itu tidak terlihat pada data contoh yang kecil.
        $this->isiData(6);
        $kecil = $this->hitungMuatKedua('/produk');

        // Ditambah produk baru di kategori yang SAMA.
        //
        // Versi pertama test ini membuat kategori kedua bernama 'knalpot' juga,
        // dan langsung menabrak batasan unik pada slug — kegagalan test, bukan
        // kegagalan aplikasi. Menambah produk ke kategori yang sudah ada justru
        // menguji hal yang benar: yang diukur adalah jumlah BARIS, bukan jumlah
        // kategori.
        $kategori = Category::query()->first();

        for ($i = 100; $i < 124; $i++) {
            $produk = Product::query()->create([
                'category_id' => $kategori->id,
                'sku' => 'TAMBAH-'.$i,
                'title_id' => 'Produk tambahan '.$i,
                'slug' => 'produk-tambahan-'.$i,
                'base_price' => 175000,
                'weight_gram' => 2500,
                'is_published' => true,
                'display_order' => $i,
            ]);

            ProductVariant::query()->create([
                'product_id' => $produk->id,
                'sku_variant' => 'TAMBAH-'.$i.'-A',
                'variant_name_id' => 'Standar',
                'price_adjustment' => 0,
                'stock_quantity' => 10,
                'reserved_quantity' => 0,
                'is_active' => true,
                'display_order' => 1,
            ]);
        }

        Cache::flush();

        $besar = $this->hitungMuatKedua('/produk');

        $this->assertLessThanOrEqual(
            $kecil + 3,
            $besar,
            "Kueri tumbuh dari $kecil menjadi $besar saat produk bertambah. "
            .'Seharusnya tetap, karena daftar memakai eager loading.',
        );
    }
}
