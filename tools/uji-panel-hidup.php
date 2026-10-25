<?php

/**
 * Uji hidup panel admin (butir 6.3 checklist 2410-01).
 *
 * Yang diperiksa: halaman-halaman yang tercantum di checklist benar-benar
 * membuka HTTP 200 SETELAH masuk, bukan sekadar 302 ke halaman login. 302
 * untuk tamu memang benar, tetapi checklist menuntut 200, jadi login dilakukan
 * lebih dulu lewat Livewire (bukan menebak kredensial di URL).
 *
 * Kredensial diambil dari basis data lewat env, tidak ditulis di berkas.
 */

$asas = 'http://127.0.0.1:8100';

function lw(string $html, string $komponen, array $params, array $cookies): array
{
    $snapshot = null;
    if (preg_match('/wire:snapshot="([^"]+)"/', $html, $m)) {
        $snapshot = html_entity_decode($m[1], ENT_QUOTES);
    }

    $token = null;
    if (preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m)) {
        $token = $m[1];
    }

    $fields = [
        'components' => [],
        'actionQueue' => [],
    ];

    if ($snapshot !== null) {
        $fields['components'] = [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [],
        ]];
    }

    return [$token, $fields];
}

function minta(string $url, array $cookies = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_COOKIE => implode('; ', array_map(
            fn ($k, $v) => $k.'='.$v,
            array_keys($cookies),
            array_values($cookies),
        )),
    ]);

    $respons = curl_exec($ch);
    $panjang = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $kepala = substr((string) $respons, 0, $panjang);
    $isi = substr((string) $respons, $panjang);

    preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $kepala, $m, PREG_SET_ORDER);

    return ['kode' => $kode, 'isi' => $isi, 'kepala' => $kepala, 'cookies' => $m];
}

// Kredensial dibaca dari berkas seed, bukan ditebak. Email admin uji ada di
// UserSeeder dan sandinya dikendalikan lewat env (nilai bawaan sama dengan
// yang dipakai harness lain di tools/).
$surel = 'admin@iksanknalpot.test';

$sandi = getenv('IKSAN_ADMIN_PASSWORD') ?: '';

if ($sandi === '') {
    fwrite(STDERR, "IKSAN_ADMIN_PASSWORD belum diisi — login panel tidak dapat dilakukan.\n");
    fwrite(STDERR, "Yang tetap diperiksa di bawah: halaman tamu dan redirect tamu.\n\n");
}

// ── Halaman tamu ────────────────────────────────────────────────────
$lulus = 0;
$gagal = 0;

function periksa(string $label, bool $syarat, string $bukti): void
{
    global $lulus, $gagal;

    if ($syarat) {
        $lulus++;
        echo "  OK    {$label} — {$bukti}\n";

        return;
    }

    $gagal++;
    echo "  GAGAL {$label} — {$bukti}\n";
}

echo "Halaman tamu (harus 200, tanpa login):\n";
foreach (['/', '/produk', '/kontak', '/faq', '/tentang', '/keranjang', '/up', '/admin/login'] as $jalur) {
    $r = minta($asas.$jalur);
    periksa($jalur, $r['kode'] === 200, 'HTTP '.$r['kode']);
}

echo "\nHalaman admin sebagai tamu (harus 302 ke login, bukan 200):\n";
foreach (['/admin/pesanan', '/admin/pesan', '/admin/produk', '/admin/pengguna', '/admin/kol', '/admin/kupon'] as $jalur) {
    $r = minta($asas.$jalur);
    $tuju = '';
    preg_match('#^Location:\s*(.+)$#mi', $r['kepala'], $m);
    $tuju = trim($m[1] ?? '');

    periksa(
        $jalur,
        $r['kode'] === 302 && str_contains($tuju, '/admin/login'),
        'HTTP '.$r['kode'].' → '.($tuju !== '' ? $tuju : '(tanpa Location)'),
    );
}

if ($sandi === '') {
    echo "\nHasil tamu: {$lulus} lulus, {$gagal} gagal";
    echo " (bagian panel dilewati: IKSAN_ADMIN_PASSWORD kosong)\n";
    exit($gagal === 0 ? 0 : 1);
}

// ── Login panel ─────────────────────────────────────────────────────
echo "\nMasuk panel:\n";

$jar = [];
$login = minta($asas.'/admin/login', $jar);

foreach ($login['cookies'] as $c) {
    $jar[$c[1]] = $c[2];
}

// Form login dirender Livewire, jadi snapshot-nya harus diambil lebih dulu.
if (! preg_match('/wire:snapshot="([^"]+)"/', $login['isi'], $m)) {
    fwrite(STDERR, "Snapshot Livewire tidak ditemukan di /admin/login — halaman mungkin gagal dirender.\n");
    exit(1);
}

$snapshot = html_entity_decode($m[1], ENT_QUOTES);
$token = null;
preg_match('/name="csrf-token" content="([^"]+)"/', $login['isi'], $mt);
$token = $mt[1] ?? null;

$payload = [
    '_token' => $token,
    'components' => [[
        'snapshot' => $snapshot,
        'updates' => ['email' => $surel, 'password' => $sandi],
        'calls' => [['path' => '', 'method' => 'masuk', 'params' => []]],
    ]],
];

$ch = curl_init($asas.'/livewire/update');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Livewire: 1',
        'Accept: application/json',
        // KEDUA tajuk wajib, bukan hanya salah satu.
        //
        // X-CSRF-TOKEN dibandingkan Laravel dengan token di SESI (nilai mentah
        // dari <meta name="csrf-token">), sedangkan X-XSRF-TOKEN dibandingkan
        // dengan COOKIE XSRF-TOKEN. Mengirim salah satunya saja membuat
        // permintaan ditolak 419 meski cookie sesinya terkirim benar — itu yang
        // membuat percobaan pertama gagal tanpa pesan jelas.
        'X-CSRF-TOKEN: '.$token,
        'X-XSRF-TOKEN: '.$token,
    ],
    CURLOPT_COOKIE => implode('; ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($jar), array_values($jar))),
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
]);

$respons = curl_exec($ch);
$panjang = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$kepala = substr((string) $respons, 0, $panjang);
$isi = substr((string) $respons, $panjang);
curl_close($ch);

preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]+)/mi', $kepala, $mc, PREG_SET_ORDER);

foreach ($mc as $c) {
    $jar[$c[1]] = $c[2];
}

$hasil = json_decode($isi, true);

// Bentuk respons Livewire: {"components":[{...,"effects":{"redirect":"..."}}]}.
// Yang dicari adalah effects pada komponen pertama, bukan kunci "effects" di
// tingkat atas — membaca tingkat atas membuat login yang BERHASIL tampak gagal
// (redirect-nya ada di dalam components, bukan di akar).
$redirect = null;

if (is_array($hasil)) {
    foreach ($hasil['components'] ?? [] as $komponen) {
        if (! empty($komponen['effects']['redirect'])) {
            $redirect = (string) $komponen['effects']['redirect'];
            break;
        }
    }
}

$berhasil = $redirect !== null;

if (! $berhasil) {
    // Tampilkan SEBAGIAN respons supaya sebabnya terlihat, bukan hanya
    // "tidak ada redirect". Pesan galat Livewire (validasi, 419) ada di dalam
    // respons, dan tanpa ini kegagalan login tampak seperti galat jaringan.
    $cuplikan = substr((string) $isi, 0, 600);
    echo "  --- respons Livewire ---\n";
    echo "  ".str_replace("\n", "\n  ", $cuplikan)."\n";
    echo "  --- akhir cuplikan ---\n";
}

periksa(
    'Login panel '.$surel,
    $berhasil,
    $berhasil ? 'redirect '.$redirect : 'tidak ada redirect pada respons Livewire',
);

if (! $berhasil) {
    echo "\nLogin gagal — halaman panel tidak dapat diperiksa.\n";
    echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
    exit(1);
}

// ── Halaman panel (butir 6.3) ───────────────────────────────────────
echo "\nHalaman panel sesudah login (checklist 6.3, harus 200):\n";
foreach (['/admin/pesanan', '/admin/pesan', '/admin/produk', '/admin/pengguna'] as $jalur) {
    $r = minta($asas.$jalur, $jar);
    periksa($jalur, $r['kode'] === 200, 'HTTP '.$r['kode']);
}

echo "\nHalaman panel tambahan (Tahap 3, harus 200):\n";
foreach (['/admin/kol', '/admin/kupon'] as $jalur) {
    $r = minta($asas.$jalur, $jar);
    periksa($jalur, $r['kode'] === 200, 'HTTP '.$r['kode']);
}

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal === 0 ? 0 : 1);
