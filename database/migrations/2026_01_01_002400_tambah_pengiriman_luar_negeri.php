<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengiriman luar negeri pada pesanan.
 *
 * Kolom yang ditambahkan, dan sebabnya satu per satu:
 *
 *   shipping_country    — alamat saja tidak cukup: ongkir internaasional
 *                        dihitung manual dan perlu negara dalam huruf latin
 *                        yang seragam ("ID", "SG"), bukan "singapore",
 *                        "S'pore", "Singapore, SG" yang harus ditebak.
 *   shipping_currency   — mata uang yang dipilih pembeli. Isi pesanan tetap
 *                        IDR; ini menyimpan PILIHAN TAMPILAN, supaya konversi
 *                        tidak perlu dihitung ulang dan tidak berubah diam-diam.
 *   shipping_quoted_at  — kapan ongkir internasional disepakati. Pesanan luar
 *                        negeri menunggu pricelist ini sebelum diproses.
 *   shipping_is_intl    — penanda yang dijelaskan di bawah.
 *
 * IS_INTERNATIONAL adalah penanda terpisah, bukan "kode negara !== 'ID'".
 * Alasannya pesanan lama: seluruh basis data sekarang punya country ID atau
 * kosong, dan menghitung ulang berarti risiko salah kelompok. Penanda ini
 * diisi sekali pada saat pesanan dibuat dan tidak pernah dihitung ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'shipping_country')) {
                $table->string('shipping_country', 2)->nullable()->after('shipping_courier');
            }

            if (! Schema::hasColumn('orders', 'shipping_currency')) {
                $table->string('shipping_currency', 3)->default('IDR')->after('shipping_country');
            }

            if (! Schema::hasColumn('orders', 'shipping_is_intl')) {
                $table->boolean('shipping_is_intl')->default(false)->after('shipping_currency');
            }

            if (! Schema::hasColumn('orders', 'shipping_quoted_at')) {
                $table->timestamp('shipping_quoted_at')->nullable()->after('shipping_is_intl');
            }

            if (! Schema::hasColumn('orders', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('shipping_quoted_at');
            }

            $table->index('shipping_is_intl', 'idx_orders_intl');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['shipping_country', 'shipping_currency', 'shipping_is_intl', 'shipping_quoted_at', 'printed_at'] as $kolom) {
                if (Schema::hasColumn('orders', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
