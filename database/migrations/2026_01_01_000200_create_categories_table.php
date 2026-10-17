<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('name_id');
            $table->string('name_en');
            $table->text('description_id')->nullable();
            $table->text('description_en')->nullable();

            $table->string('slug')->unique();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'display_order'], 'idx_categories_aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
