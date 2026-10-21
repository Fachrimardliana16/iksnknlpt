<?php

/**
 * Uji cetak resi BANYAK sekaligus (permintaan sungguhan, PDF sungguhan).
 *
 * Memeriksa janji yang mudah rusak tanpa ketahuan:
 *   1. Satu ULID  -> satu label, berlaku seperti resi tunggal.
 *   2. Tiga ULID  -> satu PDF berisi tiga label.
 *   3. Nol/pesan tak dikenal -> PDF tetap tercipta, tidak galat 500.
 *   4. ULID pesanan yang sudah dibatalkan -> label bertanda SALINAN.
 *
 * Ditulis di sini (bukan hanya tes Livewire) karena yang diuji adalah berkas
 * PDF sungguhan: ukuran halaman, jumlah halaman, dan tanda air. Tes yang hanya
 * memeriksa status HTTP tidak akan pernah tahu label kedua kosong.
 *
 * Pakai: php tools/uji-cetak-banyak.php
 */

require __DIR__.'/../vendor/autoload.php';

use App\Enums\StatusPesanan;
use App\Models\Order;
use Illuminate\Http\Request;

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Kernel console dinyalakan lebih dulu: tanpa bootstrap ini, koneksi basisdata
// belum terpasang dan Model::query() jatuh dengan "connection() on null" —
// galat yang tidak menunjuk ke penyebab sebenarnya.
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pengguna = App\Models\User::query()->where('role', 'super_admin')->firstOrFail();
auth()->login($pengguna);

function minta(string $ulid): array
{
    global $kernel;

    $req = Request::create('/admin/pesanan/cetak-resi', 'GET', ['ulid' => $ulid]);
    $req->setLaravelSession(app('session.store'));

    $res = $kernel->handle($req);
    $isi = $res->getContent();

    return [
        'status' => $res->getStatusCode(),
        'tipe' => (string) $res->headers->get('Content-Type'),
        'nama' => (string) $res->headers->get('Content-Disposition'),
        'byte' => strlen($isi),
        'pdf' => substr($isi, 0, 5),
        'halaman' => substr_count($isi, '/Type /Page') ?: substr_count($isi, '/Type/Page'),
        // CATATAN: string "SALINAN" TIDAK dicari di dalam berkas PDF. Isi teks
        // PDF dikompresi (FlateDecode), jadi mencari kata hanya menghasilkan
        // angka nol yang menyesatkan. Yang diperiksa di bawah adalah BERKAS
        // HTML yang dirender view — tempat tanda air benar-benar ada — dan
        // pencocokan label PDF sudah diuji terpisah (uji-cetak-banyak-baris.php).
        'salinan_html' => null,
    ];
}

$pesanan = Order::query()->orderByDesc('id')->take(3)->get();

if ($pesanan->count() < 2) {
    echo "PERINGATAN: butuh minimal 2 pesanan di basis data untuk uji ini.\n";
    exit(0);
}

echo "== 1. Satu ULID (label tunggal) ==\n";
$satu = minta($pesanan->first()->ulid);
print_r($satu);

echo "\n== 2. Tiga ULID (banyak label) ==\n";
$banyak = minta($pesanan->pluck('ulid')->implode(','));
print_r($banyak);

echo "\n== 3. ULID tidak dikenal ==\n";
$takDikenal = minta('NGAWUR-ULID-123');
print_r($takDikenal);

echo "\n== 4. Parameter kosong ==\n";
$kosong = minta('');
print_r($kosong);

echo "\n== 5. Pesanan yang sudah dibatalkan (tanda air SALINAN) ==\n";
$batal = Order::query()->where('status', StatusPesanan::Cancelled->value)->first();
echo $batal === null ? "tidak ada pesanan batal di basis data\n" : '';
if ($batal) {
    print_r(minta($batal->ulid));
}

echo "\n== 5b. Tanda air SALINAN di HTML label ==\n";
if ($batal) {
    $htmlLabel = view('cetak.label-pesanan', [
        'order' => $batal,
        'toko' => App\Support\Pengaturan::instance(),
        'alamat' => $batal->alamat(),
        'salinan' => true,
        'nomorSeri' => 1,
        'totalLabel' => 1,
    ])->render();

    echo "HTML label: ".(str_contains($htmlLabel, 'SALINAN') ? 'MEMUAT tanda air' : 'TANPA tanda air')."\n";
    echo "nomor pesanan di label: ".(str_contains($htmlLabel, $batal->order_number) ? 'ada' : 'HILANG')."\n";
}

echo "\n== 6. Pencatatan printed_at ==\n";
$order = $pesanan->first();
$order->forceFill(['printed_at' => null])->save();
minta($order->ulid);
$order->refresh();
$cetak = $order->getRawOriginal('printed_at');
echo "printed_at sesudah cetak: ".(is_string($cetak) ? $cetak : 'BELUM TERISI')."\n";

echo "catatan aktivitas resi.dicetak: ";
echo DB_ACTIVITY_BANTUAN($order);

function DB_ACTIVITY_BANTUAN(Order $order): string
{
    $n = Illuminate\Support\Facades\DB::table('activity_logs')
        ->where('action', 'resi.dicetak')
        ->where('subject_type', $order->getMorphClass())
        ->where('subject_id', $order->id)
        ->count();

    return $n.' baris untuk '.$order->order_number;
}
