<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // IP hanya disimpan sebagai hash SHA-256, bukan alamat mentah.
            $table->string('ip_hash', 64);

            $table->enum('event_type', [
                'login_failed',
                'login_success',
                'brute_force_blocked',
                'ip_blocked',
                'unauthorized_access',
                'rate_limit_exceeded',
                'two_factor_failed',
                'upload_rejected',
                'admin_action',
            ]);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('email_attempted')->nullable();  // terenkripsi
            $table->string('route')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload_summary')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at'], 'idx_sec_kejadian');
            $table->index(['ip_hash', 'created_at'], 'idx_sec_ip');
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64);
            $table->string('email_bidx')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at');
            $table->timestamp('locked_until')->nullable();

            $table->unique(['ip_hash', 'email_bidx'], 'idx_login_attempts_unik');
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64)->unique();
            $table->string('reason');
            $table->timestamp('blocked_until');
            $table->timestamp('created_at')->useCurrent();

            $table->index('blocked_until', 'idx_blocked_sampai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('security_logs');
    }
};
