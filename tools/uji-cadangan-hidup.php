<?php

/**
 * Uji hidup halaman Cadangan.
 *
 * Curl biasa tidak cukup: halaman dirender Livewire dan tombol buat cadangan
 * hanya berhasil bagi Super Admin. Yang diperiksa di sini adalah jalur HTTP
 * sungguhan — tamu ditolak, halaman terbuka untuk admin, dan menu sidebar
 * benar-benar memuat tautannya.
 */

$asas = 'http://127.0.0.1:8100';

function minta(string $url, array $cookies = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIE => implode('; ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($cookies), array_values($cookies))),
    ]);
    $respons = curl_exec($ch);
    $panjang = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kepala = substr((string) $respons, 0, $panjang);

    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $kepala, $c, PREG_SET_ORDER);
    preg_match('#^Location:\s*(.+)$#mi', $kepala, $l);

    return [
        'kode' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'isi' => substr((string) $respons, $panjang),
        'kepala' => $kepala,
        'cookies' => $c,
        'tuju' => trim($l[1] ?? ''),
    ];
}

function lwUpdate(string $asas, array $jar, string $snapshot, string $token, array $calls, array $updates = []): array
{
    $ch = curl_init($asas.'/livewire/update');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            '_token' => $token,
            'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Livewire: 1', 'Accept: application/json',
            'X-CSRF-TOKEN: '.$token, 'X-XSRF-TOKEN: '.$token],
        CURLOPT_COOKIE => implode('; ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($jar), array_values($jar))),
        CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30,
    ]);
    $respons = curl_exec($ch);
    $panjang = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kepala = substr((string) $respons, 0, $panjang);

    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $kepala, $c, PREG_SET_ORDER);

    return ['isi' => substr((string) $respons, $panjang), 'cookies' => $c];
}

$lulus = 0;
$gagal = 0;

function periksa(string $label, bool $syarat, string $bukti): void
{
    global $lulus, $gagal;

    if ($syarat) { $lulus++; echo "  OK    {$label} — {$bukti}\n"; return; }
    $gagal++; echo "  GAGAL {$label} — {$bukti}\n";
}

echo "Halaman Cadangan:\n";

// ── Tamu harus dialihkan ke login ─────────────────────────────────────
$tamu = minta($asas.'/admin/cadangan');
periksa('Tamu dialihkan ke login', $tamu['kode'] === 302 && str_contains($tamu['tuju'], '/admin/login'),
    'HTTP '.$tamu['kode'].' → '.($tamu['tuju'] ?: '(tanpa Location)'));

// ── Login ─────────────────────────────────────────────────────────────
$jar = [];
$login = minta($asas.'/admin/login', $jar);
foreach ($login['cookies'] as $c) { $jar[$c[1]] = $c[2]; }

if (! preg_match('/wire:snapshot="([^"]+)"/', $login['isi'], $m)) {
    fwrite(STDERR, "Snapshot Livewire tidak ditemukan di /admin/login.\n");
    exit(1);
}
$snapshot = html_entity_decode($m[1], ENT_QUOTES);
preg_match('/name="csrf-token" content="([^"]+)"/', $login['isi'], $t);
$token = $t[1] ?? '';

$sandi = getenv('IKSAN_ADMIN_PASSWORD') ?: 'password';

$r = lwUpdate($asas, $jar, $snapshot, $token,
    [['path' => '', 'method' => 'masuk', 'params' => []]],
    ['email' => 'admin@iksanknalpot.test', 'password' => $sandi],
);
foreach ($r['cookies'] as $c) { $jar[$c[1]] = $c[2]; }

$d = json_decode($r['isi'], true);
$masuk = str_contains((string) ($d['redirect'] ?? ''), '/admin')
    || str_contains(json_encode($d), '/admin');
periksa('Login panel berhasil', $masuk, 'redirect ke '.($d['redirect'] ?? '(tidak ada)'));

// ── Halaman cadangan setelah login ────────────────────────────────────
$hal = minta($asas.'/admin/cadangan', $jar);
periksa('/admin/cadangan', $hal['kode'] === 200, 'HTTP '.$hal['kode']);

foreach ([
    'Cadangan Basis Data' => 'judul halaman',
    'Cadangkan sekarang' => 'tombol buat cadangan',
    'Muat ulang' => 'tombol muat ulang',
    'Salinan di server' => 'daftar salinan',
    'penjadwal' => 'catatan soal penjadwal',
    'storage\app' => 'folder tujuan (dengan pemisah Windows)',
    'backup' => 'nama folder cadangan',
] as $cari => $label) {
    periksa($label, str_contains($hal['isi'], $cari), '"'.$cari.'"');
}

// Salinan sungguhan harus tampil namanya.
$folderCadangan = __DIR__.'/../storage/app/backup';
$berkas = glob($folderCadangan.'/iksan-*.sqlite') ?: [];
$nyata = array_filter($berkas, fn ($f) => filesize($f) > 100000);

if ($nyata !== []) {
    $nama = basename(max($nyata));
    periksa('Salinan nyata tampil namanya', str_contains($hal['isi'], $nama), $nama);
} else {
    periksa('Salinan nyata tampil namanya', false, 'tidak ada salinan >100KB di folder');
}

// ── Menu sidebar ──────────────────────────────────────────────────────
$dash = minta($asas.'/admin', $jar);
periksa('Menu "Cadangan" ada di sidebar', str_contains($dash['isi'], 'Cadangan'), 'tautan sidebar ditemukan');
periksa('Menu menaut ke admin.backup.index', str_contains($dash['isi'], '/admin/cadangan'), 'href /admin/cadangan');

// ── Tombol unduh ─────────────────────────────────────────────────────
periksa('Tombol Unduh tampil', str_contains($hal['isi'], 'Unduh'), 'taut unduh pada salinan sah');

// Blade menulis & sebagai &amp; di dalam atribut href, dan URL dibuat absolut.
preg_match('#href="([^"]*cadangan/unduh/[^"]+)"#', $hal['isi'], $u);
$tautan = isset($u[1]) ? html_entity_decode($u[1], ENT_QUOTES) : null;

// Yang diuji harus jalur relatif, jadi asas ditambahkan saat diminta.
if ($tautan !== null && str_starts_with($tautan, 'http')) {
    $tautan = preg_replace('#^https?://[^/]+#', '', $tautan);
}

periksa('Taut unduh bertanda tangan', $tautan !== null && str_contains($tautan, 'signature='), $tautan ?? '(tidak ada)');

if ($tautan !== null) {
    $unduh = minta($asas.$tautan, $jar);

    periksa('Unduhan berhasil', $unduh['kode'] === 200, 'HTTP '.$unduh['kode']);
    periksa('Isi unduhan berkas SQLite', str_starts_with($unduh['isi'], 'SQLite format 3'), substr($unduh['isi'], 0, 15));
    periksa('Dikirim sebagai lampiran', (bool) preg_match('/Content-Disposition:\s*attachment/i', $unduh['kepala']), 'header attachment');

    // Tanpa tanda tangan, tautan yang sama harus ditolak.
    $tanpaTanda = preg_replace('/[?&]signature=[^&]*/', '', $tautan);
    $tolak = minta($asas.$tanpaTanda, $jar);
    periksa('Tanpa tanda tangan ditolak', $tolak['kode'] === 403, 'HTTP '.$tolak['kode']);
}

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal === 0 ? 0 : 1);
