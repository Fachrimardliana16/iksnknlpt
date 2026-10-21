<?php

/**
 * Bootstrap untuk PHPUnit.
 *
 * Satu tugas: membuang cache konfigurasi SEBELUM aplikasi dinyalakan.
 *
 * `php artisan config:cache` menulis bootstrap/cache/config.php, dan berkas itu
 * mengabaikan seluruh <env> di phpunit.xml — termasuk DB_DATABASE. Akibatnya
 * test berjalan di atas database/database.sqlite, berkas KERJA, bukan berkas
 * test. Di mesin ini hal itu pernah menghapus isi basis data pengembangan.
 *
 * Penghapusan harus di sini, bukan di tests/TestCase.php: TestCase berjalan
 * SESUDAH config dimuat, jadi menghapus berkas di sana tidak berpengaruh.
 */

$cache = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($cache) && getenv('APP_ENV') !== 'production') {
    @unlink($cache);
}

require __DIR__.'/../vendor/autoload.php';
