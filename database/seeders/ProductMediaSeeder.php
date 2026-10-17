<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Support\GambarContoh;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Gambar produk contoh.
 *
 * Berkas dibuat saat seeding dengan GD dan ditulis ke storage/app/public,
 * bukan disimpan di repositori. Produk terakhir sengaja dibiarkan tanpa gambar
 * supaya peringatan dashboard pada PRD 7.1 dapat diuji.
 */
class ProductMediaSeeder extends Seeder
{
    /** Nama produk yang dibiarkan tanpa gambar. */
    private const TANPA_GAMBAR = 'Dudukan knalpot sudah termasuk';

    private const WARNA = ['#101014', '#141a20', '#1c1416', '#141c18', '#1a1a14'];

    public function run(): void
    {
        Storage::disk('public')->makeDirectory('products');

        $produk = Product::query()->orderBy('id')->get();

        $tanpaGambar = $produk->get(3);

        $jumlahBerkas = 0;

        foreach ($produk as $item) {
            if ($tanpaGambar !== null && $item->id === $tanpaGambar->id) {
                // Dibiarkan tanpa media dengan sengaja.
                continue;
            }

            $sudahAda = ProductMedia::query()->where('product_id', $item->id)->count();

            if ($sudahAda > 0) {
                continue;
            }

            // 2 sampai 5 gambar per produk, deterministik dari id.
            $jumlah = 2 + ($item->id % 4);

            $folder = 'products/'.$item->ulid;

            for ($n = 1; $n <= $jumlah; $n++) {
                $hasil = GambarContoh::buat(
                    folder: $folder,
                    namaBerkas: 'img-'.$n.'.webp',
                    teks: $item->title_id.' #'.$n,
                    warna: self::WARNA[($item->id + $n) % count(self::WARNA)],
                );

                ProductMedia::query()->create([
                    'product_id' => $item->id,
                    'type' => 'image',
                    'original_filename' => 'contoh-'.$n.'.webp',
                    'path_original' => null,
                    'path_thumb' => $hasil['paths']['thumb'],
                    'path_card' => $hasil['paths']['card'],
                    'path_full' => $hasil['paths']['full'],
                    'path_og' => $hasil['paths']['og'],
                    'width' => $hasil['width'],
                    'height' => $hasil['height'],
                    'byte_size' => $hasil['byte_size'],
                    'mime_detected' => 'image/webp',
                    'content_hash' => $hasil['hash'],
                    'alt_text_id' => $item->title_id.' tampak samping '.$n,
                    'alt_text_en' => $item->title_en.' side view '.$n,
                    'is_primary' => $n === 1,
                    'display_order' => $n - 1,
                ]);

                $jumlahBerkas += 4;
            }
        }

        $this->command?->info(sprintf(
            'ProductMediaSeeder: %d baris media, %d berkas gambar, %d produk tanpa gambar.',
            ProductMedia::query()->count(),
            $jumlahBerkas,
            Product::query()->doesntHave('media')->count(),
        ));
    }
}
