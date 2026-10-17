<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Nilai terenkripsi dicatat sebagai "[changed]", tidak pernah
            // berisi nilai lama atau nilai baru.
            $table->text('changes_json')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_activity_subjek');
            $table->index(['user_id', 'created_at'], 'idx_activity_user');
        });

        Schema::create('media_upload_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('original_filename');
            $table->unsignedBigInteger('byte_size');
            $table->string('mime_declared')->nullable();
            $table->string('mime_detected')->nullable();
            $table->string('content_hash', 64);

            $table->enum('status', ['accepted', 'rejected', 'failed', 'deduplicated']);
            $table->string('rejection_reason')->nullable();

            $table->foreignId('product_media_id')->nullable()->constrained('product_media')->nullOnDelete();
            $table->unsignedInteger('processing_ms')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['status', 'created_at'], 'idx_upload_status');
            $table->index('content_hash', 'idx_upload_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_upload_logs');
        Schema::dropIfExists('activity_logs');
    }
};
