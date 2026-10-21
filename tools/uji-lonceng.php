<?php

/**
 * Uji lonceng notifikasi pada data KERJA (bukan basis data test).
 *
 * Memeriksa janji yang diminta: lencana hanya menghitung butir yang belum
 * dilihat, membuka satu butir mengurangi angkanya satu, dan butir yang sudah
 * dilihat tetap tampil di daftar.
 *
 * Skrip ini menulis lalu MENGHAPUS tanda bacanya sendiri, supaya data kerja
 * tidak berubah setelah dijalankan.
 *
 * Pakai: php tools/uji-lonceng.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Livewire\Admin\LoncengNotifikasi;
use App\Models\NotificationRead;
use App\Models\User;
use App\Support\NotifikasiAdmin;
use Livewire\Livewire;

$admin = User::query()->where('role', 'super_admin')->firstOrFail();
auth()->login($admin);

function tampilkan(string $judul): void
{
    $butir = NotifikasiAdmin::semua(auth()->user());
    $belum = $butir->where('dilihat', false)->count();

    echo "\n=== {$judul} ===\n";
    echo "lencana (belum dilihat): {$belum} dari ".$butir->count()." butir\n";

    foreach ($butir as $b) {
        printf(
            "  %-26s %-5s %-10s %s\n",
            $b['kunci'],
            $b['jumlah'].'x',
            $b['dilihat'] ? 'DILIHAT' : 'BARU',
            mb_strimwidth($b['teks'], 0, 46, '…'),
        );
    }
}

// Bersihkan sisa percobaan sebelumnya.
NotificationRead::query()->where('user_id', $admin->id)->delete();

tampilkan('SEBELUM dibuka');

// Komponen dirender sungguhan, seperti saat panel dibuka admin.
$awal = Livewire::actingAs($admin)
    ->test(LoncengNotifikasi::class)
    ->call('alihkan')
    ->assertSee($admin ? 'Perlu perhatian' : '')
    ->get('buka');

echo "\npanel terbuka di render: ".($awal ? 'ya' : 'tidak')."\n";

$sebelum = NotifikasiAdmin::jumlahBelumDilihat($admin);
echo "lencana sebelum klik: {$sebelum}\n";

// Buka satu butir, seperti admin mengklik baris pertama.
$kunci = NotifikasiAdmin::semua($admin)->first()['kunci'] ?? null;

if ($kunci === null) {
    echo "TIDAK ADA BUTIR untuk diuji.\n";
    exit(0);
}

Livewire::actingAs($admin)
    ->test(LoncengNotifikasi::class)
    ->call('bukaButir', $kunci);

$sesudah = NotifikasiAdmin::jumlahBelumDilihat($admin);
echo "klik butir '{$kunci}': lencana {$sebelum} menjadi {$sesudah}\n";

tampilkan('SESUDAH satu butir dibuka');

// Pastikan butir itu masih tampil, hanya berstatus dilihat.
$butir = NotifikasiAdmin::semua($admin)->firstWhere('kunci', $kunci);
echo "\nbutir '{$kunci}' masih di daftar: ".(($butir !== null) ? 'ya' : 'TIDAK')
    .", status: ".($butir['dilihat'] ? 'DILIHAT' : 'BARU')."\n";

// Tandai semua, lalu kembalikan data kerja ke keadaan semula.
$n = NotifikasiAdmin::tandaiSemuaDilihat($admin);
echo "tandai semua: {$n} butir, lencana sekarang ".NotifikasiAdmin::jumlahBelumDilihat($admin)."\n";

NotificationRead::query()->where('user_id', $admin->id)->delete();
echo "tanda baca dibersihkan, lencana kembali ".NotifikasiAdmin::jumlahBelumDilihat($admin)."\n";
