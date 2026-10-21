<?php

/**
 * Debug: saringan status di skenario-admin.
 *
 * Skenario melaporkan "status=pending: 0 pesanan tampil" padahal loginnya
 * dinyatakan berhasil. Perlu tahu: halaman yang diterima memang daftar
 * pesanan, atau HTML halaman masuk (yang juga 200).
 *
 * Pakai: php tools/dbg-skrip-saringan.php
 */

$basis = 'http://127.0.0.1:8100';
$jar = tempnam(sys_get_temp_dir(), 'admin-');

$minta = static function (string $url, array $o = []) use ($jar): array {
    $ch = curl_init($url);
    $s = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $o['ikut'] ?? true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $o['header'] ?? [],
    ];
    if (array_key_exists('pos', $o)) {
        $s[CURLOPT_POST] = true;
        $s[CURLOPT_POSTFIELDS] = $o['pos'];
    }
    curl_setopt_array($ch, $s);
    $raw = curl_exec($ch);
    $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['kode' => $kode, 'kepala' => substr((string) $raw, 0, $hs), 'isi' => substr((string) $raw, $hs)];
};

$pdo = new PDO('sqlite:'.dirname(__DIR__).'/database/database.sqlite');

echo "\nPERSIS SEPERTI skenario-admin.php\n\n";

// 1. Tamu dialihkan
$tamu = $minta($basis.'/admin', ['ikut' => false]);
echo 'tamu /admin: '.$tamu['kode']."\n";

// 2. Halaman login
$h = $minta($basis.'/admin/login');
preg_match_all('/wire:snapshot="([^"]+)"/', $h['isi'], $m);
$snap = html_entity_decode($m[1][0] ?? '', ENT_QUOTES);
preg_match('/name="csrf-token" content="([^"]+)"/', $h['isi'], $c);
$csrf = $c[1] ?? '';
preg_match('/XSRF-TOKEN\s+([^;\s]+)/', $h['kepala'], $x);
$xsrf = $x[1] ?? '';

echo 'snapshot: '.(strlen($snap))." chars, csrf: ".strlen($csrf).", xsrf: ".strlen($xsrf)."\n";

// 3. Login
$masuk = $minta($basis.'/livewire/update', [
    'pos' => json_encode([
        'components' => [[
            'snapshot' => $snap,
            'updates' => ['email' => 'admin@iksanknalpot.test', 'password' => 'password'],
            'calls' => [['path' => '', 'method' => 'masuk', 'params' => []]],
        ]],
    ], JSON_UNESCAPED_SLASHES),
    'ikut' => false,
    'header' => [
        'Content-Type: application/json',
        'X-Livewire: 1',
        'Accept: application/json',
        'X-CSRF-TOKEN: '.$csrf,
        'X-XSRF-TOKEN: '.$xsrf,
    ],
]);

echo 'login: HTTP '.$masuk['kode']."\n";

// Cookie jar sesudah login
$baris = array_filter(file($jar) ?: [], fn ($b) => ! str_starts_with($b, '#') && trim($b) !== '');
echo 'jar sesudah login: '.count($baris)." cookie\n";
foreach ($baris as $b) {
    $p = preg_split("/\t/", rtrim($b, "\n"));
    if (count($p) >= 7 && str_contains($p[5], 'session')) {
        echo '  sesi: '.substr((string) $p[6], 0, 18)."...\n";
    }
}

// 4. Halaman daftar
$d = $minta($basis.'/admin/pesanan?status=pending&bayar=semua&urut=terbaru');
echo 'daftar: HTTP '.$d['kode'].' '.strlen($d['isi']).' bytes, IK-: '.preg_match_all('/IK-\d{8}-\d{4}/', $d['isi'])."\n";
echo 'judul: '.(preg_match('/<title>([^<]*)<\/title>/', $d['isi'], $t) ? $t[1] : '?')."\n";

$pending = (int) $pdo->query("select count(*) from orders where status = 'pending'")->fetchColumn();
echo "db pending: $pending\n";
