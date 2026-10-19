<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pembeli pada keranjang (checklist F7-03).
 *
 * Catatan kini dapat diisi di halaman keranjang, bukan hanya di checkout. Saat
 * checkout dibuat, nilai yang tersimpan di keranjang menjadi nilai awal kolom
 * di formulir — jadi pembeli yang sudah menulis "titip di pos keamanan" tidak
 * perlu menulisnya lagi.
 *
 * Disimpan di keranjang, bukan di sesi, karena keranjang tamu bertahan melewati
 * penutupan peramban (baris carts memakai session_id, bukan sesi PHP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            if (! Schema::hasColumn('carts', 'note')) {
                $table->string('note', 500)->nullable()->after('coupon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            if (Schema::hasColumn('carts', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
