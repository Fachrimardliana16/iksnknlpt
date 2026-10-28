<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suka produk — sinyal minat yang TIDAK berarti keranjang.
 *
 * Dipisah dari cart_items dengan sengaja: menekan suka adalah tindakan termurah
 * yang bisa dilakukan pembeli, dan mencampurnya ke keranjang membuat "produk
 * yang disukai" tampil sebagai "barang yang tertahan". Keduanya menjawab
 * pertanyaan berbeda saat memilih produk mana yang perlu ditinjau.
 *
 * Satu baris per produk per pengunjung. Pengunjung tanpa akun dikenali lewat
 * session_id — sama seperti keranjang tamu — supaya menyukai dua kali tidak
 * menjadi dua baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_likes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Dua-duanya nullable: yang punya akun memakai user_id, tamu memakai
            // session_id. Tidak ada yang memakai keduanya sekaligus.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->timestamps();

            // Satu baris per produk per pemilik. UNIQUE sekaligus mencegah
            // duplikat dan membuat "sudah disukai" dapat diperiksa lewat
            // EXISTS, bukan COUNT lalu perbandingan.
            $table->unique(['product_id', 'user_id'], 'idx_product_like_user');
            $table->unique(['product_id', 'session_id'], 'idx_product_like_sesi');

            $table->index('product_id', 'idx_product_like_produk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_likes');
    }
};
