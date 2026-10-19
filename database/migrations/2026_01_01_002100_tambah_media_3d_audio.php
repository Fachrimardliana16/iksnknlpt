<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom media 3D dan audio (Fase 2: P2-01, P2-02).
 *
 * Tabel product_media sudah mengenal tipe `model_3d` dan `audio` sejak F1,
 * tetapi tidak punya tempat menyimpan jalur berkasnya. Akibatnya berkas seperti
 * itu hanya dapat disimpan di kolom `path_original` milik gambar, dan pemisahan
 * jalur thumb/card/full/og yang dipakai pemrosesan gambar tidak berlaku.
 *
 * `durasi_detik` dipakai pemutar audio untuk menampilkan panjang klip sebelum
 * berkasnya dimuat, dan `keterangan` dipakai pemutar 3D sebagai judul adegan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_media', 'path_media')) {
                $table->string('path_media')->nullable()->after('path_og');
            }

            if (! Schema::hasColumn('product_media', 'durasi_detik')) {
                $table->unsignedSmallInteger('durasi_detik')->nullable()->after('byte_size');
            }

            if (! Schema::hasColumn('product_media', 'keterangan')) {
                $table->string('keterangan', 160)->nullable()->after('alt_text_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            foreach (['path_media', 'durasi_detik', 'keterangan'] as $kolom) {
                if (Schema::hasColumn('product_media', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
