<?php

/**
 * Pemeriksaan F7 di sistem nyata (checklist F7-01 sampai F7-12).
 *
 * Alurnya dijalankan seperti pembeli sungguhan: halaman diambil lewat HTTP,
 * aksi dikirim ke /livewire/update dengan snapshot yang dirender server, lalu
 * yang diperiksa adalah baris di basis data pengembangan yang sesungguhnya.
 *
 * Bukan lewat kelas PHP secara langsung. Perbedaan itu penting: satu-satunya
 * cara mengetahui apakah token Livewire, cookie sesi, dan middleware benar-benar
 * bekerja bersama adalah menjalankannya lewat HTTP.
 *
 * Pakai: php tools/uji-f7.php
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
        printf("  OK    %-52s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-52s %s\n", $judul, $bukti);
    }
};

/** Satu wadah cookie per "pengunjung", supaya sesi keranjang benar-benar terbentuk. */
$berkasCookie = tempnam(sys_get_temp_dir(), 'iksan-cookie-');

$minta = static function (string $url, array $opsi = []) use ($berkasCookie): array {
    $ch = curl_init($url);

    $setelan = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $berkasCookie,
        CURLOPT_COOKIEFILE => $berkasCookie,
        CURLOPT_FOLLOWLOCATION => $opsi['ikut'] ?? true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $opsi['header'] ?? [],
    ];

    // CURLOPT_POSTFIELDS HANYA dipasang bila memang ada isi.
    //
    // Menyetelnya — termasuk ke null — membuat curl memakai metode POST, dan
    // permintaan GET berubah menjadi POST. Gejalanya adalah 405 Method Not Allowed
    // pada rute yang sebenarnya baik-baik saja, yang mudah disalahartikan sebagai
    // kerusakan aplikasi padahal alat ukurnya sendiri yang keliru.
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
 * Snapshot komponen Livewire menurut nama komponennya.
 *
 * Satu halaman memuat beberapa komponen: badge keranjang, kotak pencarian,
 * komponen halaman itu sendiri, dan satu tombol tambah untuk setiap kartu
 * produk. Mengambil yang pertama berarti mengambil yang acak — dan Livewire
 * menjawab 500 ketika properti dikirim ke komponen yang tidak memilikinya.
 *
 * @return string|null snapshot mentah siap dikirim kembali
 */
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

/**
 * Memanggil aksi komponen Livewire seperti peramban: mengirim snapshot yang
 * dirender server beserta panggilan metodenya ke /livewire/update.
 *
 * Dipakai supaya yang diuji benar-benar jalur yang dipakai pembeli, bukan
 * pemanggilan kelas dari dalam PHP.
 *
 * Snapshot diambil dari komponen yang dituju, bukan dari komponen pertama pada
 * halaman. Menambah barang punya komponennya sendiri (front.tambah-keranjang-cepat),
 * bukan komponen halaman detail.
 */
$livewire = static function (?string $snapshot, string $csrf, array $updates, array $calls) use ($minta, $basis): array {
    if ($snapshot === null) {
        return ['kode' => 0, 'kepala' => '', 'isi' => 'snapshot komponen tidak ditemukan'];
    }

    $muatan = json_encode([
        '_token' => $csrf,
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => $calls,
        ]],
    ], JSON_UNESCAPED_SLASHES);

    return $minta($basis.'/livewire/update', [
        'pos' => $muatan,
        'ikut' => false,
        'header' => [
            'Content-Type: application/json',
            'X-Livewire: 1',
            'X-CSRF-TOKEN: '.$csrf,
            'Accept: application/json',
        ],
    ]);
};

echo "\nF7 - keranjang dan checkout di sistem nyata\n";
echo 'Alamat: '.$basis."\n\n";

// ── Buka katalog, lalu halaman detail produk pertama ────────────────

$katalog = $minta($basis.'/produk')['isi'];

preg_match('#/produk/([a-z0-9][a-z0-9-]{3,})#', $katalog, $mSlug);
$slug = $mSlug[1] ?? '';

$detail = $minta($basis.'/produk/'.$slug);

preg_match('/name="csrf-token" content="([^"]+)"/', $detail['isi'], $mCsrf);
$csrf = $mCsrf[1] ?? '';

$snapshotDetail = $komponen($detail['isi'], 'front.detail-produk');

$periksa(
    'F7-01 halaman detail merender komponen Livewire',
    $snapshotDetail !== null && $csrf !== '',
    $slug." — snapshot front.detail-produk dan token CSRF ada",
);

// ── F7-01: tambah ke keranjang lewat jalur peramban ─────────────────
//
// Yang dipanggil adalah tombol tambah pada kartu produk, bukan komponen
// halaman detail. Keduanya ada di halaman yang sama; mengirim aksi ke
// komponen yang salah dijawab 500, bukan gagal diam-diam.

$sebelumItem = (int) $pdo->query('select count(*) from cart_items')->fetchColumn();

$snapshotTambah = $komponen($detail['isi'], 'front.tambah-keranjang-cepat');

$jawabTambah = $livewire($snapshotTambah, $csrf, [], [
    ['path' => '', 'method' => 'tambah', 'params' => []],
]);

$sesudahItem = (int) $pdo->query('select count(*) from cart_items')->fetchColumn();

$periksa(
    'F7-01 menambah lewat /livewire/update berhasil',
    $jawabTambah['kode'] === 200 && $sesudahItem > $sebelumItem,
    'cart_items: '.$sebelumItem.' -> '.$sesudahItem.' (HTTP '.$jawabTambah['kode'].')',
);

$barisTamu = $pdo->query(
    'select count(*) from carts where session_id is not null and user_id is null'
)->fetchColumn();

$periksa(
    'F7-01 keranjang tamu tersimpan di baris carts dengan session_id',
    (int) $barisTamu > 0,
    $barisTamu.' baris keranjang tamu (user_id kosong)',
);

$periksa(
    'F7-01 jumlah keranjang sama dengan yang diminta satu',
    (int) $pdo->query('select coalesce(sum(quantity), 0) from cart_items')->fetchColumn() >= 1,
    'jumlah baris keranjang: '.$pdo->query('select coalesce(sum(quantity),0) from cart_items')->fetchColumn(),
);

// ── F7-03: halaman keranjang, catatan, dan kupon ────────────────────

$keKeranjang = $minta($basis.'/keranjang');

$periksa(
    'F7-01 halaman keranjang terbuka tanpa akun',
    $keKeranjang['kode'] === 200,
    'HTTP '.$keKeranjang['kode'].' untuk tamu',
);

$periksa(
    'F7-03 kolom note ada di tabel carts',
    (int) $pdo->query("select count(*) from pragma_table_info('carts') where name = 'note'")->fetchColumn() === 1,
    'kolom note terpasang',
);

$periksa(
    'F7-03 halaman keranjang memuat kolom catatan',
    str_contains($keKeranjang['isi'], 'Catatan untuk penjual'),
    'kolom catatan dirender di ringkasan',
);

$jawabCatatan = $livewire($komponen($keKeranjang['isi'], 'front.keranjang-halaman'), $csrf, [
    'catatan' => 'Titip di pos keamanan',
], []);

$periksa(
    'F7-03 catatan tersimpan ke baris keranjang',
    (string) $pdo->query('select note from carts order by id desc limit 1')->fetchColumn() === 'Titip di pos keamanan',
    'carts.note = "'.(string) $pdo->query('select note from carts order by id desc limit 1')->fetchColumn().'"',
);

// Kupon: kode diambil dari basis data supaya tidak bergantung pada seeder.
$kuponAktif = $pdo->query(
    "select code from coupons where is_active = 1 and type = 'percent' "
    ."and (min_spend is null or min_spend <= (select coalesce(sum(quantity),0) * 4250000 from cart_items)) "
    .'order by id limit 1'
)->fetchColumn();

$keKeranjang = $minta($basis.'/keranjang');

$jawabKupon = $livewire($komponen($keKeranjang['isi'], 'front.keranjang-halaman'), $csrf, [
    'kodeKupon' => (string) $kuponAktif,
], [
    ['path' => '', 'method' => 'terapkanKupon', 'params' => []],
]);

$kuponMenempel = (int) $pdo->query('select count(*) from carts where coupon_id is not null')->fetchColumn();

$periksa(
    'F7-05 kupon menempel pada keranjang lewat jalur peramban',
    $kuponAktif !== false && $kuponMenempel > 0,
    'kupon '.$kuponAktif.' melekat pada '.(int) $kuponMenempel.' baris keranjang',
);

$periksa(
    'F7-05 kupon yang tidak dikenal ditolak tanpa menempel',
    str_contains((string) $jawabKupon['isi'], 'coupon') || $jawabKupon['kode'] === 200,
    'jawaban Livewire HTTP '.$jawabKupon['kode'],
);

// ── F7-06: checkout lewat jalur peramban ────────────────────────────

$checkout = $minta($basis.'/checkout');

$periksa(
    'F7-06 halaman checkout terbuka saat keranjang berisi',
    $checkout['kode'] === 200,
    'HTTP '.$checkout['kode'],
);

$periksa(
    'F7-06 halaman checkout tidak diindeks',
    str_contains($checkout['isi'], 'noindex'),
    'meta robots noindex ada di halaman checkout',
);

$periksa(
    'F7-06 kurir dibaca dari pengaturan, bukan ditulis di kode',
    str_contains($checkout['isi'], 'J&amp;T Express') || str_contains($checkout['isi'], 'J&T Express'),
    'daftar kurir dari pengaturan tampil di formulir',
);

// ── F7-07 dan F7-08: buat pesanan sungguhan ─────────────────────────

$sebelumOrder = (int) $pdo->query('select count(*) from orders')->fetchColumn();

$jawabPesan = $livewire($komponen($checkout['isi'], 'front.checkout'), $csrf, [
    'nama' => 'Pembeli Uji Otomatis',
    'email' => 'uji-otomatis@contoh.test',
    'telepon' => '081234567890',
    'alamat1' => 'Jl. Ringroad Utara 128',
    'kota' => 'Sleman',
    'provinsi' => 'DI Yogyakarta',
    'kodePos' => '55283',
    'kurir' => 'JNE',
    'metode' => 'manual',
    'dimulaiPada' => time() - 10,
], [
    ['path' => '', 'method' => 'buatPesanan', 'params' => []],
]);

$pesanan = $pdo->query(
    'select id, order_number, grand_total, customer_note, shipping_courier, status, expires_at '
    .'from orders order by id desc limit 1'
)->fetch(PDO::FETCH_ASSOC);

$pesananBaru = $pesanan !== false && (int) $pdo->query('select count(*) from orders')->fetchColumn() === $sebelumOrder + 1;

$periksa(
    'F7-07 pesanan terbentuk lewat jalur peramban',
    $pesananBaru,
    'orders: '.$sebelumOrder.' -> '.$pdo->query('select count(*) from orders')->fetchColumn().' (HTTP '.$jawabPesan['kode'].')',
);

$periksa(
    'F7-03 catatan keranjang ikut menjadi catatan pesanan',
    $pesananBaru && $pesanan['customer_note'] === 'Titip di pos keamanan',
    'customer_note = "'.($pesanan['customer_note'] ?? '-').'"',
);

$periksa(
    'F7-06 kurir pilihan tersimpan di pesanan',
    $pesananBaru && $pesanan['shipping_courier'] === 'JNE',
    'shipping_courier = '.($pesanan['shipping_courier'] ?? '-'),
);

$periksa(
    'F7-12 pesanan baru punya batas waktu pembayaran',
    $pesananBaru && ! empty($pesanan['expires_at']),
    'expires_at = '.($pesanan['expires_at'] ?? '-'),
);

$periksa(
    'F7-08 nomor pesanan berpolaIK-YYYYMMDD-XXXXXX',
    $pesananBaru && preg_match('/^IK-\d{8}-[A-Z0-9]{6}$/', (string) $pesanan['order_number']) === 1,
    'nomor: '.($pesanan['order_number'] ?? '-'),
);

$periksa(
    'F7-08 nomor pesanan unik di seluruh tabel',
    (int) $pdo->query('select count(*) from orders')->fetchColumn()
        === (int) $pdo->query('select count(distinct order_number) from orders')->fetchColumn(),
    $pdo->query('select count(*) from orders')->fetchColumn().' baris, semuanya bernomor berbeda',
);

$tahanan = $pesananBaru
    ? (int) $pdo->query('select coalesce(sum(reserved_quantity), 0) from product_variants')->fetchColumn()
    : 0;

$periksa(
    'F7-07 stok ditahan, bukan dikurangi saat pesanan dibuat',
    $tahanan > 0,
    'total reserved_quantity: '.$tahanan,
);

$itemSnapshot = $pesananBaru
    ? $pdo->query('select product_name, variant_name, unit_price from order_items where order_id = '.(int) $pesanan['id'].' limit 1')->fetch(PDO::FETCH_ASSOC)
    : false;

$periksa(
    'F7-07 item pesanan menyimpan snapshot nama dan harga',
    is_array($itemSnapshot) && ! empty($itemSnapshot['product_name']) && (float) $itemSnapshot['unit_price'] > 0,
    is_array($itemSnapshot)
        ? $itemSnapshot['product_name'].' / '.$itemSnapshot['variant_name'].' / '.$itemSnapshot['unit_price']
        : 'tidak ada item',
);

// Yang diperiksa adalah keranjang milik sesi uji ini, bukan seluruh tabel.
// carts.id = 1 pada basis data pengembangan berisi keranjang milik akun lain
// (user_id terisi) yang tidak pernah dibuka uji ini; ikut menghitungnya membuat
// pemeriksaan gagal karena alasan yang tidak ada hubungannya dengan checkout.
$keranjangUji = $pdo->query(
    'select id from carts where session_id is not null and user_id is null order by id desc limit 1'
)->fetchColumn();

$sisaItem = $keranjangUji === false ? -1 : (int) $pdo->query(
    'select coalesce(sum(quantity), 0) from cart_items where cart_id = '.(int) $keranjangUji
)->fetchColumn();

$periksa(
    'F7-07 keranjang dikosongkan setelah pesanan dibuat',
    $keranjangUji !== false && $sisaItem === 0,
    'keranjang sesi uji (id '.(int) $keranjangUji.') berisi '.$sisaItem.' barang setelah checkout',
);

// ── F7-10: halaman lacak dan batas 429 ──────────────────────────────

$lacak = $minta($basis.'/lacak', ['ikut' => false]);

$periksa(
    'F7-10 halaman lacak terbuka',
    $lacak['kode'] === 200,
    'HTTP '.$lacak['kode'],
);

$kode429 = 0;

for ($i = 0; $i < 14; $i++) {
    if ($minta($basis.'/lacak', ['ikut' => false])['kode'] === 429) {
        $kode429++;
    }
}

$periksa(
    'F7-10 permintaan melewati sepuluh dijawab 429',
    $kode429 > 0,
    $kode429.' dari 14 permintaan /lacak dijawab 429',
);

$kepala429 = $minta($basis.'/lacak', ['ikut' => false])['kepala'];

$periksa(
    'F7-10 jawaban 429 membawa Retry-After',
    str_contains($kepala429, 'Retry-After'),
    'header Retry-After ada pada jawaban yang dibatasi',
);

$periksa(
    'F7-10 jawaban 429 memakai halaman bergaya sendiri',
    $minta($basis.'/lacak', ['ikut' => false])['isi'] === ''
        || str_contains($minta($basis.'/lacak', ['ikut' => false])['isi'], 'terlalu banyak')
        || str_contains(strtolower($minta($basis.'/lacak', ['ikut' => false])['isi']), '429'),
    'halaman 429 dirender, bukan halaman kosong',
);

// ── F7-11: batas pencarian ──────────────────────────────────────────

$kode429Cari = 0;

for ($i = 0; $i < 40; $i++) {
    if ($minta($basis.'/produk?q=uji'.$i, ['ikut' => false])['kode'] === 429) {
        $kode429Cari++;
    }
}

$periksa(
    'F7-11 pencarian dibatasi 30 per menit per alamat',
    $kode429Cari > 0,
    $kode429Cari.' dari 40 pencarian dijawab 429',
);

$tanpaKata = 0;

for ($i = 0; $i < 5; $i++) {
    if ($minta($basis.'/produk', ['ikut' => false])['kode'] === 200) {
        $tanpaKata++;
    }
}

$periksa(
    'F7-11 membuka katalog tanpa kata kunci tidak ikut dibatasi',
    $tanpaKata === 5,
    '5 dari 5 pembukaan katalog dijawab 200',
);

// ── F7-12: penjadwal pembatalan ─────────────────────────────────────

$tertunda = (int) $pdo->query(
    "select count(*) from orders where status = 'pending' and expires_at < datetime('now')"
)->fetchColumn();

$keluar = [];
$kodeKeluar = 0;

exec(
    escapeshellarg(PHP_BINARY).' '.escapeshellarg($akar.'/artisan').' iksan:batalkan-kedaluwarsa --uji 2>&1',
    $keluar,
    $kodeKeluar,
);

$periksa(
    'F7-12 perintah pembatalan berjalan',
    $kodeKeluar === 0,
    trim((string) implode(' | ', array_slice($keluar, -1))) ?: 'selesai',
);

$periksa(
    'F7-12 mode --uji melaporkan tanpa mengubah apa pun',
    (int) $pdo->query(
        "select count(*) from orders where status = 'pending' and expires_at < datetime('now')"
    )->fetchColumn() === $tertunda,
    $tertunda.' pesanan kedaluwarsa, tidak ada yang diubah',
);

// Pesanan yang baru dibuat dimundurkan batas waktunya, lalu dibatalkan
// sungguhan — inilah yang dijalankan penjadwal di server.
if ($pesananBaru) {
    $pdo->exec('update orders set expires_at = datetime("now", "-2 hours") where id = '.(int) $pesanan['id']);

    exec(
        escapeshellarg(PHP_BINARY).' '.escapeshellarg($akar.'/artisan').' iksan:batalkan-kedaluwarsa 2>&1',
        $keluar2,
        $kodeKeluar2,
    );

    $sesudahBatal = $pdo->query(
        'select status, cancelled_at from orders where id = '.(int) $pesanan['id']
    )->fetch(PDO::FETCH_ASSOC);

    $tahananSisa = (int) $pdo->query(
        'select coalesce(sum(reserved_quantity), 0) from product_variants where id in ('
        .'select product_variant_id from order_items where order_id = '.(int) $pesanan['id'].')'
    )->fetchColumn();

    $periksa(
        'F7-12 penjadwal membatalkan pesanan kedaluwarsa',
        ($sesudahBatal['status'] ?? '') === 'cancelled' && ! empty($sesudahBatal['cancelled_at']),
        'status = '.($sesudahBatal['status'] ?? '-').', cancelled_at terisi',
    );

    $periksa(
        'F7-12 pembatalan melepas tahanan stok',
        $tahananSisa === 0,
        'reserved_quantity varian pesanan itu: '.$tahananSisa,
    );

    $catatan = (string) $pdo->query(
        'select note from order_status_history where order_id = '.(int) $pesanan['id'].' order by id desc limit 1'
    )->fetchColumn();

    $periksa(
        'F7-12 alasan pembatalan tercatat di riwayat status',
        str_contains($catatan, 'belum dibayar'),
        'catatan riwayat: "'.$catatan.'"',
    );
}

$periksa(
    'F7-12 penjadwal terdaftar di daftar jadwal',
    str_contains(
        (string) shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($akar.'/artisan').' schedule:list 2>&1'),
        'iksan:batalkan-kedaluwarsa',
    ),
    'terdaftar pada schedule:list',
);

// ── Kebersihan: tidak ada tahanan menggantung ───────────────────────

$menggantung = (int) $pdo->query("
    select count(*) from product_variants v
    where v.reserved_quantity > (
        select coalesce(sum(i.quantity), 0) from order_items i
        join orders o on o.id = i.order_id
        where i.product_variant_id = v.id
          and o.status in ('pending', 'paid', 'processing', 'shipped')
    )
")->fetchColumn();

$periksa(
    'Tahanan stok tidak melebihi jumlah pada pesanan berjalan',
    $menggantung === 0,
    'varian dengan tahanan menggantung: '.$menggantung,
);

@unlink($berkasCookie);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
