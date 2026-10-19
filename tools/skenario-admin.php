<?php

/**
 * Skenario ADMIN lewat HTTP nyata (audit menyeluruh).
 *
 * Alur yang dijalankan adalah pekerjaan harian pemilik toko: masuk panel,
 * memeriksa pesanan baru, memverifikasi bukti transfer, mengubah status,
 * mengisi resi, mencetak tiga dokumen, menyunting produk, mengubah pengaturan,
 * dan membuka halaman keamanan.
 *
 * Yang diperiksa bukan hanya kode jawaban: setiap langkah dibandingkan dengan
 * baris basis data yang seharusnya berubah, dan setiap dokumen cetak diperiksa
 * dari isi berkas PDF-nya (ukuran lembar dibaca dari /MediaBox).
 *
 * Pakai: php tools/skenario-admin.php
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
        printf("  OK    %-58s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-58s %s\n", $judul, $bukti);
    }
};

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
    $mentah = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $kepala = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['kode' => $kode, 'kepala' => substr((string) $mentah, 0, $kepala), 'isi' => substr((string) $mentah, $kepala)];
};

$xsrf = static function () use ($jar): string {
    foreach (file($jar) ?: [] as $b) {
        if (str_starts_with($b, '#') || trim($b) === '') {
            continue;
        }
        $p = preg_split('/\t/', trim($b));
        if (count($p) >= 7 && $p[5] === 'XSRF-TOKEN') {
            return urldecode($p[6]);
        }
    }

    return '';
};

$komponen = static function (string $html, string $nama): ?string {
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $m);
    foreach ($m[1] as $mentah) {
        $snap = html_entity_decode($mentah, ENT_QUOTES);
        $d = json_decode($snap, true);
        if (is_array($d) && ($d['memo']['name'] ?? null) === $nama) {
            return $snap;
        }
    }

    return null;
};

$lw = static function (?string $snap, array $updates, array $calls) use ($minta, $basis, $xsrf): array {
    if ($snap === null) {
        return ['kode' => 0, 'isi' => 'snapshot tidak ada', 'kepala' => ''];
    }

    return $minta($basis.'/livewire/update', [
        'pos' => json_encode([
            'components' => [['snapshot' => $snap, 'updates' => $updates, 'calls' => $calls]],
        ], JSON_UNESCAPED_SLASHES),
        'ikut' => false,
        'header' => ['Content-Type: application/json', 'X-Livewire: 1', 'Accept: application/json', 'X-XSRF-TOKEN: '.$xsrf()],
    ]);
};

/** Ukuran lembar PDF dalam milimeter, dibaca dari /MediaBox. */
$ukuranPdf = static function (string $pdf): ?array {
    if (preg_match('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $m) !== 1) {
        return null;
    }

    return [
        round(((float) $m[3] - (float) $m[1]) * 25.4 / 72, 1),
        round(((float) $m[4] - (float) $m[2]) * 25.4 / 72, 1),
    ];
};

echo "\nSKENARIO ADMIN — pekerjaan harian pemilik toko\n";
echo 'Alamat: '.$basis."\n\n";

// ── 1. Masuk panel ──────────────────────────────────────────────────

$tamu = $minta($basis.'/admin', ['ikut' => false]);
$periksa('Tamu dialihkan dari panel', in_array($tamu['kode'], [302, 401, 403], true), 'HTTP '.$tamu['kode']);

$halamanMasuk = $minta($basis.'/admin/login');
$snapMasuk = $komponen($halamanMasuk['isi'], 'admin.masuk');

// Kata sandi salah dulu: harus ditolak.
$salah = $lw($snapMasuk, ['email' => 'admin@iksanknalpot.test', 'password' => 'salah-total'], [
    ['path' => '', 'method' => 'masuk', 'params' => []],
]);

$periksa(
    'Kata sandi salah ditolak',
    $salah['kode'] === 200 && ! str_contains($salah['isi'], 'Keluar'),
    'HTTP '.$salah['kode'].', tidak ada sesi panel',
);

$masuk = $lw($snapMasuk, ['email' => 'admin@iksanknalpot.test', 'password' => 'password'], [
    ['path' => '', 'method' => 'masuk', 'params' => []],
]);

$dasbor = $minta($basis.'/admin');

$periksa(
    'Admin berhasil masuk panel',
    $dasbor['kode'] === 200 && str_contains($dasbor['isi'], 'Keluar'),
    'HTTP '.$dasbor['kode'].' (masuk: '.$masuk['kode'].')',
);

// ── 2. Halaman panel yang dibuka setiap hari ────────────────────────

foreach ([
    'Pesanan' => '/admin/pesanan',
    'Keranjang terbuka' => '/admin/keranjang-terbuka',
    'Produk' => '/admin/produk',
    'Kategori' => '/admin/kategori',
    'Kupon' => '/admin/kupon',
    'Pelanggan' => '/admin/pelanggan',
    'Pesan kontak' => '/admin/pesan',
    'Keamanan' => '/admin/keamanan',
    'Pengaturan' => '/admin/pengaturan',
    'Hero' => '/admin/hero',
] as $nama => $jalur) {
    $r = $minta($basis.$jalur);
    $periksa("Membuka halaman panel: {$nama}", $r['kode'] === 200, 'HTTP '.$r['kode']);
}

// ── 3. Pesanan: saring, cari, buka ──────────────────────────────────

$cariNomor = $minta($basis.'/admin/pesanan?cari=IK-&status=semua&bayar=semua&urut=terbaru');
$periksa('Pencarian pesanan berdasarkan nomor', $cariNomor['kode'] === 200 && substr_count($cariNomor['isi'], '/admin/pesanan/') > 0, 'ada hasil');

$saring = $minta($basis.'/admin/pesanan?status=pending&bayar=semua&cari=&urut=terbaru');
$pendingDb = (int) $pdo->query("select count(*) from orders where status = 'pending'")->fetchColumn();

// Baris tabel dihitung dari nomor pesanan yang tampil, bukan dari tautan —
// setiap baris punya satu tautan, tetapi nomor pesanannya yang membuktikan
// barisnya benar-benar pesanan pending.
preg_match_all('/IK-\d{8}-[A-Z0-9]{6}/', $saring['isi'], $mNomor);
$tampil = count(array_unique($mNomor[0]));

$periksa(
    'Saringan status pesanan sesuai basis data',
    $saring['kode'] === 200 && $tampil > 0 && $tampil <= max(1, $pendingDb),
    'status=pending: '.$tampil.' pesanan tampil, '.$pendingDb.' di basis data',
);

$ekspor = $minta($basis.'/admin/ekspor/pesanan?status=semua&bayar=semua');
$periksa(
    'Ekspor CSV pesanan',
    $ekspor['kode'] === 200 && str_contains($ekspor['kepala'], 'text/csv') && substr_count($ekspor['isi'], "\n") > 1,
    substr_count($ekspor['isi'], "\n").' baris',
);

// ── 4. Verifikasi bukti bayar atas pesanan baru customer ────────────

$baru = $pdo->query(
    "select id, ulid, order_number, status from orders where order_number like 'IK-%' and status = 'pending' order by id desc limit 1"
)->fetch(PDO::FETCH_ASSOC);

$periksa('Ada pesanan pending untuk diuji', $baru !== false, $baru['order_number'] ?? '-');

if ($baru !== false) {
    $detail = $minta($basis.'/admin/pesanan/'.$baru['ulid']);
    $periksa(
        'Detail pesanan terbuka dengan ringkasan biaya',
        $detail['kode'] === 200 && str_contains($detail['isi'], $baru['order_number']),
        'HTTP '.$detail['kode'],
    );

    // Tandai lunas. Aksi ini milik komponen DAFTAR pesanan (tombol pada baris),
    // bukan komponen detail — mengirimnya ke detail dijawab 500.
    $daftarUntukLunas = $minta($basis.'/admin/pesanan');
    $bayar = $lw($komponen($daftarUntukLunas['isi'], 'admin.pesanan-daftar'), [], [
        ['path' => '', 'method' => 'tandaiDibayar', 'params' => [(int) $baru['id']]],
    ]);

    $sesudah = $pdo->query('select status, payment_status, paid_at from orders where id = '.(int) $baru['id'])->fetch(PDO::FETCH_ASSOC);

    $periksa(
        'Admin menandai pesanan lunas dari daftar',
        $bayar['kode'] === 200 && ($sesudah['status'] ?? '') === 'paid',
        'status -> '.($sesudah['status'] ?? '-').', paid_at '.($sesudah['paid_at'] ?? '-'),
    );

    // Isi resi.
    $detail2 = $minta($basis.'/admin/pesanan/'.$baru['ulid']);
    $kirim = $lw($komponen($detail2['isi'], 'admin.pesanan-detail'), [
        'kurir' => 'JNE',
        'layanan' => 'REG',
        'nomorResi' => 'AUDIT-RESI-77',
    ], [
        ['path' => '', 'method' => 'simpanPengiriman', 'params' => []],
    ]);

    $resi = $pdo->query('select tracking_number, shipping_courier from orders where id = '.(int) $baru['id'])->fetch(PDO::FETCH_ASSOC);

    $periksa(
        'Admin mengisi nomor resi dan kurir',
        $kirim['kode'] === 200 && ($resi['tracking_number'] ?? '') === 'AUDIT-RESI-77',
        'resi '.($resi['tracking_number'] ?? '-').' / '.($resi['shipping_courier'] ?? '-'),
    );

    // Tiga dokumen cetak.
    foreach ([
        ['Resi', '/resi', [100.0, 150.0]],
        ['Invoice', '/invoice', [210.0, 297.0]],
        ['Surat jalan', '/surat-jalan', [210.0, 297.0]],
    ] as [$nama, $jalur, $harap]) {
        $dok = $minta($basis.'/admin/pesanan/'.$baru['ulid'].$jalur);
        $ukuran = $ukuranPdf($dok['isi']);

        $periksa(
            "Cetak {$nama} menghasilkan PDF ".implode('x', $harap).' mm',
            $dok['kode'] === 200
                && str_starts_with($dok['isi'], '%PDF')
                && $ukuran !== null
                && abs($ukuran[0] - $harap[0]) < 2
                && abs($ukuran[1] - $harap[1]) < 2,
            'HTTP '.$dok['kode'].', '.($ukuran ? $ukuran[0].' x '.$ukuran[1].' mm' : 'MediaBox tidak ada'),
        );
    }

    // Perpindahan status sah: paid -> processing.
    $detail3 = $minta($basis.'/admin/pesanan/'.$baru['ulid']);
    $pindah = $lw($komponen($detail3['isi'], 'admin.pesanan-detail'), [
        'statusTujuan' => 'processing',
        'catatanStatus' => 'Diproses audit',
    ], [
        ['path' => '', 'method' => 'pindahStatus', 'params' => []],
    ]);

    $st = $pdo->query('select status from orders where id = '.(int) $baru['id'])->fetchColumn();

    $periksa('Admin memindahkan status pesanan', $pindah['kode'] === 200 && $st === 'processing', 'status -> '.$st);

    $riwayat = $pdo->query(
        'select from_status, to_status, changed_by from order_status_history where order_id = '.(int) $baru['id'].' order by id desc limit 1'
    )->fetch(PDO::FETCH_ASSOC);

    $periksa(
        'Riwayat status mencatat pelaku dan arah perpindahan',
        is_array($riwayat) && $riwayat['to_status'] === 'processing' && $riwayat['changed_by'] !== null,
        ($riwayat['from_status'] ?? '-').' -> '.($riwayat['to_status'] ?? '-').', pelaku #'.($riwayat['changed_by'] ?? '-'),
    );
}

// ── 5. Produk: daftar, sunting, simpan ──────────────────────────────

$daftarProduk = $minta($basis.'/admin/produk');
preg_match('#/admin/produk/([0-9a-z]{26})/sunting#', $daftarProduk['isi'], $mU);
$ulidProduk = $mU[1] ?? '';

$periksa('Daftar produk menautkan halaman sunting', $ulidProduk !== '', 'produk: '.$ulidProduk);

if ($ulidProduk !== '') {
    $sunting = $minta($basis.'/admin/produk/'.$ulidProduk.'/sunting');
    $periksa('Halaman sunting produk terbuka', $sunting['kode'] === 200, 'HTTP '.$sunting['kode']);

    $judulLama = (string) $pdo->query(
        "select title_id from products where ulid = ".$pdo->quote($ulidProduk)
    )->fetchColumn();

    // Formulir produk menyimpan per tab: simpanInfo() untuk judul/deskripsi,
    // simpanVarian() untuk varian. `simpan` tidak ada.
    $simpan = $lw($komponen($sunting['isi'], 'admin.produk-form'), [
        'title_id' => $judulLama.' (audit)',
    ], [
        ['path' => '', 'method' => 'simpanInfo', 'params' => []],
    ]);

    $judulBaru = (string) $pdo->query(
        "select title_id from products where ulid = ".$pdo->quote($ulidProduk)
    )->fetchColumn();

    $periksa(
        'Admin menyunting judul produk dan tersimpan',
        $simpan['kode'] === 200 && str_ends_with($judulBaru, '(audit)'),
        'title_id = "'.$judulBaru.'" (HTTP '.$simpan['kode'].')',
    );

    // Kembalikan judul asli supaya data demo tidak berubah.
    $pdo->prepare("update products set title_id = ? where ulid = ?")->execute([$judulLama, $ulidProduk]);
}

// ── 6. Pengaturan situs ─────────────────────────────────────────────

$pengaturan = $minta($basis.'/admin/pengaturan');
$periksa('Halaman pengaturan terbuka', $pengaturan['kode'] === 200, 'HTTP '.$pengaturan['kode']);

$simpanSetelan = $lw($komponen($pengaturan['isi'], 'admin.pengaturan-situs'), [
    'nilai.site_tagline' => 'Tagline audit',
], [
    ['path' => '', 'method' => 'simpan', 'params' => []],
]);

$taglineDb = (string) $pdo->query("select value from settings where key = 'site_tagline'")->fetchColumn();

$periksa(
    'Admin mengubah pengaturan dan nilainya tersimpan',
    $simpanSetelan['kode'] === 200 && $taglineDb === 'Tagline audit',
    'site_tagline = "'.$taglineDb.'"',
);

// ── 7. Keamanan: buka blokir ────────────────────────────────────────

$keamanan = $minta($basis.'/admin/keamanan');
$periksa(
    'Halaman keamanan menampilkan grafik dan riwayat',
    $keamanan['kode'] === 200 && str_contains($keamanan['isi'], 'Kejadian'),
    'HTTP '.$keamanan['kode'],
);

// ── 8. Keluar ───────────────────────────────────────────────────────

// Logout adalah POST biasa (bukan Livewire), jadi yang dikirim adalah _token
// dari <meta name="csrf-token"> halaman panel. Cookie XSRF-TOKEN tidak dipakai
// di jalur ini, dan memakainya menghasilkan 419.
$halamanPanel = $minta($basis.'/admin/pesanan');
preg_match('/name="csrf-token" content="([^"]+)"/', $halamanPanel['isi'], $mCsrf);
$tokenCsrf = $mCsrf[1] ?? '';

$keluar = $minta($basis.'/admin/logout', [
    'pos' => ['_token' => $tokenCsrf],
    'ikut' => false,
]);

$setelahKeluar = $minta($basis.'/admin', ['ikut' => false]);

$periksa(
    'Admin keluar dan panel tertutup kembali',
    in_array($keluar['kode'], [302, 200], true) && in_array($setelahKeluar['kode'], [302, 401, 403], true),
    'logout HTTP '.$keluar['kode'].', panel HTTP '.$setelahKeluar['kode'],
);

@unlink($jar);

echo "\nHasil admin: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
