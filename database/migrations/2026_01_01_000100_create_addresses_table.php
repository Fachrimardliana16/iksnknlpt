<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('label')->default('Rumah');

            // Seluruh isi alamat adalah PII, terenkripsi di tingkat aplikasi.
            $table->text('recipient_name');
            $table->text('phone_number');
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();

            $table->string('city');
            $table->string('state_province');
            $table->string('postal_code');

            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('user_id', 'idx_addresses_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
