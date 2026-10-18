<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan media_upload_logs dengan nilai yang benar-benar ditulis kode.
 *
 * Skema aslinya membatasi status pada ['accepted','rejected','failed',
 * 'deduplicated']. Sementara itu, dan sejak awal, pekerja antrean menulis
 * 'processed' untuk unggahan yang berhasil, dan halaman produk menghitung
 * 'queued' serta 'processing'. Empat dari lima nilai yang dipakai aplikasi
 * tidak ada di dalam batasan itu.
 *
 * Akibatnya bukan sekadar satu baris gagal: SELURUH catatan unggahan yang
 * berhasil tidak pernah tersimpan. Kegagalannya pun tidak terlihat, karena
 * penulisan log sengaja tidak boleh menggagalkan pemrosesan berkas — jadi
 * pengguna melihat gambar berhasil muncul, sementara tabel jejak auditnya
 * kosong. Checklist F3-13 mensyaratkan tabel itu terisi untuk setiap unggahan,
 * termasuk alasan penolakan yang spesifik.
 *
 * Dua kolom lain juga dilonggarkan karena alasan yang sama: `content_hash` dan
 * `byte_size` dideklarasikan wajib, padahal penolakan paling awal terjadi
 * sebelum hash dihitung dan sebelum ukuran diketahui — tepat pada kasus yang
 * paling perlu tercatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Batasan CHECK pada SQLite tidak dapat diubah di tempat. Tabel dibangun
        // ulang dengan definisi yang benar, lalu isinya dipindahkan. Cara ini
        // juga bekerja di MySQL dan PostgreSQL, jadi tidak ada cabang kode per
        // mesin basis data.
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('media_upload_logs_baru');

        Schema::create('media_upload_logs_baru', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('original_filename');
            // Ukuran dan hash boleh kosong: penolakan paling awal terjadi
            // sebelum keduanya diketahui.
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('mime_declared')->nullable();
            $table->string('mime_detected')->nullable();
            $table->string('content_hash', 64)->nullable();

            // Nilai yang benar-benar ditulis aplikasi.
            $table->enum('status', [
                'queued',       // menunggu di antrean
                'processing',   // sedang diproses pekerja
                'processed',    // berhasil, media terbentuk
                'rejected',     // ditolak pemeriksaan, dengan alasan
                'deduplicated', // isi berkas identik, dilewati
                'failed',       // gagal setelah beberapa percobaan
            ]);
            $table->string('rejection_reason')->nullable();

            $table->foreignId('product_media_id')->nullable()->constrained('product_media')->nullOnDelete();
            $table->unsignedInteger('processing_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        // Status lama 'accepted' tidak ada lagi di daftar baru; nilainya
        // dipetakan ke 'processed' supaya baris lama tetap sah.
        DB::statement("
            INSERT INTO media_upload_logs_baru
                (id, ulid, user_id, product_id, original_filename, byte_size,
                 mime_declared, mime_detected, content_hash, status,
                 rejection_reason, product_media_id, processing_ms, created_at)
            SELECT
                id, ulid, user_id, product_id, original_filename, byte_size,
                mime_declared, mime_detected, content_hash,
                CASE WHEN status = 'accepted' THEN 'processed' ELSE status END,
                rejection_reason, product_media_id, processing_ms, created_at
            FROM media_upload_logs
        ");

        Schema::drop('media_upload_logs');
        Schema::rename('media_upload_logs_baru', 'media_upload_logs');

        // Indeks dibuat setelah penukaran: nama indeks bersifat global di
        // SQLite, sehingga membuatnya lebih dulu bertabrakan dengan indeks
        // bernama sama milik tabel lama.
        Schema::table('media_upload_logs', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'idx_upload_status');
            $table->index('content_hash', 'idx_upload_hash');
            $table->index(['product_id', 'status'], 'idx_upload_produk');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('media_upload_logs_lama');

        Schema::create('media_upload_logs_lama', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();

            $table->string('original_filename');
            $table->unsignedBigInteger('byte_size');
            $table->string('mime_declared')->nullable();
            $table->string('mime_detected')->nullable();
            $table->string('content_hash', 64);

            $table->enum('status', ['accepted', 'rejected', 'failed', 'deduplicated']);
            $table->string('rejection_reason')->nullable();

            $table->foreignId('product_media_id')->nullable()->constrained('product_media')->nullOnDelete();
            $table->unsignedInteger('processing_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        // Baris yang tidak dapat dinyatakan dalam batasan lama dibuang, bukan
        // dipaksa masuk dengan nilai yang salah.
        DB::statement("
            INSERT INTO media_upload_logs_lama
                (id, ulid, user_id, product_id, original_filename, byte_size,
                 mime_declared, mime_detected, content_hash, status,
                 rejection_reason, product_media_id, processing_ms, created_at)
            SELECT
                id, ulid, user_id, product_id, original_filename,
                COALESCE(byte_size, 0), mime_declared, mime_detected,
                COALESCE(content_hash, ''), 'accepted',
                rejection_reason, product_media_id, processing_ms, created_at
            FROM media_upload_logs
            WHERE status IN ('rejected', 'failed', 'deduplicated')
        ");

        Schema::drop('media_upload_logs');
        Schema::rename('media_upload_logs_lama', 'media_upload_logs');

        Schema::table('media_upload_logs', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'idx_upload_status');
            $table->index('content_hash', 'idx_upload_hash');
        });

        Schema::enableForeignKeyConstraints();
    }
};
