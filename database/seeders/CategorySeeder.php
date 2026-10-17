<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $kategori = [
            ['Full System', 'Full System', 'Set knalpot lengkap dari header sampai muffler.', 'Complete exhaust set from header to muffler.'],
            ['Slip-On', 'Slip-On', 'Muffler pengganti langsung pada pipa standar.', 'Replacement muffler on the standard pipe.'],
            ['Header', 'Header', 'Pipa depan dan kolektor.', 'Front pipe and collector.'],
            ['Muffler', 'Muffler', 'Tabung peredam suara.', 'Silencer body.'],
            ['Racing', 'Racing', 'Peruntukan lintasan dan kompetisi.', 'Track and competition use.'],
            ['Aksesoris', 'Accessories', 'Bracket, peredam, dan pelengkap.', 'Brackets, baffles, and fittings.'],
        ];

        foreach ($kategori as $i => [$id, $en, $deskId, $deskEn]) {
            Category::query()->updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($id)],
                [
                    'name_id' => $id,
                    'name_en' => $en,
                    'description_id' => $deskId,
                    'description_en' => $deskEn,
                    'display_order' => $i,
                    'is_active' => true,
                ],
            );
        }

        $this->command?->info('CategorySeeder: '.Category::query()->count().' kategori.');
    }
}
