<?php

namespace Database\Seeders;

use App\Enums\TipeKupon;
use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $kupon = [
            [
                'code' => 'IKSAN10',
                'type' => TipeKupon::Persen,
                'value' => 10,
                'min_spend' => 500_000,
                'max_discount' => 500_000,
                'quota' => 100,
                'used_count' => 0,
                'starts_at' => null,
                'ends_at' => now()->addMonths(6),
                'is_active' => true,
            ],
            [
                'code' => 'GRATISONGKIR',
                'type' => TipeKupon::GratisOngkir,
                'value' => 0,
                'min_spend' => 1_000_000,
                'max_discount' => 200_000,
                'quota' => null,
                'used_count' => 0,
                'starts_at' => null,
                'ends_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                // Sengaja kedaluwarsa, untuk menguji validasi di keranjang.
                'code' => 'LEBARANLALU',
                'type' => TipeKupon::Nominal,
                'value' => 150_000,
                'min_spend' => 1_500_000,
                'max_discount' => null,
                'quota' => 50,
                'used_count' => 50,
                'starts_at' => now()->subMonths(6),
                'ends_at' => now()->subMonths(4),
                'is_active' => true,
            ],
        ];

        foreach ($kupon as $data) {
            Coupon::query()->updateOrCreate(['code' => $data['code']], $data);
        }

        $this->command?->info('CouponSeeder: '.Coupon::query()->count().' kupon.');
    }
}
