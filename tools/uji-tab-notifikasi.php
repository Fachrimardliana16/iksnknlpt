<?php

/**
 * Uji tab Notifikasi lewat render Livewire sungguhan.
 *
 * Curl biasa tidak berguna di sini: komponen dirender Livewire, dan isi tab
 * hanya muncul sesudah pilihTab() dijalankan. Yang diperiksa karena itu:
 * baris pengaturan benar-benar ada, tipe dan nilainya benar, dan komponen
 * yang dirender lewat Livewire::test() memuat kedua kelompok sakelar.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\GrupPengaturan;
use App\Livewire\Admin\PengaturanSitus;
use App\Models\Setting;
use App\Models\User;
use App\Support\KomponenPengaturan;
use App\Support\Pengaturan;
use App\Support\SakelarNotifikasi;
use Livewire\Livewire;

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

echo "Baris pengaturan grup 'notification':\n";

$baris = Setting::query()->where('group_name', GrupPengaturan::Notifikasi->value)->get();

periksa('Jumlah baris sakelar', $baris->count() === 10, $baris->count().' baris');

foreach ($baris as $s) {
    periksa(
        $s->key,
        $s->type === 'boolean' && $s->value === '1',
        'type='.$s->type.' nilai='.var_export($s->value, true),
    );
}

echo "\nRender komponen (login sebagai super admin):\n";

$admin = User::query()->where('role', 'super_admin')->first();

if ($admin === null) {
    fwrite(STDERR, "Tidak ada akun super admin — jalankan UserSeeder lebih dulu.\n");
    exit(1);
}

auth()->login($admin);

Pengaturan::segarkan();

$komponen = Livewire::test(PengaturanSitus::class)->call('pilihTab', GrupPengaturan::Notifikasi->value);

periksa('Tab aktif menjadi notification', $komponen->get('tab') === 'notification', $komponen->get('tab'));

foreach ([
    'Butir lonceng panel',
    'Notifikasi yang dikirim',
    'Stok habis',
    'Stok menipis',
    'Pesanan siap dikirim',
    'Pesanan hampir batal otomatis',
    'Produk masuk keranjang',
    'Surel/WA saat pesanan dibuat',
    'Surel/WA saat pesanan dibayar',
    'Surel/WA saat pesanan dikirim',
    'Push ke perangkat admin',
    'Surel cadangan bila push gagal',
] as $cari) {
    $komponen->assertSee($cari);
    periksa('Label tampil: '.$cari, true, 'ditemukan di HTML render');
}

echo "\nMematikan satu sakelar lewat komponen:\n";

$komponen->set('nilai.notif_item_stok_habis', false)->call('simpan')->assertHasNoErrors();

$tersimpan = Setting::query()->where('key', 'notif_item_stok_habis')->value('value');
periksa('Nilai tersimpan "0"', $tersimpan === '0', var_export($tersimpan, true));

Pengaturan::segarkan();
periksa('SakelarNotifikasi membaca MATI', SakelarNotifikasi::butirAktif('stok-habis') === false, 'butirAktif(stok-habis) = false');
periksa('Sakelar lain tetap AKTIF', SakelarNotifikasi::butirAktif('stok-menipis') === true, 'butirAktif(stok-menipis) = true');

echo "\nMenyalakan kembali:\n";

$komponen->set('nilai.notif_item_stok_habis', true)->call('simpan')->assertHasNoErrors();

$tersimpan = Setting::query()->where('key', 'notif_item_stok_habis')->value('value');
periksa('Nilai tersimpan "1"', $tersimpan === '1', var_export($tersimpan, true));

Pengaturan::segarkan();
periksa('SakelarNotifikasi membaca AKTIF', SakelarNotifikasi::butirAktif('stok-habis') === true, 'butirAktif(stok-habis) = true');

echo "\nHasil: {$lulus} lulus, {$gagal} gagal\n";
exit($gagal === 0 ? 0 : 1);
