<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAQ (pertanyaan yang sering diajukan) untuk halaman depan.
 *
 * Dua bahasa, seperti kategori dan produk: pertanyaan Indonesia wajib, versi
 * Inggris boleh kosong dan jatuh ke versi Indonesia saat ditampilkan. Tanpa
 * aturan itu, situs dua bahasa akan menampilkan kolom kosong kepada pembeli
 * luar negeri — lebih buruk daripada menampilkan teks Indonesia.
 *
 * `group_key` dipakai untuk mengelompokkan di beranda (Pembayaran, Pengiriman,
 * Produk, Lainnya). Dikelompokkan karena delapan pertanyaan berderet tanpa
 * judul lebih sulit dipindai daripada empat kelompok pendek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('group_key', 30)->default('umum');

            $table->string('question_id', 300);
            $table->string('question_en', 300)->nullable();

            $table->text('answer_id');
            $table->text('answer_en')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Tampil di beranda (bawah CTA) dan/atau di halaman /faq tersendiri.
            $table->boolean('tampil_di_beranda')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'display_order'], 'idx_faq_aktif_urutan');
            $table->index('group_key', 'idx_faq_kelompok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
