<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;

/**
 * Dasar seluruh komponen panel admin.
 *
 * KENAPA INI ADA. Middleware `auth` dan `PastikanAdmin` hanya berjalan saat
 * HALAMAN pertama dimuat. Setelah halaman hidup, setiap tombol mengirim
 * permintaannya ke /livewire/update — rute milik Livewire sendiri, yang hanya
 * memakai grup `web`. Middleware rute asal tidak ikut.
 *
 * Akibatnya, method publik apa pun pada komponen admin dapat dipanggil oleh
 * siapa saja yang memegang sidik komponen itu. Sebelum kelas ini ada, seorang
 * PELANGGAN dapat memanggil `hapus()` pada ProdukDaftar dan produknya benar-
 * benar terhapus — terbukti di OtorisasiAksiAdminTest, bukan dugaan.
 *
 * CARA KERJANYA: Livewire memanggil `boot()` pada SETIAP siklus permintaan
 * komponen, termasuk /livewire/update, dan sebelum mount() maupun method
 * aksinya. Karena itu penjagaan ditaruh di sana — bukan di mount(), yang
 * bertabrakan dengan mount($ulid) milik komponen yang memuat model.
 *
 * Yang diperiksa di sini adalah syarat minimum: pemanggilnya pengguna panel
 * yang masih aktif. Pembedaan Admin dan Super Admin tetap milik KebijakanPanel
 * dan dipanggil lewat authorize() di dalam method yang membutuhkannya —
 * menaruhnya di sini akan menutup pekerjaan harian Admin biasa.
 */
abstract class KomponenAdmin extends Component
{
    public function boot(): void
    {
        $this->pastikanPenggunaPanel();
    }

    /**
     * Syarat minimum untuk menyentuh panel sama sekali.
     *
     * Aman dipanggil berulang; komponen yang butuh pemeriksaan lebih ketat
     * memanggilnya lagi di method yang bersangkutan.
     */
    protected function pastikanPenggunaPanel(): void
    {
        $pengguna = auth()->user();

        if (! $pengguna instanceof User) {
            abort(403, 'Masuk sebagai pengguna panel untuk melakukan ini.');
        }

        $peran = $pengguna->role;

        if ($peran === null || ! $peran->bisaMasukPanel()) {
            \App\Support\Keamanan::catat(
                \App\Enums\TipeKejadianKeamanan::AksesTidakSah,
                request(),
                $pengguna->email,
                $pengguna->id,
                'Pelanggan mencoba menjalankan aksi panel lewat Livewire',
            );

            abort(403, 'Akun ini tidak memiliki akses ke panel admin.');
        }

        if ($pengguna->is_blocked) {
            abort(403, 'Akun ini diblokir.');
        }
    }
}
