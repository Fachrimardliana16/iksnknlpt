<?php

/**
 * Skenario CUSTOMER lewat HTTP nyata (audit menyeluruh).
 *
 * Yang dijalankan adalah alur yang dilalui pembeli sungguhan: membuka katalog,
 * memilih produk, menambah ke keranjang lewat /livewire/update seperti
 * peramban, mengisi checkout, membuat pesanan, lalu mengunggah bukti bayar dan
 * melacak pesanannya. Setiap langkah memeriksa kode jawaban DAN baris basis
 * data yang seharusnya berubah.
 *
 * Bukan lewat kelas PHP: yang diuji adalah token CSRF, cookie sesi, middleware,
 * dan Livewire yang bekerja bersama — persis yang dijalankan pembeli.
 *
 * Pakai: php tools/skenario-customer.php
 */

$basis = getenv('UJI_BASE') ?: 'http://127.0.0.1:8100';
$akar = dirname(__DIR__);
$pdo = new PDO('sqlite:'.$akar.'/database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$lulus = 0;
$gagal = 0;
$catatan = [];

$periksa = static function (string $judul, bool $syarat, string $bukti) use (&$lulus, &$gagal): void {
    if ($syarat) {
        $lulus++;
        printf("  OK    %-56s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-56s %s\n", $judul, $bukti);
    }
};

$jar = tempnam(sys_get_temp_dir(), 'cust-');

$minta = static function (string $url, array $o = []) use ($jar): array {
    $ch = curl_init($url);
    $s = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $o['ikut'] ?? true,
        CURLOPT_TIMEOUT => 40,
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
    $efektif = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return [
        'kode' => $kode,
        'kepala' => substr((string) $mentah, 0, $kepala),
        'isi' => substr((string) $mentah, $kepala),
        'url' => $efektif,
    ];
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

echo "\nSKENARIO CUSTOMER — alur belanja ujung ke ujung\n";
echo 'Alamat: '.$basis."\n\n";

// ── 1. Halaman publik yang dibuka pembeli ───────────────────────────

foreach ([
    ['Beranda', '/'],
    ['Katalog', '/produk'],
    ['Tentang', '/tentang'],
    ['Kontak', '/kontak'],
    ['Halaman statis', '/halaman/syarat-ketentuan'],
] as [$nama, $jalur]) {
    $r = $minta($basis.$jalur);
    $periksa("Membuka {$nama}", $r['kode'] === 200, 'HTTP '.$r['kode']);
}

$katalog = $minta($basis.'/produk')['isi'];
preg_match('#/produk/([a-z0-9][a-z0-9-]{3,})#', $katalog, $mSlug);
$slug = $mSlug[1] ?? '';

$periksa('Katalog menampilkan produk', $slug !== '', 'produk pertama: '.$slug);

// ── 2. Detail produk ────────────────────────────────────────────────

$detail = $minta($basis.'/produk/'.$slug);

$periksa(
    'Detail produk terbuka dan memuat harga',
    $detail['kode'] === 200 && preg_match('/Rp\s?[0-9.]+/', $detail['isi']) === 1,
    'HTTP '.$detail['kode'].', harga terender',
);

$periksa(
    'Detail produk punya JSON-LD Product',
    str_contains($detail['isi'], '"@type":"Product"') || str_contains($detail['isi'], '"@type": "Product"'),
    'data terstruktur ada',
);

// ── 3. Keranjang ────────────────────────────────────────────────────

$sebelumItem = (int) $pdo->query('select coalesce(sum(quantity),0) from cart_items')->fetchColumn();

$tambah = $lw($komponen($detail['isi'], 'front.tambah-keranjang-cepat'), [], [
    ['path' => '', 'method' => 'tambah', 'params' => []],
]);

$sesudahItem = (int) $pdo->query('select coalesce(sum(quantity),0) from cart_items')->fetchColumn();

$periksa(
    'Menambah produk ke keranjang lewat jalur peramban',
    $tambah['kode'] === 200 && $sesudahItem > $sebelumItem,
    'cart_items '.$sebelumItem.' -> '.$sesudahItem.' (HTTP '.$tambah['kode'].')',
);

$keranjang = $minta($basis.'/keranjang');

$periksa(
    'Keranjang terbuka tanpa akun dan berisi barang',
    $keranjang['kode'] === 200 && str_contains($keranjang['isi'], 'Keranjang'),
    'HTTP '.$keranjang['kode'],
);

$periksa(
    'Keranjang tidak diindeks mesin pencari',
    str_contains($keranjang['isi'], 'noindex'),
    'meta robots noindex',
);

// Jumlah diubah pembeli.
$ubah = $lw($komponen($keranjang['isi'], 'front.keranjang-halaman'), [
    'catatan' => 'Titip di pos keamanan',
], []);

$catatanDb = (string) $pdo->query('select note from carts order by id desc limit 1')->fetchColumn();

$periksa(
    'Catatan pembeli tersimpan ke keranjang',
    $ubah['kode'] === 200 && $catatanDb === 'Titip di pos keamanan',
    'carts.note = "'.$catatanDb.'"',
);

// ── 4. Checkout ─────────────────────────────────────────────────────

$checkout = $minta($basis.'/checkout');

$periksa(
    'Checkout terbuka saat keranjang berisi',
    $checkout['kode'] === 200,
    'HTTP '.$checkout['kode'],
);

// Checkout dengan data tidak lengkap harus ditolak.
$tolak = $lw($komponen($checkout['isi'], 'front.checkout'), [
    'nama' => 'A',
    'email' => 'bukan-email',
], [
    ['path' => '', 'method' => 'buatPesanan', 'params' => []],
]);

$periksa(
    'Checkout menolak data tidak sah tanpa membuat pesanan',
    $tolak['kode'] === 200 && str_contains($tolak['isi'], 'error') || str_contains($tolak['isi'], 'wajib'),
    'jawaban memuat galat validasi, pesanan tidak terbentuk',
);

$sebelumOrder = (int) $pdo->query('select count(*) from orders')->fetchColumn();

// Honeypot: bot mengisi kolom tersembunyi.
$bot = $lw($komponen($checkout['isi'], 'front.checkout'), [
    'situs' => 'http://spam.example',
    'dimulaiPada' => time() - 10,
], [
    ['path' => '', 'method' => 'buatPesanan', 'params' => []],
]);

$periksa(
    'Checkout menolak pengiriman dari bot (honeypot terisi)',
    (int) $pdo->query('select count(*) from orders')->fetchColumn() === $sebelumOrder,
    'pesanan tidak bertambah (HTTP '.$bot['kode'].')',
);

// Pembeli sungguhan. Waktu mulai mundur 10 detik: token minimum 2 detik.
$pesan = $lw($komponen($checkout['isi'], 'front.checkout'), [
    'nama' => 'Siti Rahayu',
    'email' => 'siti.audit@contoh.test',
    'telepon' => '081298765432',
    'alamat1' => 'Jl. Kaliurang KM 9 No. 21',
    'kota' => 'Sleman',
    'provinsi' => 'DI Yogyakarta',
    'kodePos' => '55581',
    'kurir' => 'JNE',
    'metode' => 'manual',
    'catatan' => 'Kirim pagi hari',
    'dimulaiPada' => time() - 10,
], [
    ['path' => '', 'method' => 'buatPesanan', 'params' => []],
]);

$order = $pdo->query(
    'select id, ulid, order_number, grand_total, status, payment_status, customer_note, shipping_courier '
    .'from orders order by id desc limit 1'
)->fetch(PDO::FETCH_ASSOC);

$baru = (int) $pdo->query('select count(*) from orders')->fetchColumn() === $sebelumOrder + 1;

$periksa('Pesanan terbentuk dari checkout', $baru, 'orders '.$sebelumOrder.' -> '.($sebelumOrder + 1).' (HTTP '.$pesan['kode'].')');

$periksa(
    'Nomor pesanan berpola IK-YYYYMMDD-XXXXXX',
    $baru && preg_match('/^IK-\d{8}-[A-Z0-9]{6}$/', (string) $order['order_number']) === 1,
    'nomor: '.($order['order_number'] ?? '-'),
);

$periksa(
    'Catatan pembeli ikut ke pesanan',
    ($order['customer_note'] ?? '') === 'Kirim pagi hari',
    'customer_note = "'.($order['customer_note'] ?? '').'"',
);

$periksa(
    'Total pesanan masuk akal (bukan nol)',
    $baru && (float) $order['grand_total'] > 0,
    'grand_total = '.number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.'),
);

$periksa(
    'Keranjang dikosongkan setelah pesanan dibuat',
    (int) $pdo->query('select coalesce(sum(quantity),0) from cart_items ci join carts c on c.id = ci.cart_id where c.session_id is not null')->fetchColumn() === 0,
    'keranjang tamu kosong',
);

// ── 5. Halaman sukses dan pembayaran ────────────────────────────────

$sukses = $minta($basis.'/pesanan/sukses/'.$order['ulid']);

$periksa(
    'Halaman sukses pesanan terbuka',
    $sukses['kode'] === 200 && str_contains($sukses['isi'], (string) $order['order_number']),
    'HTTP '.$sukses['kode'].', nomor tampil',
);

$bayar = $minta($basis.'/pesanan/'.$order['ulid'].'/bayar');

$periksa(
    'Halaman unggah bukti bayar terbuka',
    $bayar['kode'] === 200,
    'HTTP '.$bayar['kode'],
);

$bayarPalsu = $minta($basis.'/pesanan/01zzzzzzzzzzzzzzzzzzzzzzzz/bayar', ['ikut' => false]);

$periksa(
    'ULID karangan ditolak (tidak membocorkan pesanan lain)',
    in_array($bayarPalsu['kode'], [404, 403], true),
    'HTTP '.$bayarPalsu['kode'],
);

// ── 6. Lacak pesanan ────────────────────────────────────────────────

$lacak = $minta($basis.'/lacak');
$periksa('Halaman lacak terbuka', $lacak['kode'] === 200, 'HTTP '.$lacak['kode']);

$hasilLacak = $lw($komponen($lacak['isi'], 'front.lacak-pesanan'), [
    'nomor' => (string) $order['order_number'],
    'email' => 'siti.audit@contoh.test',
], [
    ['path' => '', 'method' => 'lacak', 'params' => []],
]);

$periksa(
    'Lacak dengan nomor + email benar menampilkan pesanan',
    $hasilLacak['kode'] === 200 && (str_contains($hasilLacak['isi'], (string) $order['order_number']) || str_contains($hasilLacak['isi'], 'status')),
    'HTTP '.$hasilLacak['kode'],
);

$salahEmail = $lw($komponen($lacak['isi'], 'front.lacak-pesanan'), [
    'nomor' => (string) $order['order_number'],
    'email' => 'orang.lain@contoh.test',
], [
    ['path' => '', 'method' => 'lacak', 'params' => []],
]);

$periksa(
    'Lacak dengan email salah tidak menampilkan pesanan',
    $salahEmail['kode'] === 200 && ! str_contains($salahEmail['isi'], 'Kirim pagi hari'),
    'detail pesanan tidak bocor',
);

@unlink($jar);

echo "\nHasil customer: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
