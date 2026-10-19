<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spesifikasi teknis produk (checklist F6-04).
 *
 * Disimpan sebagai satu kolom teks JSON, bukan tabel baru. Alasannya: isinya
 * hanya dibaca bersama produknya, tidak pernah dicari, disaring, atau
 * diurutkan sendiri — dan jumlah barisnya sedikit (maksimum delapan). Tabel
 * terpisah berarti satu kueri tambahan pada setiap halaman detail produk,
 * tepat di halaman yang paling sering dibuka mesin pencari.
 *
 * Bentuk isinya larik objek terurut: [{"label_id":"Material","nilai_id":"..."}].
 * Urutannya penting karena mengikuti urutan yang disusun admin.
 *
 * dibuat_pada dipakai agar migrasi ini dapat dijalankan ulang tanpa galat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'spesifikasi_json')) {
                $table->text('spesifikasi_json')->nullable()->after('description_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'spesifikasi_json')) {
                $table->dropColumn('spesifikasi_json');
            }
        });
    }
};
