<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('code')->unique();
            $table->enum('type', ['percent', 'fixed', 'free_shipping']);

            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('min_spend', 12, 2)->default(0);
            $table->decimal('max_discount', 12, 2)->nullable();

            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'idx_coupons_berlaku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
