<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemilik KOL untuk kupon.
 *
 * Kupon yang sama dapat dipakai banyak orang lewat tautan berbagi. Supaya
 * kinerja tiap KOL dapat dilihat, kupon perlu tahu siapa yang membagikannya —
 * tanpa itu, kode KOL-A dan KOL-B yang menunjuk kupon yang sama tidak dapat
 * dibedakan di laporan.
 *
 * Kolom baru sengaja nullable. Baris kupon yang sudah ada tidak punya pemilik
 * dan tetap sah; kupon lama tinggal berjalan seperti sekarang sampai
 * pemiliknya diisi. Membuat kolom ini NOT NULL akan menggagalkan migrasi pada
 * basis data yang sudah berisi kupon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('kol_nama', 120)->nullable()->after('code');
            $table->string('kol_slug', 120)->nullable()->after('kol_nama');
            $table->string('kol_kontak', 120)->nullable()->after('kol_slug');
        });

        // Slug unik hanya untuk baris yang punya slug. Dua kupon tanpa pemilik
        // sama-sama NULL, dan indeks unik pada SQLite memperlakukan NULL sebagai
        // bukan duplikat — jadi keduanya boleh hidup berdampingan.
        DB::statement('CREATE UNIQUE INDEX coupons_kol_slug_unique ON coupons (kol_slug) WHERE kol_slug IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS coupons_kol_slug_unique');

        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn(['kol_nama', 'kol_slug', 'kol_kontak']);
        });
    }
};
