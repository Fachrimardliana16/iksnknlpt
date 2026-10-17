<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan wajib dijaga: Coupon sebelum Order karena orders.coupon_id adalah
     * kunci asing.
     */
    public function run(): void
    {
        $this->pengamanProduksi();

        // Deterministik. Tanpa ini, dua kali seed menghasilkan data berbeda dan
        // uji idempoten menjadi tidak bermakna.
        mt_srand(20261017);

        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ProductMediaSeeder::class,
            HeroSlideSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
            ContactMessageSeeder::class,
        ]);
    }

    /**
     * migrate:fresh pada produksi menghapus seluruh data. Penghalang ini
     * disengaja, bukan sekadar peringatan di dokumentasi.
     */
    private function pengamanProduksi(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Seeder menolak berjalan di lingkungan produksi. Data akan tertimpa.'
            );
        }
    }
}
