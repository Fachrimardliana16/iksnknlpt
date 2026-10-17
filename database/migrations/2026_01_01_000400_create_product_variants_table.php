<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku_variant')->unique();
            $table->string('variant_name_id');
            $table->string('variant_name_en');
            $table->decimal('price_adjustment', 12, 2)->default(0);

            // stock_quantity adalah stok fisik. reserved_quantity menahan stok
            // untuk pesanan yang belum lunas, sehingga stok dapat dijual =
            // stock_quantity - reserved_quantity.
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);

            $table->unsignedInteger('weight_gram')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active'], 'idx_variants_product');
            $table->index('stock_quantity', 'idx_variants_stok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
