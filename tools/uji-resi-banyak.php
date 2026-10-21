<?php

/**
 * Loop ketat: cetak resi banyak harus menghasilkan BANYAK label.
 *
 * Gejala: "cetak resi kok malah jadi 1 ya? padahal pesanan berbeda".
 *
 * Yang diperiksa adalah sifat yang terlihat langsung oleh admin: berapa halaman
 * dalam berkas PDF-nya. Bukan hanya 200, bukan hanya ada "label" di HTML —
 * jumlah halaman, dan nomor pesanan mana yang benar-benar tercetak.
 *
 * Pakai: php tools/uji-resi-banyak.php
 */

$basis = getenv('UJI_BASE') ?: 'http://127.0.0.1:8100';
$akar = dirname(__DIR__);

require $akar.'/vendor/autoload.php';

$app = require $akar.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make(Illuminate\Contracts\Http\Kernel::class);

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

/**
 * Menghitung halaman PDF dari byte-nya.
 *
 * /Type /Page (bukan /Pages) adalah penanda objek halaman. Dicari mentah, bukan
 * lewat pustaka, karena yang diuji adalah perilaku dokumen yang sungguh
 * diunduh admin — termasuk kekacauan yang bisa dibuat pustaka PDF mana pun.
 */
$hitungHalaman = static function (string $pdf): int {
    preg_match_all('/\/Type\s*\/Page[^s]/', $pdf, $m);

    return count($m[0]);
};

// Teks dibaca lewat pembaca terpisah: dompdf mengompres stream isinya, jadi
// membaca byte mentah akan menyimpulkan "nomor tidak pernah tercetak" padahal
// salahnya cara membacanya.
$bacaTeksPdf = require __DIR__.'/_baca-pdf.php';

/** Nomor pesanan yang benar-benar tercetak, dibaca dari teks PDF. */
$nomorDalamPdf = static function (string $pdf) use ($bacaTeksPdf): array {
    preg_match_all('/IK-[0-9]{8}-[0-9]{4}|IK-T1/', $bacaTeksPdf($pdf), $m);

    return array_values(array_unique($m[0]));
};

echo "\nUJI RESI BANYAK — satu berkas, banyak label\n";
echo 'Alamat: '.$basis."\n\n";

// ── 1. Lewat HTTP sebagai admin ────────────────────────────────────────
// Login lewat sesi PHP langsung: yang diuji adalah pembuatan PDF-nya, bukan
// alur masuk panel (sudah diuji di tempat lain dan sedang diperbaiki).

$jar = tempnam(sys_get_temp_dir(), 'resi-');

$cookies = [];
foreach (glob($akar.'/storage/framework/sessions/*') ?: [] as $f) {
    $isi = file_get_contents($f);
    if (str_contains((string) $isi, 'login_web') || str_contains((string) $isi, 'Auth')) {
        $cookies[] = [basename($f), $isi];
    }
}

echo "  Sesi dengan tanda masuk ditemukan: ".count($cookies)."\n";

// Ambil sesi yang sah dengan mencoba? Tidak — pakai LoginEvent sekali jalan.
$admin = App\Models\User::query()->whereIn('role', ['super_admin', 'admin'])->first();

if ($admin === null) {
    echo "  Tidak ada pengguna admin di basis data ini. Uji dilewati.\n";
    exit(1);
}

Illuminate\Support\Facades\Auth::login($admin);

// Render controller langsung dengan request yang sudah terautentikasi,
// supaya yang diukur HTML yang masuk ke dompdf — bukan HTML hasil login.
$request = Illuminate\Http\Request::create('/admin/pesanan/cetak-resi', 'GET');
$request->setUserResolver(fn () => $admin);
app()->instance('request', $request);
Illuminate\Support\Facades\Auth::shouldUse('web');

$pesanan = App\Models\Order::query()->orderByDesc('id')->limit(3)->get();

if ($pesanan->count() < 2) {
    echo "  Butuh minimal 2 pesanan; ada ".$pesanan->count().". Uji dilewati.\n";
    exit(1);
}

$ulid = $pesanan->pluck('ulid')->implode(',');
$nomorHarapan = $pesanan->pluck('order_number')->all();

echo "  Pesanan diuji: ".implode(', ', $nomorHarapan)."\n\n";

$permalink = 'ulid='.urlencode($ulid);
$req = Illuminate\Http\Request::create('/admin/pesanan/cetak-resi?'.$permalink, 'GET');
$req->setUserResolver(fn () => $admin);

app()->instance('request', $req);
Illuminate\Support\Facades\Auth::shouldUse('web');

$respons = (new App\Http\Controllers\Admin\ResiBanyak)($req);

$isi = $respons->getContent();
$kode = $respons->getStatusCode();

$periksa(
    'Cetak banyak: kode jawaban 200 dan isi PDF',
    $kode === 200 && str_starts_with((string) $isi, '%PDF'),
    'HTTP '.$kode.', '.strlen((string) $isi).' bytes, tanda %PDF: '.(str_starts_with((string) $isi, '%PDF') ? 'ada' : 'TIDAK ADA'),
);

$jumlahHalaman = $hitungHalaman((string) $isi);

$periksa(
    'PDF memuat satu halaman per pesanan',
    $jumlahHalaman === $pesanan->count(),
    $jumlahHalaman.' halaman / '.$pesanan->count().' pesanan',
);

$tercetak = $nomorDalamPdf((string) $isi);
$kurang = array_diff($nomorHarapan, $tercetak);

$periksa(
    'Semua nomor pesanan benar-benar tercetak',
    $kurang === [],
    $kurang === [] ? implode(' ', $nomorHarapan) : 'tidak ada: '.implode(' ', $kurang),
);

$nama = '';
foreach ($respons->headers->all() as $k => $v) {
    if (strtolower($k) === 'content-disposition') {
        $nama = is_array($v) ? implode('', $v) : (string) $v;
    }
}

$periksa(
    'Nama berkas menyebut jumlah pesanan',
    str_contains($nama, (string) $pesanan->count()) || str_contains($nama, $pesanan->first()->order_number),
    $nama,
);

// ── 2. Satu pesanan dipilih ───────────────────────────────────────────
$satu = App\Models\Order::query()->orderByDesc('id')->first();
$req1 = Illuminate\Http\Request::create('/admin/pesanan/cetak-resi?ulid='.urlencode($satu->ulid), 'GET');
$req1->setUserResolver(fn () => $admin);
app()->instance('request', $req1);

$responsSatu = (new App\Http\Controllers\Admin\ResiBanyak)($req1);
$isiSatu = (string) $responsSatu->getContent();
$halamanSatu = $hitungHalaman($isiSatu);

$periksa(
    'Satu pesanan: satu halaman',
    $halamanSatu === 1,
    $halamanSatu.' halaman',
);

// ── 3. Tanpa pilihan ──────────────────────────────────────────────────
$reqKosong = Illuminate\Http\Request::create('/admin/pesanan/cetak-resi', 'GET');
$reqKosong->setUserResolver(fn () => $admin);
app()->instance('request', $reqKosong);

try {
    (new App\Http\Controllers\Admin\ResiBanyak)($reqKosong);
    $periksa('Tanpa pilihan: ditolak dengan pesan', false, 'tidak ada galat');
} catch (Illuminate\Validation\ValidationException $e) {
    $periksa(
        'Tanpa pilihan: ditolak dengan pesan',
        str_contains($e->validator->errors()->first(), 'Tidak ada pesanan yang dipilih'),
        $e->validator->errors()->first(),
    );
}

// ── 4. Lebih dari batas ───────────────────────────────────────────────
// Hanya bila basis data sungguh punya lebih dari BATAS pesanan. Di basis data
// pengembangan yang kecil, tes ini tidak dapat dijalankan dan batasnya sudah
// diuji di tests/Feature/ResiBanyakTest.php (dengan RefreshDatabase, 101 baris
// dibuat di sana).
$jumlahPesanan = App\Models\Order::query()->count();

if ($jumlahPesanan > App\Http\Controllers\Admin\ResiBanyak::BATAS) {
    $lebihBatas = array_fill(0, App\Http\Controllers\Admin\ResiBanyak::BATAS + 1, $semuaUlid[0]);

    $reqBanyak = Illuminate\Http\Request::create(
        '/admin/pesanan/cetak-resi?ulid='.urlencode(implode(',', $lebihBatas)),
        'GET',
    );
    $reqBanyak->setUserResolver(fn () => $admin);
    app()->instance('request', $reqBanyak);

    $responsBatas = (new App\Http\Controllers\Admin\ResiBanyak)($reqBanyak);
    $pesanBatas = (string) session('galat-admin', '');

    $periksa(
        'Lebih dari '.App\Http\Controllers\Admin\ResiBanyak::BATAS.': diberi pesan, bukan berkas',
        $responsBatas->getStatusCode() === 302 && str_contains($pesanBatas, 'Maksimal'),
        'HTTP '.$responsBatas->getStatusCode().', pesan: '.$pesanBatas,
    );
} else {
    // ULID yang sama diulang bukan 101 pilihan: array_unique() menyatukannya,
    // jadi ini bukan cara menguji batas. Yang menguji batas butuh 101 ULID
    // BERBEDA — ada di tests/Feature/ResiBanyakTest.php.
    echo "  LEWAT Lebih dari ".App\Http\Controllers\Admin\ResiBanyak::BATAS
        .': basis data hanya punya '.$jumlahPesanan." pesanan.\n";
    echo "         Batas diuji di tests/Feature/ResiBanyakTest.php (101 baris dibuat).\n";
}

echo "\nHasil: ".$lulus.' lulus, '.$gagal." gagal\n";

exit($gagal === 0 ? 0 : 1);
