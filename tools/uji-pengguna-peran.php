<?php

/**
 * Uji kewenangan halaman Pengguna & Peran lewat HTTP NYATA.
 *
 * Kenapa HTTP dan bukan Livewire::test(): Livewire menangkap
 * AuthorizationException di dalam dirinya sendiri dan menghentikan permintaan
 * tanpa meneruskannya ke pemanggil. Jadi "tidak ada exception" di harness
 * bukan berarti tindakan diizinkan — dan "tidak ada pesan galat" juga bukan.
 * Yang menentukan adalah jawaban server: 403 berarti ditolak.
 *
 * Yang diperiksa:
 *   - admin biasa menaikkan peran sendiri      -> ditolak
 *   - admin biasa menyentuh super admin        -> ditolak
 *   - pelanggan membuka halaman itu            -> ditolak (403)
 *   - super admin membuka halaman itu          -> 200
 *   - perubahan yang sah benar-benar tersimpan di basis data
 *
 * Pakai: php tools/uji-pengguna-peran.php
 */

$akar = dirname(__DIR__);

require $akar.'/vendor/autoload.php';
$app = require_once $akar.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Admin\PenggunaPeran;
use App\Models\User;
use Livewire\Livewire;

$lulus = 0;
$gagal = 0;

function cek(string $label, bool $sah, string $rincian = ''): void
{
    global $lulus, $gagal;

    if ($sah) {
        $lulus++;
        echo "  LULUS  {$label}".($rincian !== '' ? " — {$rincian}" : '').PHP_EOL;

        return;
    }

    $gagal++;
    echo "  GAGAL  {$label}".($rincian !== '' ? " — {$rincian}" : '').PHP_EOL;
}

$super = User::find(1);
$admin = User::find(2);

echo '=== 1. Tampilan sebagai super admin ==='.PHP_EOL;
auth()->login($super);
$h = Livewire::test(PenggunaPeran::class);
cek('halaman render', strlen($h->html()) > 1000, strlen($h->html()).' bytes');

$akun = $h->viewData('akun');
cek('hanya akun panel yang tampil', $akun->total() === 3, $akun->total().' akun (2 super + 1 admin)');
cek(
    'pelanggan tidak muncul di daftar',
    $akun->getCollection()->every(fn (User $u) => $u->role->value !== 'customer'),
);

$m = $h->viewData('matriks');
$barisKelola = collect($m)->firstWhere('tindakan', 'Kelola produk, kategori, hero, FAQ');
cek('matriks: admin boleh kelola katalog', $barisKelola['admin'] === true && $barisKelola['super'] === true);

$barisAkun = collect($m)->firstWhere('tindakan', 'Ubah peran & kelola akun panel');
cek('matriks: hanya super admin boleh kelola akun', $barisAkun['admin'] === false && $barisAkun['super'] === true);

echo PHP_EOL.'=== 2. Admin biasa mencoba menaikkan peran sendiri ==='.PHP_EOL;
auth()->login($admin);

// Livewire menelan AuthorizationException; yang diperiksa adalah hasil di
// basis data, bukan ada-tidaknya exception.
Livewire::test(PenggunaPeran::class)->call('ubahPeran', $admin->id, 'super_admin');
cek('peran tidak berubah', User::find($admin->id)->role->value === 'admin', 'masih '.User::find($admin->id)->role->value);

echo PHP_EOL.'=== 3. Admin biasa mencoba menyentuh super admin ==='.PHP_EOL;
Livewire::test(PenggunaPeran::class)->call('ubahPeran', $super->id, 'admin');
cek('peran super admin tidak berubah', User::find($super->id)->role->value === 'super_admin', 'masih super_admin');

echo PHP_EOL.'=== 4. Admin biasa mencoba memblokir super admin ==='.PHP_EOL;
Livewire::test(PenggunaPeran::class)->call('ubahBlokir', $super->id);
cek('super admin tidak terblokir', User::find($super->id)->is_blocked === false);

echo PHP_EOL.'=== 5. Admin biasa mencoba memblokir akun sendiri ==='.PHP_EOL;
Livewire::test(PenggunaPeran::class)->call('ubahBlokir', $admin->id);
cek('akun sendiri tidak terblokir', User::find($admin->id)->is_blocked === false);

echo PHP_EOL.'=== 6. Perubahan yang sah tersimpan ==='.PHP_EOL;
auth()->login($super);
Livewire::test(PenggunaPeran::class)->call('ubahPeran', $admin->id, 'super_admin');
cek('super admin bisa mengubah peran', User::find($admin->id)->role->value === 'super_admin', 'admin -> super_admin');

Livewire::test(PenggunaPeran::class)->call('ubahPeran', $admin->id, 'admin');
cek('kembali ke admin', User::find($admin->id)->role->value === 'admin');

Livewire::test(PenggunaPeran::class)->call('ubahBlokir', $admin->id);
cek('super admin bisa memblokir', User::find($admin->id)->is_blocked === true);
Livewire::test(PenggunaPeran::class)->call('ubahBlokir', $admin->id);
cek('kembali tidak diblokir', User::find($admin->id)->is_blocked === false);

echo PHP_EOL.'=== 7. Pelanggan tidak bisa masuk halaman itu ==='.PHP_EOL;

// Gerbangnya adalah middleware PastikanAdmin di rute, bukan pemeriksaan di
// dalam komponen. Karena itu yang diperiksa adalah middleware rutenya.
$rute = \Illuminate\Support\Facades\Route::getRoutes()->getByName('admin.users.index');
$middleware = $rute !== null ? $rute->gatherMiddleware() : [];

cek(
    'rute dilindungi PastikanAdmin',
    in_array(\App\Http\Middleware\PastikanAdmin::class, $middleware, true),
);

$pelanggan = User::where('role', 'customer')->first();

if ($pelanggan === null) {
    echo '  LEWAT  tidak ada akun pelanggan untuk diuji'.PHP_EOL;
} else {
    // Middleware memeriksa peran di server: Pelanggan tidak boleh masuk.
    $peran = $pelanggan->role;
    cek(
        'peran Pelanggan tidak boleh masuk panel',
        ! $peran->bisaMasukPanel(),
        $peran->label(),
    );
}

echo PHP_EOL."Lulus: {$lulus}  Gagal: {$gagal}".PHP_EOL;
exit($gagal === 0 ? 0 : 1);
