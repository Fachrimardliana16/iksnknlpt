<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanda "sudah dilihat" untuk notifikasi panel.
 *
 * Notifikasi panel TIDAK disimpan sebagai baris tersendiri — isinya dihitung
 * dari kueri saat dirender (lihat App\Support\NotifikasiAdmin), supaya tidak
 * ada salinan data yang bisa basi. Yang perlu disimpan hanyalah satu hal yang
 * tidak dapat dihitung ulang: kapan admin terakhir membuka butir itu.
 *
 * Karena itu tabelnya menyimpan kunci butir (mis. 'stok-habis') dan sidik
 * keadaannya saat dilihat. Bila keadaannya berubah — varian yang habis
 * bertambah dari 2 menjadi 3 — sidiknya tidak cocok lagi dan butir itu
 * kembali dihitung sebagai BELUM dilihat. Tanda "sudah dibaca" yang tidak
 * pernah kedaluwarsa justru berbahaya: admin akan melewatkan stok habis yang
 * baru terjadi karena sudah menutup notifikasi lama.
 *
 * user_id ikut disimpan: dua admin yang berbeda tidak boleh saling
 * menghapus tanda baca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Kunci butir notifikasi, mis. 'stok-habis' atau 'pesanan-perlu-dikirim'.
            $table->string('notification_key', 60);

            // Sidik keadaan butir saat dilihat (sha1 dari isinya).
            $table->string('fingerprint', 40);

            // Denormalisasi untuk laporan/audit; bukan sumber kebenaran.
            $table->string('kind', 20)->nullable();
            $table->unsignedInteger('item_count')->default(0);

            $table->timestamp('read_at')->useCurrent();

            $table->unique(['user_id', 'notification_key'], 'idx_notif_baca_unik');
            $table->index(['user_id', 'read_at'], 'idx_notif_baca_waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
