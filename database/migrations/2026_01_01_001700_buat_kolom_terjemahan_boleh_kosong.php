<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom terjemahan yang belum tentu ada isinya dibuat nullable.
 *
 * Kolom-kolom ini dideklarasikan NOT NULL saat skema pertama dibuat, padahal
 * seluruh kode aplikasi sudah memperlakukannya sebagai boleh kosong:
 * `filled($product->title_en)`, `$product->title_en ?: null`, dan pemeriksaan
 * serupa ada di model, view, dan seeder.
 *
 * Akibat pertentangan itu, panel admin gagal menyimpan kategori atau produk
 * yang belum diterjemahkan — dan terjemahan Inggris adalah pekerjaan yang
 * wajar ditunda, bukan syarat untuk menyimpan draf. Galat yang muncul adalah
 * kegagalan batasan basis data, bukan pesan yang bisa ditindaklanjuti pengguna.
 *
 * Yang diubah hanya kolom yang memang boleh kosong menurut kode aplikasi.
 * Kolom yang wajib (nama Indonesia, harga, kategori) dibiarkan NOT NULL: di
 * situlah batasan basis data bermanfaat sebagai jaring pengaman terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->change();
            $table->text('description_id')->nullable()->change();
            $table->text('description_en')->nullable()->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('title_en')->nullable()->change();
            $table->text('description_id')->nullable()->change();
            $table->text('description_en')->nullable()->change();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('variant_name_en')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Kolom diisi nilai cadangan lebih dulu. Mengembalikan NOT NULL pada
        // kolom yang berisi NULL akan gagal, dan mengosongkan nilainya berarti
        // membuang data yang sah.
        foreach ([
            ['categories', 'name_en'],
            ['categories', 'description_id'],
            ['categories', 'description_en'],
            ['products', 'title_en'],
            ['products', 'description_id'],
            ['products', 'description_en'],
            ['product_variants', 'variant_name_en'],
        ] as [$tabel, $kolom]) {
            \Illuminate\Support\Facades\DB::table($tabel)
                ->whereNull($kolom)
                ->update([$kolom => '']);
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('name_en')->nullable(false)->change();
            $table->text('description_id')->nullable(false)->change();
            $table->text('description_en')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('title_en')->nullable(false)->change();
            $table->text('description_id')->nullable(false)->change();
            $table->text('description_en')->nullable(false)->change();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->string('variant_name_en')->nullable(false)->change();
        });
    }
};
