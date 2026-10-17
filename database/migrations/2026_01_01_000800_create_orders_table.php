<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Nomor publik, acak, tidak berurutan.
            $table->string('order_number')->unique();

            // NULL = checkout tamu.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('guest_email')->nullable();          // terenkripsi
            $table->string('guest_email_bidx')->nullable();   // indeks buta
            $table->text('guest_phone')->nullable();          // terenkripsi

            $table->enum('status', ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'])
                ->default('pending');

            $table->enum('payment_status', ['unpaid', 'pending', 'paid', 'failed', 'refunded'])
                ->default('unpaid');

            $table->enum('payment_method', ['paypal', 'manual'])->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->string('currency', 3)->default('IDR');

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot alamat terenkripsi. Alamat pelanggan dapat berubah,
            // tetapi invoice lama tidak boleh ikut berubah.
            $table->text('shipping_address_snapshot');

            $table->string('shipping_courier')->nullable();
            $table->string('shipping_service')->nullable();
            $table->string('tracking_number')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_orders_status');
            $table->index(['user_id', 'created_at'], 'idx_orders_user');
            $table->index('payment_status', 'idx_orders_pembayaran');
            $table->index(['status', 'expires_at'], 'idx_orders_kedaluwarsa');
            $table->index('guest_email_bidx', 'idx_orders_guest_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
