<?php

/* Audit cakupan akses: setiap rute publik dan panel, status jawabannya + header. */

$basis = 'http://127.0.0.1:8100';

$rutes = [
    ['/', 'Beranda'],
    ['/produk', 'Katalog'],
    ['/produk?q=knalpot&min=100000&max=5000000&urut=price_asc', 'Katalog tersaring'],
    ['/produk/knalpot-full-system-nmax-v2-titanium', 'Detail produk'],
    ['/produk/tidak-ada-xyz', 'Detail produk tidak ada'],
    ['/keranjang', 'Keranjang'],
    ['/checkout', 'Checkout kosong'],
    ['/lacak', 'Lacak'],
    ['/tentang', 'Tentang'],
    ['/kontak', 'Kontak'],
    ['/halaman/syarat-ketentuan', 'Syarat'],
    ['/halaman/kebijakan-privasi', 'Privasi'],
    ['/halaman/tidak-ada', 'Halaman tidak ada'],
    ['/lang/en', 'Ganti bahasa'],
    ['/lang/xx', 'Bahasa tak dikenal'],
    ['/admin', 'Panel tanpa sesi'],
    ['/admin/login', 'Masuk panel'],
    ['/admin/pesanan', 'Pesanan tanpa sesi'],
    ['/admin/keamanan', 'Keamanan tanpa sesi'],
    ['/bukti-bayar/01abc', 'Bukti bayar tanpa tanda tangan'],
    ['/storage/products/tidak-ada.webp', 'Berkas storage tidak ada'],
];

$ch = curl_init();
echo "\nCAKUPAN RUTE — status, waktu, ukuran, dan header\n\n";
printf("%-52s %6s %8s %10s %s\n", 'Rute', 'Kode', 'Waktu', 'Bita', 'Catatan');

foreach ($rutes as [$jalur, $nama]) {
    curl_setopt_array($ch, [
        CURLOPT_URL => $basis.$jalur,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => false,
    ]);

    $mulai = microtime(true);
    $isi = (string) curl_exec($ch);
    $ms = (int) round((microtime(true) - $mulai) * 1000);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipe = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    $catatan = '';

    if ($kode >= 500) {
        $catatan = 'GALAT SERVER';
    } elseif ($kode === 200 && str_contains($isi, 'Whoops')) {
        $catatan = 'halaman galat terender';
    } elseif ($kode === 200 && stripos($isi, 'exception') !== false && stripos($isi, 'stack trace') !== false) {
        $catatan = 'jejak galat bocor';
    } elseif ($ms > 1000) {
        $catatan = 'lambat';
    }

    printf("%-52s %6d %7dms %9d %s\n", $jalur, $kode, $ms, strlen($isi), $catatan);
}

curl_close($ch);
