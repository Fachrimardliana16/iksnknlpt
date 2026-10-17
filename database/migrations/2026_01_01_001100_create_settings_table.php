<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            // Nilai disimpan sebagai teks. Tipe menentukan cara render di panel.
            $table->text('value')->nullable();

            $table->string('group_name')->default('general');
            $table->enum('type', ['text', 'textarea', 'number', 'boolean', 'image', 'email', 'url', 'select', 'json'])
                ->default('text');

            $table->string('label')->nullable();
            $table->text('options_json')->nullable();

            // Enkripsi per baris. Kredensial pembayaran terenkripsi, nama toko tidak.
            $table->boolean('is_encrypted')->default(false);

            // Nilai yang boleh dikirim ke sisi depan, agar pengaturan publik
            // dapat dimuat dengan satu kueri.
            $table->boolean('is_public')->default(false);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('group_name', 'idx_settings_grup');
            $table->index('is_public', 'idx_settings_publik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
