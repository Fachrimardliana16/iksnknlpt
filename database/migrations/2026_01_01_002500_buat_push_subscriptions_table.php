<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langganan web push perangkat admin.
 *
 * Satu admin bisa punya beberapa perangkat: komputer kantor, ponsel, tablet.
 * Setiap perangkat itu satu baris, karena notifikasi dikirim ke PERANGKAT,
 * bukan ke pengguna — kalau digabung, satu perangkat yang mati membuat yang
 * lain tidak menerima kabar.
 *
 * `endpoint` unik karena satu peramban yang mendaftar dua kali tidak boleh
 * menghasilkan dua notifikasi untuk hal yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Alamat layanan push milik peramban. Unik: satu peramban yang
            // berlangganan dua kali tetap satu baris.
            $table->text('endpoint');

            // Kunci enkripsi konten. Disimpan sebagai teks biasa: keduanya
            // sendiri-sendiri tidak berguna tanpa endpoint, dan keduanya harus
            // dikirim apa adanya ke layanan push.
            $table->string('public_key', 255);
            $table->string('auth_token', 255);

            // "chrome", "firefox", "safari" — dipai untuk pesan bantuan di panel
            // ketika satu jenis peramban tidak mendukung notifikasi.
            $table->string('peramban', 40)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('endpoint', 'idx_push_endpoint');
            $table->index(['user_id', 'last_used_at'], 'idx_push_pengguna');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
