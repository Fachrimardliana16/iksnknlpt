<?php

namespace App\Policies;

use App\Enums\PeranPengguna;
use App\Models\User;

/**
 * Kebijakan otorisasi panel (checklist F10-01).
 *
 * Middleware PastikanAdmin sudah menjaga rutenya: peran Pelanggan tidak dapat
 * menyentuh /admin sama sekali. Yang belum diatur adalah pembedaan antara Admin
 * dan Super Admin pada tindakan tertentu.
 *
 * Pembedaannya satu saja dan disengaja: tindakan yang dapat mengubah siapa yang
 * boleh masuk, atau mengubah identitas toko, hanya untuk Super Admin. Sisanya —
 * mengelola produk, memproses pesanan, menjawab pesan — terbuka bagi Admin,
 * karena itu pekerjaan harian dan membatasinya hanya memperlambat tanpa menambah
 * keamanan.
 *
 * Yang masuk kategori terbatas:
 *
 *   - mengubah pengaturan situs: memuat kredensial pembayaran dan kunci API
 *   - membuka blokir alamat: kebalikan dari pemblokiran, dan menyalahgunakannya
 *     berarti mematikan perlindungan brute force
 *   - mengelola akun panel: menaikkan peran orang lain
 *
 * Setiap pemeriksaan tetap dilakukan di server lewat authorize(). Menyembunyikan
 * tombolnya saja tidak cukup: permintaan Livewire dapat dikirim langsung tanpa
 * melewati antarmuka.
 */
class KebijakanPanel
{
    /** Melihat daftar apa pun di panel: semua pengguna panel. */
    public function lihat(User $user): bool
    {
        return $user->role->bisaMasukPanel();
    }

    /**
     * Mengelola isi katalog dan pesanan.
     *
     * Terbuka bagi Admin: menambah produk, memproses pesanan, dan menjawab
     * pesan adalah pekerjaan harian.
     */
    public function kelola(User $user): bool
    {
        return $user->role->bisaMasukPanel();
    }

    /** Mengubah pengaturan situs, termasuk kredensial pembayaran. */
    public function aturSitus(User $user): bool
    {
        return $user->role->bisaUbahKredensial();
    }

    /** Membuka blokir alamat yang dipasang otomatis. */
    public function bukaBlokir(User $user): bool
    {
        return $user->role->bisaUbahKredensial();
    }

    /** Mengubah peran, memblokir, atau menghapus akun panel. */
    public function kelolaAkun(User $user): bool
    {
        return $user->role->bisaUbahKredensial();
    }

    /**
     * Menghapus data yang menempel pada catatan uang.
     *
     * Produk yang pernah dipesan tidak boleh hilang begitu saja, karena baris
     * pesanan lama menunjuk ke sana. Yang diperiksa bukan perannya, melainkan
     * apakah datanya masih dipakai — dan itu ditangani di masing-masing
     * komponen. Di sini hanya dipastikan pemanggilnya pengguna panel.
     */
    public function hapus(User $user): bool
    {
        return $user->role->bisaMasukPanel();
    }

    /** Peran yang boleh melakukan tindakan terbatas, untuk dipakai di antarmuka. */
    public static function bolehAturSitus(?User $user): bool
    {
        return $user !== null && $user->role === PeranPengguna::SuperAdmin;
    }
}
