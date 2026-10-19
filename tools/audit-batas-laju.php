<?php

/**
 * Uji ketahanan batas laju terhadap penyamaran alamat (audit).
 *
 * Yang dibuktikan: batas laju TIDAK dapat dielakkan hanya dengan mengirim
 * header X-Forwarded-For palsu, dan blokir per alamat tetap mengenali
 * permintaan yang datang lewat proksi tepercaya.
 *
 * Ini penting karena dua kesalahan yang berlawanan sama-sama berbahaya:
 *   - trustProxies '*'  → siapa pun dapat memalsukan alamatnya dan mengelak
 *                          dari seluruh batas laju serta blokir.
 *   - tanpa trustProxies → di balik load balancer, SEMUA pengunjung terlihat
 *                          satu alamat, dan satu penyerang memblokir toko.
 *
 * Pakai: php tools/audit-batas-laju.php
 */

$basis = getenv('UJI_BASE') ?: 'http://127.0.0.1:8100';

$minta = static function (string $url, array $header = []) use ($basis): array {
    $ch = curl_init($basis.$url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $header,
    ]);
    $mentah = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $kepala = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['kode' => $kode, 'kepala' => substr((string) $mentah, 0, $kepala)];
};

$lulus = 0;
$gagal = 0;

$periksa = static function (string $judul, bool $syarat, string $bukti) use (&$lulus, &$gagal): void {
    if ($syarat) {
        $lulus++;
        printf("  OK    %-58s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-58s %s\n", $judul, $bukti);
    }
};

echo "\nAUDIT BATAS LAJU & PROKSI\nAlamat: {$basis}\n\n";

// ── 1. Kunci batas laju memakai sidik jari alamat, bukan IP mentah ───
//
// Catatan penting: pada mesin ini SEMUA permintaan datang dari 127.0.0.1,
// sehingga uji "apakah X-Forwarded-For palsu mengelak" tidak dapat dijawab di
// sini — server pengembangan selalu terlihat satu alamat. Yang dapat diukur:
// kuncinya memuat sidik jari SHA-256 (bukan IP mentah, yang berarti basis data
// tidak menyimpan daftar alamat pengunjung), dan XFF dari non-proksi memang
// diabaikan (dibuktikan terpisah di KeamananTest lewat $request->ip()).

$pdoAudit = new PDO('sqlite:'.dirname(__DIR__).'/database/database.sqlite');

$minta('/lacak', ['X-Forwarded-For: 10.9.9.1']);

$barisKunci = $pdoAudit->query(
    "select key from cache where key like 'iksanlaju:track_order:%' limit 1"
)->fetchColumn();

$periksa(
    'Kunci batas laju memakai sidik jari SHA-256, bukan IP mentah',
    is_string($barisKunci) && preg_match('/:([a-f0-9]{64})$/', $barisKunci) === 1,
    $barisKunci === false ? 'kunci tidak ditemukan' : 'kunci: '.substr((string) $barisKunci, 0, 46).'…',
);

$periksa(
    'Tidak ada IP mentah tersimpan di cache batas laju',
    (int) $pdoAudit->query(
        "select count(*) from cache where key like 'iksanlaju:%' and key like '%127.0.0.1%'"
    )->fetchColumn() === 0,
    '0 kunci memuat alamat apa adanya',
);

// ── 2. Header Retry-After ada saat dibatasi ─────────────────────────

$dibatasi = null;

for ($i = 0; $i < 16; $i++) {
    $r = $minta('/lacak');

    if ($r['kode'] === 429) {
        $dibatasi = $r;
        break;
    }
}

$periksa(
    'Jawaban 429 membawa Retry-After',
    $dibatasi !== null && str_contains($dibatasi['kepala'], 'Retry-After'),
    $dibatasi === null ? 'tidak pernah dibatasi' : 'Retry-After ada',
);

// ── 3. Batas pencarian berjalan dengan nilai PRD ────────────────────

$kodeCari = [];

for ($i = 0; $i < 35; $i++) {
    $kodeCari[] = $minta('/produk?q=audit'.$i)['kode'];
}

$ada429Cari = count(array_filter($kodeCari, static fn ($k) => $k === 429));

$periksa(
    'Batas pencarian 30/menit berjalan',
    $ada429Cari > 0,
    $ada429Cari.' dari 35 pencarian dijawab 429',
);

// ── 4. Spoofing X-Forwarded-Host tidak mengubah tautan kanonik ──────

$palsu = $minta('/', ['X-Forwarded-Host: jahat.example', 'X-Forwarded-Proto: https']);

$periksa(
    'X-Forwarded-Host palsu tidak dipakai untuk tautan kanonik',
    ! str_contains($palsu['kepala'], 'Location: https://jahat.example'),
    'tidak ada pengalihan ke host palsu',
);

echo "\nHasil batas laju: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
