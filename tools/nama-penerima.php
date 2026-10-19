<?php

/**
 * Pembantu harness: mencetak nama penerima dari pesanan pertama yang punya
 * alamat tersimpan.
 *
 * Nama penerima berada di dalam snapshot alamat yang terenkripsi, jadi tidak
 * dapat dibaca lewat SQL biasa (json_extract menolak isinya, dan tinker tidak
 * dapat dipakai karena meneruskan kode PHP bertanda kutip lewat
 * escapeshellarg di Windows memakan kutipnya). Berkas ini dijalankan sebagai
 * berkas, bukan sebagai potongan kode, sehingga tidak ada yang perlu di-escape.
 *
 * Hanya untuk pengujian: php tools/nama-penerima.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pesanan = App\Models\Order::query()
    ->whereNotNull('shipping_address_snapshot')
    ->get()
    ->first(fn ($o): bool => trim((string) $o->namaPenerima()) !== '');

echo 'NAMAUJI:'.trim((string) $pesanan?->namaPenerima())."\n";
