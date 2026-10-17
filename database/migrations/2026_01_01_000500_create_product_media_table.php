<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['image', 'video', 'audio', 'model_3d'])->default('image');

            $table->string('original_filename')->nullable();
            $table->string('path_original')->nullable();
            $table->string('path_thumb')->nullable();   // 320x240
            $table->string('path_card')->nullable();    // 640x480
            $table->string('path_full')->nullable();    // 1600x1200
            $table->string('path_og')->nullable();      // 1200x630

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('mime_detected')->nullable();
            $table->string('content_hash', 64)->nullable();

            $table->string('alt_text_id')->nullable();
            $table->string('alt_text_en')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'display_order'], 'idx_media_urutan');
            $table->index(['product_id', 'is_primary'], 'idx_media_utama');
            $table->index('content_hash', 'idx_media_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
