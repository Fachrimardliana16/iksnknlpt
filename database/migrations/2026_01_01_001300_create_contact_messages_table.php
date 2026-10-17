<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Isi pesan kontak adalah PII.
            $table->text('name');
            $table->text('email');
            $table->string('email_bidx')->nullable();
            $table->text('phone')->nullable();

            $table->string('subject')->nullable();
            $table->text('message');

            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_read', 'created_at'], 'idx_contact_belum_dibaca');
            $table->index('email_bidx', 'idx_contact_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
