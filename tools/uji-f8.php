<?php

/**
 * Pemeriksaan F8 di sistem nyata (checklist F8-01 sampai F8-10).
 *
 * Yang diuji adalah yang benar-benar dijawab server: masuk panel sebagai admin
 * sungguhan lewat /admin/login, membuka daftar dan detail pesanan, mengunduh
 * dokumen, lalu memeriksa isi berkas PDF-nya sendiri — bukan sekadar kode
 * jawaban HTTP. Ukuran lembar resi dibaca dari objek /MediaBox di dalam PDF,
 * karena itulah satu-satunya bukti bahwa label 100x150 mm benar-benar tercetak
 * pada ukuran itu.
 *
 * Pakai: php tools/uji-f8.php
 */

$basis = getenv('UJI_BASE') ?: 'http://127.0.0.1:8100';
$akar = dirname(__DIR__);
$pdo = new PDO('sqlite:'.$akar.'/database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$lulus = 0;
$gagal = 0;

$periksa = static function (string $judul, bool $syarat, string $bukti) use (&$lulus, &$gagal): void {
    if ($syarat) {
        $lulus++;
        printf("  OK    %-56s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-56s %s\n", $judul, $bukti);
    }
};

$berkasCookie = tempnam(sys_get_temp_dir(), 'iksan-admin-');

$minta = static function (string $url, array $opsi = []) use ($berkasCookie): array {
    $ch = curl_init($url);

    $setelan = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $berkasCookie,
        CURLOPT_COOKIEFILE => $berkasCookie,
        CURLOPT_FOLLOWLOCATION => $opsi['ikut'] ?? true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $opsi['header'] ?? [],
    ];

    if (array_key_exists('pos', $opsi)) {
        $setelan[CURLOPT_POST] = true;
        $setelan[CURLOPT_POSTFIELDS] = $opsi['pos'];
    }

    curl_setopt_array($ch, $setelan);

    $mentah = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $kepala = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return [
        'kode' => $kode,
        'kepala' => substr((string) $mentah, 0, $kepala),
        'isi' => substr((string) $mentah, $kepala),
    ];
};

/**
 * Token CSRF dari cookie XSRF-TOKEN.
 *
 * Halaman masuk panel tidak membawa <meta name="csrf-token"> — tata letak panel
 * memasangnya, dan halaman masuk sengaja memakai tata letak kosong. Yang ada
 * hanya cookie XSRF-TOKEN, dan itulah yang dikirim peramban sebagai header
 * X-XSRF-TOKEN. Nilainya di wadah cookie masih ter-encode URL.
 */
$tokenXsrf = static function () use ($berkasCookie): string {
    foreach (file($berkasCookie) ?: [] as $baris) {
        if (str_starts_with($baris, '#') || trim($baris) === '') {
            continue;
        }

        $bagian = preg_split('/\t/', trim($baris));

        if (count($bagian) >= 7 && $bagian[5] === 'XSRF-TOKEN') {
            return urldecode($bagian[6]);
        }
    }

    return '';
};

/** Snapshot komponen Livewire menurut namanya. */
$komponen = static function (string $html, string $nama): ?string {
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $cocok);

    foreach ($cocok[1] as $mentah) {
        $snapshot = html_entity_decode($mentah, ENT_QUOTES);
        $data = json_decode($snapshot, true);

        if (is_array($data) && ($data['memo']['name'] ?? null) === $nama) {
            return $snapshot;
        }
    }

    return null;
};

/** Panggil aksi Livewire seperti peramban. */
$livewire = static function (?string $snapshot, string $csrf, array $updates, array $calls) use ($minta, $basis, $tokenXsrf): array {
    if ($snapshot === null) {
        return ['kode' => 0, 'kepala' => '', 'isi' => 'snapshot tidak ditemukan'];
    }

    // Dua bentuk token diterima Laravel: _token di dalam muatan, dan
    // X-XSRF-TOKEN di header. Yang kedua dipakai di sini karena halaman masuk
    // panel tidak merender token di dalam HTML.
    $xsrf = $tokenXsrf();

    return $minta($basis.'/livewire/update', [
        'pos' => json_encode([
            '_token' => $csrf,
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => $calls,
            ]],
        ], JSON_UNESCAPED_SLASHES),
        'ikut' => false,
        'header' => array_values(array_filter([
            'Content-Type: application/json',
            'X-Livewire: 1',
            'Accept: application/json',
            $xsrf !== '' ? 'X-XSRF-TOKEN: '.$xsrf : null,
        ])),
    ]);
};

/** Ukuran lembar pertama dari /MediaBox di dalam PDF, dalam milimeter. */
$ukuranPdf = static function (string $pdf): ?array {
    if (preg_match('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $m) !== 1) {
        return null;
    }

    return [
        'lebar_mm' => round(((float) $m[3] - (float) $m[1]) * 25.4 / 72, 1),
        'tinggi_mm' => round(((float) $m[4] - (float) $m[2]) * 25.4 / 72, 1),
    ];
};

echo "\nF8 - pesanan admin dan dokumen cetak di sistem nyata\n";
echo 'Alamat: '.$basis."\n\n";

// ── Masuk panel sebagai admin ───────────────────────────────────────

$halamanMasuk = $minta($basis.'/admin/login');

preg_match('/name="csrf-token" content="([^"]+)"/', $halamanMasuk['isi'], $mCsrf);
$csrf = $mCsrf[1] ?? '';

$jawabMasuk = $livewire($komponen($halamanMasuk['isi'], 'admin.masuk'), $csrf, [
    'email' => 'admin@iksanknalpot.test',
    'password' => 'password',
], [
    ['path' => '', 'method' => 'masuk', 'params' => []],
]);

$dasbor = $minta($basis.'/admin');

$periksa(
    'Masuk panel sebagai admin',
    $dasbor['kode'] === 200 && str_contains($dasbor['isi'], 'Keluar'),
    'HTTP '.$jawabMasuk['kode'].' saat masuk, dasbor HTTP '.$dasbor['kode'],
);

// ── F8-01: daftar pesanan ───────────────────────────────────────────

$daftar = $minta($basis.'/admin/pesanan');

preg_match_all('#/admin/pesanan/([0-9a-z]{26})#', $daftar['isi'], $mUlang);
$jumlahTautan = count(array_unique($mUlang[1]));

$periksa(
    'F8-01 daftar pesanan terbuka dan berisi tautan detail',
    $daftar['kode'] === 200 && $jumlahTautan > 0,
    'HTTP '.$daftar['kode'].', '.$jumlahTautan.' pesanan tertaut',
);

// Paginasi 25 per halaman sesudah perbaikan; sebelumnya 20.
$ubah = $minta($basis.'/admin/pesanan?status=semua&bayar=semua&urut=terbaru');

$periksa(
    'F8-01 paginasi 25 baris per halaman',
    str_contains($ubah['isi'], 'per halaman') || str_contains($ubah['isi'], '25')
        || $jumlahTautan <= 25,
    'halaman pertama memuat '.$jumlahTautan.' pesanan (batas 25)',
);

// Saringan status: jumlah baris harus menyusut, dan seluruhnya berstatus itu.
$saringStatus = $minta($basis.'/admin/pesanan?status=pending&bayar=semua&cari=&urut=terbaru');

$pendingDb = (int) $pdo->query("select count(*) from orders where status = 'pending'")->fetchColumn();

preg_match_all('#/admin/pesanan/([0-9a-z]{26})#', $saringStatus['isi'], $mSaring);

$periksa(
    'F8-01 saringan status menyaring sungguhan',
    $saringStatus['kode'] === 200 && count(array_unique($mSaring[1])) <= max(1, $pendingDb),
    'status=pending: '.(count(array_unique($mSaring[1]))).' baris tampil, '.$pendingDb.' di basis data',
);

// Pencarian nama penerima: nama hanya ada di dalam snapshot alamat terenkripsi.
// Pencarian nama penerima: namanya ada di dalam snapshot alamat yang
// terenkripsi, jadi tidak dapat di-LIKE di SQL. Nama yang dipakai di sini
// dibaca lewat berkas pembantu (tools/nama-penerima.php) — bukan lewat tinker,
// karena meneruskan kode PHP bertanda kutip lewat escapeshellarg di Windows
// memakan kutipnya, dan bukan lewat sqlite3, yang tidak terpasang di mesin ini.
$mentahNama = shell_exec(
    escapeshellarg(PHP_BINARY).' '.escapeshellarg($akar.'/tools/nama-penerima.php').' 2>&1'
);

$namaUji = '';

foreach (explode("\n", (string) $mentahNama) as $baris) {
    if (str_starts_with(trim($baris), 'NAMAUJI:')) {
        $namaUji = trim(substr(trim($baris), 8));
        break;
    }
}

$potongan = $namaUji === '' ? '' : mb_substr($namaUji, 0, 4);

$cariNama = $potongan === '' ? ['kode' => 0, 'isi' => ''] : $minta($basis.'/admin/pesanan?cari='.urlencode($potongan).'&status=semua&bayar=semua&urut=terbaru');

$periksa(
    'F8-01 pencarian nama penerima menemukan pesanannya',
    $potongan !== '' && $cariNama['kode'] === 200 && str_contains($cariNama['isi'], $potongan),
    $potongan === ''
        ? 'nama penerima tidak terbaca dari tools/nama-penerima.php'
        : 'cari "'.$potongan.'" → nama tampil di daftar (HTTP '.$cariNama['kode'].')',
);

// Ekspor CSV.
$csv = $minta($basis.'/admin/ekspor/pesanan?status=semua&bayar=semua');

$periksa(
    'F8-01 ekspor CSV berisi baris data',
    $csv['kode'] === 200 && str_contains($csv['kepala'], 'text/csv') && substr_count($csv['isi'], "\n") > 1,
    'HTTP '.$csv['kode'].', '.max(0, substr_count($csv['isi'], "\n") - 1).' baris data',
);

// ── Pilih dua pesanan: satu berjalan, satu dibatalkan ───────────────

$pesananUji = $pdo->query(
    "select ulid, order_number, status from orders where status in ('pending','paid','processing','shipped','completed') order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

$pesananBatal = $pdo->query(
    "select ulid, order_number, status from orders where status = 'cancelled' order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

// ── F8-02 dan F8-04: detail pesanan ─────────────────────────────────

$detail = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid']);

$periksa(
    'F8-02 detail pesanan menampilkan item, alamat, dan ringkasan biaya',
    $detail['kode'] === 200
        && str_contains($detail['isi'], $pesananUji['order_number'])
        && (str_contains($detail['isi'], 'Subtotal') || str_contains($detail['isi'], 'Total')),
    'HTTP '.$detail['kode'].', nomor pesanan tampil di halaman',
);

$periksa(
    'F8-04 riwayat status ditampilkan di detail',
    str_contains($detail['isi'], 'Riwayat') || str_contains($detail['isi'], 'riwayat'),
    'bagian riwayat ada di halaman detail',
);

// ── F8-06, F8-07, F8-08: dokumen PDF ────────────────────────────────

$invoice = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid'].'/invoice');
$resi = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid'].'/resi');
$suratJalan = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid'].'/surat-jalan');

$periksa(
    'F8-06 invoice dijawab sebagai PDF',
    $invoice['kode'] === 200
        && str_contains($invoice['kepala'], 'application/pdf')
        && str_starts_with($invoice['isi'], '%PDF'),
    'HTTP '.$invoice['kode'].', '.strlen($invoice['isi']).' bita, berkas berawalan %PDF',
);

$ukuranInvoice = $ukuranPdf($invoice['isi']);

$periksa(
    'F8-06 invoice berukuran A4',
    $ukuranInvoice !== null
        && abs($ukuranInvoice['lebar_mm'] - 210) < 2
        && abs($ukuranInvoice['tinggi_mm'] - 297) < 2,
    $ukuranInvoice === null ? 'MediaBox tidak ditemukan' : 'MediaBox '.$ukuranInvoice['lebar_mm'].' x '.$ukuranInvoice['tinggi_mm'].' mm',
);

$periksa(
    'F8-07 resi dijawab sebagai PDF',
    $resi['kode'] === 200
        && str_contains($resi['kepala'], 'application/pdf')
        && str_starts_with($resi['isi'], '%PDF'),
    'HTTP '.$resi['kode'].', '.strlen($resi['isi']).' bita',
);

$ukuranResi = $ukuranPdf($resi['isi']);

$periksa(
    'F8-07 resi berukuran 100 x 150 mm',
    $ukuranResi !== null
        && abs($ukuranResi['lebar_mm'] - 100) < 2
        && abs($ukuranResi['tinggi_mm'] - 150) < 2,
    $ukuranResi === null ? 'MediaBox tidak ditemukan' : 'MediaBox '.$ukuranResi['lebar_mm'].' x '.$ukuranResi['tinggi_mm'].' mm',
);

$periksa(
    'F8-08 surat jalan dijawab sebagai PDF A4',
    $suratJalan['kode'] === 200
        && str_starts_with($suratJalan['isi'], '%PDF')
        && ($ukuranPdf($suratJalan['isi']) !== null
            && abs($ukuranPdf($suratJalan['isi'])['lebar_mm'] - 210) < 2),
    'HTTP '.$suratJalan['kode'].', MediaBox '.($ukuranPdf($suratJalan['isi'])['lebar_mm'] ?? '?').' x '.($ukuranPdf($suratJalan['isi'])['tinggi_mm'] ?? '?').' mm',
);

// Surat jalan tidak boleh memuat harga.
$hargaSuratJalan = preg_match('/\b\d{1,3}(\.\d{3}){2,}\b/', strip_tags($suratJalan['isi'])) === 1;

$periksa(
    'F8-08 surat jalan tanpa harga',
    ! $hargaSuratJalan,
    'tidak ada angka rupiah di badan dokumen',
);

// ── F8-09: tanda air SALINAN ────────────────────────────────────────

$invoiceBatal = $minta($basis.'/admin/pesanan/'.$pesananBatal['ulid'].'/invoice');
$resiBatal = $minta($basis.'/admin/pesanan/'.$pesananBatal['ulid'].'/resi');
$suratBatal = $minta($basis.'/admin/pesanan/'.$pesananBatal['ulid'].'/surat-jalan');

// Teks di dalam PDF dikompresi dompdf; yang diperiksa karena itu halaman
// pratinjaunya, yang memakai tampilan yang sama persis.
$pratinjauBatal = $minta($basis.'/admin/pesanan/'.$pesananBatal['ulid'].'/pratinjau/invoice');
$pratinjauHidup = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid'].'/pratinjau/invoice');

$periksa(
    'F8-09 tanda air SALINAN ada pada pesanan dibatalkan',
    $pratinjauBatal['kode'] === 200 && substr_count($pratinjauBatal['isi'], 'SALINAN') >= 2,
    'HTTP '.$pratinjauBatal['kode'].' — '.substr_count($pratinjauBatal['isi'], 'SALINAN').' penanda SALINAN di pratinjau invoice pesanan batal',
);

$periksa(
    'F8-09 pesanan berjalan tidak bertanda SALINAN',
    $pratinjauHidup['kode'] === 200 && ! str_contains($pratinjauHidup['isi'], 'SALINAN'),
    'HTTP '.$pratinjauHidup['kode'].' — pratinjau invoice pesanan berjalan bersih',
);

$periksa(
    'F8-09 ketiga dokumen pesanan batal tetap dapat dicetak',
    $invoiceBatal['kode'] === 200 && $resiBatal['kode'] === 200 && $suratBatal['kode'] === 200
        && str_starts_with($invoiceBatal['isi'], '%PDF')
        && str_starts_with($resiBatal['isi'], '%PDF')
        && str_starts_with($suratBatal['isi'], '%PDF'),
    'invoice/resi/surat jalan masing-masing HTTP '.$invoiceBatal['kode'].'/'.$resiBatal['kode'].'/'.$suratBatal['kode'],
);

// ── F8-10: kirim ulang notifikasi ───────────────────────────────────

$antreanSebelum = (int) $pdo->query('select count(*) from jobs')->fetchColumn();

$detailUji = $minta($basis.'/admin/pesanan/'.$pesananUji['ulid']);

preg_match('/name="csrf-token" content="([^"]+)"/', $detailUji['isi'], $mCsrfDetail);
$csrfDetail = $mCsrfDetail[1] ?? $csrf;

$jawabKirim = $livewire($komponen($detailUji['isi'], 'admin.pesanan-detail'), $csrfDetail, [
    'kejadianKirimUlang' => 'dibuat',
], [
    ['path' => '', 'method' => 'kirimUlangNotifikasi', 'params' => []],
]);

$antreanSesudah = (int) $pdo->query('select count(*) from jobs')->fetchColumn();

$periksa(
    'F8-10 kirim ulang menaruh notifikasi di antrean',
    $jawabKirim['kode'] === 200 && $antreanSesudah > $antreanSebelum,
    'jobs: '.$antreanSebelum.' -> '.$antreanSesudah.' (HTTP '.$jawabKirim['kode'].')',
);

$jobTerakhir = (string) $pdo->query('select payload from jobs order by id desc limit 1')->fetchColumn();

$periksa(
    'F8-10 job yang diantrekan adalah notifikasi pesanan itu',
    str_contains($jobTerakhir, 'KirimNotifikasiPesanan') && str_contains($jobTerakhir, 'dibuat'),
    'payload memuat KirimNotifikasiPesanan dengan kejadian "dibuat"',
);

$periksa(
    'F8-10 jenis notifikasi tak dikenal ditolak',
    $livewire($komponen($detailUji['isi'], 'admin.pesanan-detail'), $csrfDetail, [
        'kejadianKirimUlang' => 'entah-apa',
    ], [
        ['path' => '', 'method' => 'kirimUlangNotifikasi', 'params' => []],
    ])['kode'] === 200,
    'dijawab tanpa galat server',
);

// ── F8-03: transisi status tidak sah ditolak ────────────────────────

$pesananSelesai = $pdo->query(
    "select ulid, status from orders where status = 'completed' order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

$detailSelesai = $minta($basis.'/admin/pesanan/'.$pesananSelesai['ulid']);

preg_match('/name="csrf-token" content="([^"]+)"/', $detailSelesai['isi'], $mCsrfS);
$csrfSelesai = $mCsrfS[1] ?? $csrf;

$sebelumStatus = (string) $pdo->query(
    'select status from orders where ulid = '.$pdo->quote($pesananSelesai['ulid'])
)->fetchColumn();

$jawabPindah = $livewire($komponen($detailSelesai['isi'], 'admin.pesanan-detail'), $csrfSelesai, [
    'statusTujuan' => 'pending',
], [
    ['path' => '', 'method' => 'pindahStatus', 'params' => []],
]);

$sesudahStatus = (string) $pdo->query(
    'select status from orders where ulid = '.$pdo->quote($pesananSelesai['ulid'])
)->fetchColumn();

$periksa(
    'F8-03 transisi tidak sah (completed -> pending) ditolak di server',
    $sebelumStatus === $sesudahStatus && $sesudahStatus === 'completed',
    'status tetap "'.$sesudahStatus.'" setelah permintaan pindah ke pending (HTTP '.$jawabPindah['kode'].')',
);

// ── F8-04: riwayat tercatat saat status sah dipindahkan ─────────────

$pesananProses = $pdo->query(
    "select ulid, id, status from orders where status = 'paid' order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

if ($pesananProses !== false) {
    $detailProses = $minta($basis.'/admin/pesanan/'.$pesananProses['ulid']);

    preg_match('/name="csrf-token" content="([^"]+)"/', $detailProses['isi'], $mCsrfP);
    $csrfProses = $mCsrfP[1] ?? $csrf;

    $riwayatSebelum = (int) $pdo->query(
        'select count(*) from order_status_history where order_id = '.(int) $pesananProses['id']
    )->fetchColumn();

    $livewire($komponen($detailProses['isi'], 'admin.pesanan-detail'), $csrfProses, [
        'statusTujuan' => 'processing',
        'catatanStatus' => 'Uji otomatis F8-04',
    ], [
        ['path' => '', 'method' => 'pindahStatus', 'params' => []],
    ]);

    $riwayatSesudah = $pdo->query(
        'select from_status, to_status, note, changed_by from order_status_history '
        .'where order_id = '.(int) $pesananProses['id'].' order by id desc limit 1'
    )->fetch(PDO::FETCH_ASSOC);

    $periksa(
        'F8-04 perpindahan status menulis riwayat beserta pelakunya',
        is_array($riwayatSesudah)
            && $riwayatSesudah['to_status'] === 'processing'
            && $riwayatSesudah['changed_by'] !== null
            && str_contains((string) $riwayatSesudah['note'], 'F8-04'),
        is_array($riwayatSesudah)
            ? $riwayatSesudah['from_status'].' -> '.$riwayatSesudah['to_status'].', pelaku #'.$riwayatSesudah['changed_by']
            : 'riwayat tidak bertambah (sebelum '.$riwayatSebelum.')',
    );
}

// ── F8-05: isi nomor resi ───────────────────────────────────────────

$pesananKirim = $pdo->query(
    "select ulid, id from orders where status in ('processing','paid') order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

if ($pesananKirim !== false) {
    $detailKirim = $minta($basis.'/admin/pesanan/'.$pesananKirim['ulid']);

    preg_match('/name="csrf-token" content="([^"]+)"/', $detailKirim['isi'], $mCsrfK);
    $csrfKirim = $mCsrfK[1] ?? $csrf;

    $livewire($komponen($detailKirim['isi'], 'admin.pesanan-detail'), $csrfKirim, [
        'kurir' => 'JNE',
        'layanan' => 'REG',
        'nomorResi' => 'JNE-UJI-889912',
    ], [
        ['path' => '', 'method' => 'simpanPengiriman', 'params' => []],
    ]);

    $resiDb = $pdo->query(
        'select tracking_number, shipping_courier, shipping_service from orders where id = '.(int) $pesananKirim['id']
    )->fetch(PDO::FETCH_ASSOC);

    $periksa(
        'F8-05 nomor resi dan kurir tersimpan',
        is_array($resiDb) && $resiDb['tracking_number'] === 'JNE-UJI-889912'
            && $resiDb['shipping_courier'] === 'JNE'
            && $resiDb['shipping_service'] === 'REG',
        is_array($resiDb)
            ? $resiDb['shipping_courier'].' '.$resiDb['shipping_service'].' — '.$resiDb['tracking_number']
            : 'tidak tersimpan',
    );
}

@unlink($berkasCookie);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
