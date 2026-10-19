<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

/**
 * 24 produk realistis. Tidak ada angka karangan: seluruh harga, stok, dan
 * jumlah ditulis eksplisit di tabel di bawah supaya hasil seed deterministik
 * dan dapat diperiksa ulang.
 */
class ProductSeeder extends Seeder
{
    /** [nama, kategori, harga, varian-varian] */
    private const PRODUK = [
        ['Knalpot Full System NMAX V2 Titanium', 'Full System', 4250000, [['Titanium', 0, 12], ['Stainless', -750000, 8], ['Carbon', 850000, 5]]],
        ['Knalpot Full System PCX 160 Stainless', 'Full System', 3750000, [['Stainless', 0, 10], ['Titanium', 900000, 4]]],
        ['Knalpot Full System Aerox 155 Racing', 'Full System', 4650000, [['Carbon', 0, 6], ['Titanium', 350000, 0]]],
        ['Knalpot Full System Vario 160', 'Full System', 3450000, [['Stainless', 0, 14], ['Titanium', 780000, 6], ['Hitam Doff', -320000, 11]]],

        ['Slip-On Racing Vario 160', 'Slip-On', 1250000, [['Hitam Doff', 0, 22], ['Carbon', 275000, 9]]],
        ['Slip-On NMAX Standar Bunyi', 'Slip-On', 985000, [['Stainless', 0, 30], ['Hitam Doff', -50000, 18]]],
        ['Slip-On Beat FI Street', 'Slip-On', 675000, [['Hitam Doff', 0, 41], ['Stainless', 165000, 13]]],
        ['Slip-On Mio M3 Daily', 'Slip-On', 620000, [['Hitam Doff', 0, 25], ['Stainless', 140000, 17]]],
        ['Slip-On CB150R Sport', 'Slip-On', 1450000, [['Titanium', 0, 7], ['Carbon', 400000, 3]]],

        ['Header Stainless Beat FI', 'Header', 450000, [['Standar', 0, 34], ['Besar', 110000, 9]]],
        ['Header Stainless Vario 125', 'Header', 495000, [['Standar', 0, 28], ['Besar', 125000, 12]]],
        ['Header Racing Jupiter MX', 'Header', 585000, [['Standar', 0, 16], ['Besar', 145000, 7]]],

        ['Muffler Carbon R15', 'Muffler', 1850000, [['Carbon', 0, 9], ['Titanium', 450000, 4]]],
        ['Muffler Titanium CBR150R', 'Muffler', 2150000, [['Titanium', 0, 6], ['Carbon', -250000, 8]]],
        ['Muffler Standar NMAX Original', 'Muffler', 780000, [['Standar', 0, 0], ['Replika', -95000, 0]]],

        ['Knalpot Racing Jupiter MX Drag', 'Racing', 2450000, [['Carbon', 0, 5], ['Titanium', 600000, 2]]],
        ['Knalpot Racing Sonic 150R', 'Racing', 2250000, [['Stainless', 0, 4], ['Titanium', 520000, 2]]],
        ['Knalpot Racing Satria F150', 'Racing', 2380000, [['Titanium', 0, 3], ['Carbon', 500000, 0]]],
        ['Knalpot Racing Mio Drag 200cc', 'Racing', 1750000, [['Stainless', 0, 7], ['Carbon', 385000, 3]]],

        ['Bracket Knalpot Universal', 'Aksesoris', 95000, [['Standar', 0, 120], ['Besi Tebal', 35000, 64]]],
        ['Peredam Bunyi Baffle Universal', 'Aksesoris', 75000, [['Kecil', 0, 85], ['Besar', 15000, 60]]],
        ['Per Paku Knalpot Stainless', 'Aksesoris', 45000, [['Set 6', 0, 150], ['Set 12', 25000, 90]]],
        ['Selang Fleksibel Knalpot 40mm', 'Aksesoris', 65000, [['40mm', 0, 55], ['50mm', 18000, 40]]],
        ['Sarung Tangan Bengkel Anti Panas', 'Aksesoris', 55000, [['Standar', 0, 70], ['Panjang', 22000, 38]]],
    ];

    /** Produk yang sengaja seluruh variannya nol, untuk menguji badge Habis. */
    private const SKU_STOK_NOL = 'IK-MF-1003';

    /** Produk yang sengaja disembunyikan dari katalog. */
    private const DISEMBUNYIKAN = ['muffler-standar-nmax-original', 'sarung-tangan-bengkel-anti-panas'];

    /** Prefiks SKU per kategori. */
    private const PREFIKS = [
        'Full System' => 'FS',
        'Slip-On' => 'SO',
        'Header' => 'HD',
        'Muffler' => 'MF',
        'Racing' => 'RC',
        'Aksesoris' => 'AK',
    ];

    public function run(): void
    {
        $kategori = Category::query()->get()->keyBy('name_id');

        /** @var array<string, int> $nomorPerKategori */
        $nomorPerKategori = [];

        foreach (self::PRODUK as $i => $baris) {
            [$nama, $namaKategori, $harga, $varianList] = $baris;

            /** @var Category|null $kat */
            $kat = $kategori->get($namaKategori);

            if ($kat === null) {
                continue;
            }

            $nomorPerKategori[$namaKategori] = ($nomorPerKategori[$namaKategori] ?? 1000) + 1;
            $sku = 'IK-'.(self::PREFIKS[$namaKategori] ?? 'XX').'-'.$nomorPerKategori[$namaKategori];

            $slug = \Illuminate\Support\Str::slug($nama);

            $produk = Product::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $kat->id,
                    'sku' => $sku,
                    'title_id' => $nama,
                    'title_en' => $nama,
                    'excerpt_id' => $this->ringkasan($namaKategori).' untuk pemakaian harian.',
                    'excerpt_en' => $this->ringkasan($namaKategori).' for daily use.',
                    'description_id' => $this->deskripsi($nama, $namaKategori),
                    'description_en' => $this->deskripsi($nama, $namaKategori, 'en'),
                    'base_price' => $harga,
                    'weight_gram' => $this->berat($namaKategori),
                    'is_featured' => $i < 8,
                    'is_published' => ! in_array($slug, self::DISEMBUNYIKAN, true),
                    'published_at' => in_array($slug, self::DISEMBUNYIKAN, true) ? null : now(),
                ],
            );

            foreach ($varianList as $urutan => [$namaVarian, $selisih, $stok]) {
                $stokAkhir = $produk->sku === self::SKU_STOK_NOL ? 0 : $stok;

                // reserved_quantity sengaja TIDAK ikut ditulis di sini.
                //
                // Kolom itu bukan data katalog, melainkan catatan pesanan yang
                // sedang menahan stok. Menyetelnya ke 0 pada setiap seed berarti
                // menghapus catatan tahanan milik pesanan yang masih berjalan:
                // stok tampak bebas padahal barangnya sudah terikat, dan barang
                // yang sama bisa terjual dua kali. Seeder ini hanya menetapkan
                // stok fisik; tahanan diatur oleh OrderSeeder dan oleh
                // StatusPesananService saat pesanan berpindah status.
                ProductVariant::query()->updateOrCreate(
                    ['sku_variant' => $produk->sku.'-V'.($urutan + 1)],
                    [
                        'product_id' => $produk->id,
                        'variant_name_id' => $namaVarian,
                        'variant_name_en' => $namaVarian,
                        'price_adjustment' => $selisih,
                        'stock_quantity' => $stokAkhir,
                        'display_order' => $urutan,
                        'is_active' => true,
                    ],
                );
            }
        }

        $this->command?->info('ProductSeeder: '.Product::query()->count().' produk, '.ProductVariant::query()->count().' varian.');
    }

    private function ringkasan(string $kategori): string
    {
        return match ($kategori) {
            'Full System' => 'Set lengkap header sampai muffler, siap pasang tanpa ubah rangka',
            'Slip-On' => 'Muffler pengganti pada pipa standar, pemasangan tanpa potong',
            'Header' => 'Pipa depan stainless dengan aliran lebih lancar',
            'Muffler' => 'Tabung peredam dengan volume tetap di bawah ambang bising',
            'Racing' => 'Peruntukan lintasan, tanpa pengurangan bunyi',
            default => 'Pelengkap pemasangan dan perawatan',
        };
    }

    private function deskripsi(string $nama, string $kategori, string $locale = 'id'): string
    {
        $baris = match ($kategori) {
            'Full System' => [
                'Pipa dibentuk dari mandrel bending sehingga diameter dalam tetap sama di seluruh tikungan.',
                'Sambungan memakai flange dan paku stainless, tanpa lem penyegel.',
                'Dudukan memakai karet peredam getaran, tidak mengubah titik las rangka.',
            ],
            'Slip-On' => [
                'Diameter masuk disesuaikan dengan pipa standar, jadi tidak perlu penyesuaian.',
                'Peredam dalam dibagi dua ruang untuk menurunkan nada tanpa menyumbat aliran.',
                'Berat lebih ringan dibanding muffler bawaan.',
            ],
            'Header' => [
                'Panjang pipa diukur agar torsi puncak tetap di putaran bawah sampai menengah.',
                'Material stainless tahan karat untuk pemakaian harian.',
            ],
            'Muffler' => [
                'Tingkat kebisingan diukur sebelum keluar dari bengkel.',
                'Baffle dapat dilepas untuk pemakaian di lintasan.',
            ],
            'Racing' => [
                'Dirancang untuk pemakaian lintasan, bukan jalan raya.',
                'Tanpa peredam, sehingga wajib memakai pelindung telinga.',
            ],
            default => [
                'Dibuat dari bahan yang sama dengan unit utama.',
                'Cocok untuk sebagian besar sepeda motor dengan pipa 38 sampai 51 mm.',
            ],
        };

        $judul = $locale === 'en'
            ? 'Designed and assembled in our own workshop.'
            : 'Dirancang dan dirakit di bengkel kami sendiri.';

        return $judul."\n\n".implode(' ', $baris);
    }

    private function berat(string $kategori): int
    {
        return match ($kategori) {
            'Full System' => 4200,
            'Slip-On' => 2600,
            'Header' => 1400,
            'Muffler' => 3100,
            'Racing' => 2300,
            default => 600,
        };
    }
}
