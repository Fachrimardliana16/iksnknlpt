<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Support\GambarContoh;
use Illuminate\Database\Seeder;

class HeroSlideSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'judulId' => 'Diukur sebelum dipotong',
                'judulEn' => 'Measured before it is cut',
                'subId' => 'Setiap pipa dibentuk dengan mandrel bending, sehingga diameter dalam tetap sama di seluruh tikungan.',
                'subEn' => 'Every pipe is mandrel bent, so the inner diameter stays the same through every bend.',
                'tombolId' => 'Lihat Katalog',
                'tombolEn' => 'View Catalog',
                'urlUtama' => '/produk',
                'tombol2Id' => 'Konsultasi via WhatsApp',
                'tombol2En' => 'Ask on WhatsApp',
                'urlKedua' => null,
                'aktif' => true,
            ],
            [
                'judulId' => 'Full system untuk harian',
                'judulEn' => 'Full system for daily riding',
                'subId' => 'Torsi puncak tetap di putaran bawah sampai menengah, tanpa mengubah titik las rangka.',
                'subEn' => 'Peak torque stays in the low to mid range, without touching the frame welds.',
                'tombolId' => 'Lihat Full System',
                'tombolEn' => 'See Full System',
                'urlUtama' => '/produk?kategori=full-system',
                'tombol2Id' => null,
                'tombol2En' => null,
                'urlKedua' => null,
                'aktif' => true,
            ],
            [
                'judulId' => 'Unit lintasan',
                'judulEn' => 'Track unit',
                'subId' => 'Tanpa peredam, dirancang untuk pemakaian lintasan saja.',
                'subEn' => 'No silencer, designed for track use only.',
                'tombolId' => 'Lihat Seri Racing',
                'tombolEn' => 'See Racing Series',
                'urlUtama' => '/produk?kategori=racing',
                'tombol2Id' => null,
                'tombol2En' => null,
                'urlKedua' => null,
                'aktif' => false,
            ],
        ];

        foreach ($slides as $i => $slide) {
            $berkas = 'hero/slide-'.($i + 1).'.webp';

            if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($berkas)) {
                $hasil = GambarContoh::buat(
                    folder: 'hero',
                    namaBerkas: 'slide-'.($i + 1).'.webp',
                    teks: $slide['judulId'],
                    warna: '#0d0d0f',
                    turunans: ['full' => [1920, 1080], 'mobile' => [900, 1200], 'card' => [640, 480]],
                );

                $berkas = $hasil['paths']['full'];
            }

            HeroSlide::query()->updateOrCreate(
                ['media_path' => $berkas],
                [
                    'title_id' => $slide['judulId'],
                    'title_en' => $slide['judulEn'],
                    'subtitle_id' => $slide['subId'],
                    'subtitle_en' => $slide['subEn'],
                    'media_type' => 'image',
                    'media_path_mobile' => str_replace('slide-'.($i + 1).'.webp', 'slide-'.($i + 1).'-mobile.webp', $berkas),
                    'poster_path' => null,
                    'focal_point_x' => 0.5,
                    'focal_point_y' => 0.45,
                    'primary_button_label_id' => $slide['tombolId'],
                    'primary_button_label_en' => $slide['tombolEn'],
                    'primary_button_url' => $slide['urlUtama'],
                    'secondary_button_label_id' => $slide['tombol2Id'],
                    'secondary_button_label_en' => $slide['tombol2En'],
                    'secondary_button_url' => $slide['urlKedua'],
                    'starts_at' => null,
                    'ends_at' => null,
                    'display_order' => $i,
                    'is_active' => $slide['aktif'],
                ],
            );
        }

        $this->command?->info('HeroSlideSeeder: '.HeroSlide::query()->count().' slide.');
    }
}
