<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Spesifikasi teknis untuk seluruh produk contoh (checklist F6-04).
 *
 * Dipisah dari ProductSeeder supaya seeder produk tetap sependek daftar
 * harganya, dan supaya spesifikasi dapat diisi ulang sendiri tanpa menyentuh
 * harga atau stok.
 *
 * Isinya diambil dari bentuk produk, bukan dikarang per produk: dua puluh
 * empat baris tabel spesifikasi yang berbeda-beda hanya akan jadi tempat salah
 * ketik, sementara yang perlu diuji di sini adalah bentuknya — tabel terurut,
 * dua bahasa, dan tetap kosong pada produk yang belum diisi.
 *
 * Idempoten: menulis ulang kolom yang sama setiap kali dijalankan, dan
 * menormalkan kondisi awal supaya dua kali seed menghasilkan baris yang sama
 * persis.
 */
class SpesifikasiProdukSeeder extends Seeder
{
    /** Bahan menurut kategori, dipakai pada baris Material. */
    private const BAHAN = [
        'Full System' => ['Titanium grade 1', 'Titanium grade 1'],
        'Slip-On' => ['Stainless 304', 'Stainless 304'],
        'Header' => ['Stainless 304', 'Stainless 304'],
        'Muffler' => ['Stainless 304 berpulir', 'Corrugated stainless 304'],
        'Racing' => ['Stainless 304', 'Stainless 304'],
        'Aksesoris' => ['Baja galvanis', 'Galvanised steel'],
    ];

    /** Diameter pipa menurut kategori, dipakai pada baris Diameter. */
    private const DIAMETER = [
        'Full System' => ['51 mm', '51 mm'],
        'Slip-On' => ['38 mm', '38 mm'],
        'Header' => ['32 mm', '32 mm'],
        'Muffler' => ['51 mm masuk, 51 mm keluar', '51 mm in, 51 mm out'],
    ];

    public function run(): void
    {
        // Kondisi awal dinormalkan: produk yang sengaja diuji tanpa spesifikasi
        // dibersihkan lebih dulu, sehingga menjalankan seeder dua kali tidak
        // menyisakan sisa dari jalannya yang pertama.
        Product::query()
            ->whereIn('slug', self::TANPA_SPESIFIKASI)
            ->update(['spesifikasi_json' => null]);

        $terisi = 0;

        Product::query()
            ->with('category')
            ->orderBy('id')
            ->each(function (Product $produk) use (&$terisi): void {
                if (in_array($produk->slug, self::TANPA_SPESIFIKASI, true)) {
                    return;
                }

                $kategori = $produk->category?->name_id ?? 'Aksesoris';

                $produk->update(['spesifikasi_json' => json_encode(
                    $this->baris($kategori, $produk),
                    JSON_UNESCAPED_UNICODE,
                )]);

                $terisi++;
            });

        $this->command?->info(sprintf(
            'SpesifikasiProdukSeeder: %d produk berisi spesifikasi, %d sengaja dibiarkan kosong.',
            $terisi,
            count(self::TANPA_SPESIFIKASI),
        ));
    }

    /** Produk yang dibiarkan tanpa spesifikasi, untuk menguji keadaan kosong. */
    private const TANPA_SPESIFIKASI = [
        'peredam-bunyi-baffle-universal',
        'sarung-tangan-bengkel-anti-panas',
    ];

    /**
     * @return array<int, array<string, string>>
     */
    private function baris(string $kategori, Product $produk): array
    {
        [$bahanId, $bahanEn] = self::BAHAN[$kategori] ?? ['Baja galvanis', 'Galvanised steel'];
        [$diameterId, $diameterEn] = self::DIAMETER[$kategori] ?? ['Universal', 'Universal'];

        $berat = number_format($produk->weight_gram / 1000, 2, ',', '.').' kg';
        $beratEn = number_format($produk->weight_gram / 1000, 2, '.', '').' kg';

        return [
            ['label_id' => 'Material', 'label_en' => 'Material', 'nilai_id' => $bahanId, 'nilai_en' => $bahanEn],
            ['label_id' => 'Diameter', 'label_en' => 'Diameter', 'nilai_id' => $diameterId, 'nilai_en' => $diameterEn],
            ['label_id' => 'Berat', 'label_en' => 'Weight', 'nilai_id' => $berat, 'nilai_en' => $beratEn],
            ['label_id' => 'Kategori', 'label_en' => 'Category', 'nilai_id' => $kategori, 'nilai_en' => $kategori],
            ['label_id' => 'Garansi', 'label_en' => 'Warranty', 'nilai_id' => '6 bulan', 'nilai_en' => '6 months'],
        ];
    }
}
