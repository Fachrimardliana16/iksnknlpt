<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan product_id ke media_upload_logs.
 *
 * Sebelumnya log hanya menyimpan product_media_id, yang baru terisi setelah
 * pemrosesan BERHASIL. Akibatnya baris yang masih menunggu di antrean atau
 * ditolak tidak dapat dihubungkan ke produk mana pun, dan halaman produk tidak
 * bisa menjawab pertanyaan sederhana: "berapa gambar produk ini yang masih
 * diproses?"
 *
 * Tanpa kolom ini, satu-satunya cara menjawabnya adalah menghitung antrean
 * seluruh produk — angka yang benar untuk satu produk tetapi menyesatkan untuk
 * produk lain, dan itu lebih buruk daripada tidak menampilkan angka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_upload_logs', function (Blueprint $table): void {
            // Nullable dan tanpa foreign key yang mengikat: log adalah catatan
            // riwayat, dan menghapus produk tidak boleh menghapus bukti bahwa
            // unggahan pernah terjadi.
            $table->unsignedBigInteger('product_id')->nullable()->after('user_id');
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('media_upload_logs', function (Blueprint $table): void {
            $table->dropIndex(['product_id', 'status']);
            $table->dropColumn('product_id');
        });
    }
};
