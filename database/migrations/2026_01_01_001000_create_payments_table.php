<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->enum('provider', ['paypal', 'manual']);
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_status')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('IDR');

            $table->string('proof_path')->nullable();
            $table->longText('payload_json')->nullable();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            // Webhook ganda tidak boleh tercatat dua kali.
            $table->unique(['provider', 'provider_transaction_id'], 'idx_payments_txn_unik');
            $table->index('order_id', 'idx_payments_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
