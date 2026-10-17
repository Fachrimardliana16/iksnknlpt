<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshot saat transaksi. Harga produk dapat berubah setelah
            // pesanan dibuat, dan invoice lama tidak boleh ikut berubah.
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku');

            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('weight_gram')->default(1000);

            $table->timestamp('created_at')->nullable();

            $table->index('order_id', 'idx_order_items_order');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'created_at'], 'idx_osh_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
    }
};
