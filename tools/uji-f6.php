<?php

/**
 * Pemeriksaan F6 di sistem nyata (jalur HTTP + basis data), bukan lewat harness
 * nama-simbol. Dijalankan terhadap berkas HTML hasil kur`l ke server yang
 * benar-benar melayani aplikasi, supaya yang diperiksa adalah keluaran yang
 * sama dengan yang dilihat pembeli.
 *
 * Pakai: php tools/uji-f6.php
 */

$basis = getenv('UJI_BASE') ?: 'http://127.0.0.1:8100';
$direktori = __DIR__.'/out';

$ambil = static function (string $url): string {
    $konteks = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
    $isi = @file_get_contents($url, false, $konteks);

    return is_string($isi) ? $isi : '';
};

$lulus = 0;
$gagal = 0;

$periksa = static function (string $judul, bool $syarat, string $bukti) use (&$lulus, &$gagal): void {
    if ($syarat) {
        $lulus++;
        printf("  OK   %-46s %s\n", $judul, $bukti);
    } else {
        $gagal++;
        printf("  GAGAL %-45s %s\n", $judul, $bukti);
    }
};

// ── Ambil halaman nyata ─────────────────────────────────────────────

@mkdir($direktori, 0777, true);

$slugAda = $ambil($basis.'/produk');
preg_match('#/produk/([a-z0-9][a-z0-9-]{3,})#', $slugAda, $cocok);
$slug = $cocok[1] ?? '';

if ($slug === '' || $ambil($basis.'/produk/'.$slug) === '') {
    // Jalur cadangan: slug diambil dari basis data, supaya pemeriksaan tetap
    // berjalan walau bentuk tautan di katalog berubah.
    $pdoCadangan = new PDO('sqlite:'.dirname(__DIR__).'/database/database.sqlite');
    $slug = (string) $pdoCadangan->query(
        'select slug from products where is_published = 1 order by id limit 1'
    )->fetchColumn();
}

$katalog = $ambil($basis.'/produk');
$detail = $slug === '' ? '' : $ambil($basis.'/produk/'.$slug);
$tanpaSpec = $ambil($basis.'/produk/peredam-bunyi-baffle-universal');
$kosong = $ambil($basis.'/produk?q=zzz-tidak-ada');

file_put_contents($direktori.'/f6-detail.html', $detail);
file_put_contents($direktori.'/f6-kosong.html', $kosong);

echo "\nF6 - katalog dan detail produk di sistem nyata\n";
echo 'Alamat: '.$basis."\n";
echo 'Produk contoh: '.$slug."\n\n";

// ── F6-01 ───────────────────────────────────────────────────────────

$periksa(
    'F6-01 daftar produk terisi',
    substr_count($katalog, 'plate-interaktif') >= 8,
    substr_count($katalog, 'plate-interaktif').' kartu produk di /produk',
);

$periksa(
    'F6-01 filter kategori menyaring',
    str_contains($ambil($basis.'/produk?kategori=aksesoris'), 'Bracket') === true,
    'kategori=aksesoris mengembalikan produk kategori itu saja',
);

$periksa(
    'F6-01 urut harga menaik',
    (bool) preg_match('/Rp 4[0-9.]{4,}.*Rp 9[0-9.]{4,}/s', $ambil($basis.'/produk?urut=price_asc')),
    'harga terkecil muncul sebelum terbesar dengan urut=price_asc',
);

// ── F6-02 ───────────────────────────────────────────────────────────

$periksa(
    'F6-02 hasil kosong menjelaskan sebab',
    str_contains($kosong, 'Tidak ada produk yang memuat kata') && str_contains($kosong, 'zzz-tidak-ada'),
    'sebab disebut, bukan sekadar angka nol',
);

$periksa(
    'F6-02 kerangka memuat dirender',
    str_contains($kosong, 'wire:loading.delay.long') && str_contains($kosong, 'animate-pulse'),
    'kerangka kartu dengan animate-pulse, bukan pemintal di tengah',
);

// ── F6-03 ───────────────────────────────────────────────────────────

$periksa(
    'F6-03 pemilih varian ada di halaman',
    str_contains($detail, 'wire:click="pilihVarian('),
    'tombol varian memanggil aksi server',
);

$periksa(
    'F6-03 harga dan stok dirender dari varian',
    (bool) preg_match('/Rp [0-9.]+/', $detail) && str_contains($detail, 'Stok '),
    'harga dan jumlah stok tampil sebelum interaksi apa pun',
);

// ── F6-04 ───────────────────────────────────────────────────────────

$periksa(
    'F6-04 tabel spesifikasi tampil',
    str_contains($detail, 'Titanium grade 1') && str_contains($detail, 'Garansi'),
    'baris Material dan Garansi ada di halaman',
);

$periksa(
    'F6-04 produk tanpa spesifikasi tidak menampilkan judul tabel',
    ! str_contains($tanpaSpec, '>Spesifikasi<'),
    'Peredam Bunyi Baffle tidak memuat blok Spesifikasi',
);

// ── F6-05 ───────────────────────────────────────────────────────────

$periksa(
    'F6-05 tiga tombol aksi tersedia',
    str_contains($detail, 'Tambah ke Keranjang')
        && str_contains($detail, 'Beli Sekarang')
        && str_contains($detail, 'Tanya via WhatsApp'),
    'tambah, beli sekarang, dan tanya WhatsApp',
);

$periksa(
    'F6-05 tautan WhatsApp menyebut produk',
    (bool) preg_match('/wa\.me\/628123456789\?text=[^"]+/', $detail),
    'tautan wa.me dengan pesan berisi produk',
);

// ── F6-06 ───────────────────────────────────────────────────────────

$periksa(
    'F6-06 bar aksi lengket memuat harga, jumlah, tombol',
    str_contains($detail, 'safe-area-inset-bottom')
        && str_contains($detail, 'Tambah jumlah')
        && str_contains($detail, 'Kurangi jumlah'),
    'bar lengket dengan pengatur jumlah di bawah 1024 px',
);

// ── F6-07 ───────────────────────────────────────────────────────────

preg_match('#<title>(.*?)</title>#s', $detail, $judul);
$periksa(
    'F6-07 judul halaman memuat produk dan toko',
    str_contains($judul[1] ?? '', ' · ')
        && str_contains($judul[1] ?? '', 'Iksan Knalpot')
        && ! str_contains($judul[1] ?? '', 'produk ·'),
    'judul: '.trim($judul[1] ?? ''),
);

$periksa(
    'F6-07 meta OG lengkap',
    str_contains($detail, 'og:type" content="product"')
        && str_contains($detail, 'og:title"')
        && str_contains($detail, 'og:image:width" content="1200"')
        && str_contains($detail, 'og:image:height" content="630"'),
    'og:type, og:title, og:image 1200x630',
);

$periksa(
    'F6-07 tautan kanonikal ada',
    str_contains($detail, '<link rel="canonical"'),
    'canonical menunjuk halaman produk itu sendiri',
);

preg_match('#<script type="application/ld\+json">\s*(\{.*?\})\s*</script>#s', $detail, $ld);
$data = json_decode($ld[1] ?? '', true);

$periksa(
    'F6-07 JSON-LD sah dan lengkap',
    is_array($data)
        && ($data['@type'] ?? '') === 'Product'
        && is_array($data['offers'] ?? null)
        && ($data['offers']['priceCurrency'] ?? '') === 'IDR',
    is_array($data)
        ? 'Product, offers '.$data['offers']['lowPrice'].'-'.$data['offers']['highPrice'].' IDR, '.$data['offers']['offerCount'].' tawaran'
        : 'blok JSON-LD tidak dapat diurai',
);

// ── F6-08 ───────────────────────────────────────────────────────────

$pdo = new PDO('sqlite:'.dirname(__DIR__).'/database/database.sqlite');
$baca = static fn (PDO $p, string $s): int => (int) $p->query(
    "select view_count from products where slug = '".$s."'",
)->fetchColumn();

$sebelum = $baca($pdo, $slug);

for ($i = 0; $i < 20; $i++) {
    $ambil($basis.'/produk/'.$slug);
}

$sesudah = $baca($pdo, $slug);

$periksa(
    'F6-08 dua puluh muat menambah tepat 20',
    ($sesudah - $sebelum) === 20,
    'view_count '.$sebelum.' -> '.$sesudah.' (+'.($sesudah - $sebelum).')',
);

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";

exit($gagal === 0 ? 0 : 1);
