<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('sku')->unique();
            $table->string('title_id');
            $table->string('title_en');
            $table->string('slug')->unique();
            $table->text('excerpt_id')->nullable();
            $table->text('excerpt_en')->nullable();
            $table->longText('description_id');
            $table->longText('description_en');

            $table->decimal('base_price', 12, 2);
            $table->unsignedInteger('weight_gram')->default(1000);

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('category_id', 'idx_products_category');
            $table->index(['is_featured', 'is_published'], 'idx_products_unggulan');
            $table->index(['is_published', 'created_at'], 'idx_products_daftar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
