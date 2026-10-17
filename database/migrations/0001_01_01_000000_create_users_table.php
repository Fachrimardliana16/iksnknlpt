<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identifier publik. Rute dan API memakai ULID, bukan id, untuk
            // mencegah enumerasi ID dan IDOR.
            $table->ulid('ulid')->unique();

            $table->text('name');                 // terenkripsi
            $table->text('email');                // terenkripsi
            $table->string('email_bidx')->unique(); // HMAC-SHA256, satu-satunya kolom email yang dapat dicari

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            $table->text('phone_number')->nullable();   // terenkripsi

            $table->enum('role', ['customer', 'admin', 'super_admin'])->default('customer');

            $table->text('two_factor_secret')->nullable();          // terenkripsi
            $table->text('two_factor_recovery_codes')->nullable();  // terenkripsi
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip_hash')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('role', 'idx_users_role');
            $table->index('is_blocked', 'idx_users_blocked');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
