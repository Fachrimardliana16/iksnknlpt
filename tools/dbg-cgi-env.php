<?php

/**
 * Probe: apa yang diterima php-cgi vs apa yang dibaca Laravel.
 *
 * Kegagalan saringan status hanya muncul lewat nginx+php-cgi (port 8100);
 * lewat `php -S` (8101) dan lewat kernel Laravel langsung, filternya benar.
 * Yang perlu diketahui: variabel server mana yang berbeda.
 *
 * Pakai: php tools/dbg-cgi-env.php
 */

echo "== \$_SERVER ==\n";
foreach (['REQUEST_URI', 'SCRIPT_NAME', 'PATH_INFO', 'PHP_SELF', 'QUERY_STRING', 'SCRIPT_FILENAME', 'DOCUMENT_URI'] as $k) {
    echo str_pad($k, 18).': '.($_SERVER[$k] ?? 'TIDAK ADA')."\n";
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$req = Illuminate\Support\Facades\Request::capture();

echo "\n== Laravel Request ==\n";
echo 'path():    '.$req->path()."\n";
echo 'getRequestUri(): '.$req->getRequestUri()."\n";
echo 'query("status"): '.(string) $req->query('status', 'KOSONG')."\n";

echo "\n== Livewire #[Url] ==\n";
echo 'SupportQueryParams membaca query string dari request utama: '.$req->getUri()."\n";
