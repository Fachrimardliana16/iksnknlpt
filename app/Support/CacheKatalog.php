<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Penanda perubahan katalog (checklist F3-14, F11-01).
 *
 * Cache daftar produk di halaman depan disimpan per kombinasi saringan, jadi
 * jumlah kuncinya tidak terbatas. Menghapusnya satu per satu berarti harus
 * menebak saringan mana yang pernah dikunjungi pembeli — dan tebakan yang
 * salah meninggalkan halaman yang menampilkan produk yang sudah ditarik.
 *
 * Karena itu yang dipakai adalah penanda versi: seluruh kunci cache memuat
 * nomor versi, dan menaikkan nomor itu membuat semua kunci lama tidak lagi
 * cocok tanpa perlu dihapus. Kunci lama akan kedaluwarsa sendiri.
 *
 * Menaikkan versi dijalankan pada setiap perubahan yang mengubah tampilan
 * katalog: produk disimpan, diterbitkan, dihapus, kategori dipindah, atau
 * gambarnya berubah.
 */
class CacheKatalog
{
    private const KUNCI_VERSI = 'katalog:versi';

    /** Nomor versi saat ini, dipakai sebagai bagian dari setiap kunci cache. */
    public static function versi(): int
    {
        // Diingat selama satu permintaan.
        //
        // Setiap kunci cache memanggil metode ini, dan penyimpan cache bawaan
        // adalah basis data — jadi tanpa mengingatnya, satu halaman yang
        // memakai tiga kunci membayar tiga kueri hanya untuk membaca satu
        // angka yang tidak berubah di tengah permintaan.
        if (self::$versiPermintaan !== null) {
            return self::$versiPermintaan;
        }

        try {
            return self::$versiPermintaan = (int) Cache::get(self::KUNCI_VERSI, 1);
        } catch (Throwable) {
            // Cache yang tidak dapat dibaca tidak boleh menjatuhkan halaman.
            // Versi 0 berarti seluruh kunci dianggap tidak cocok, sehingga
            // halaman dirender ulang dari basis data.
            return self::$versiPermintaan = 0;
        }
    }

    /** Versi yang sudah dibaca pada permintaan ini. */
    private static ?int $versiPermintaan = null;

    /** Melupakan versi yang diingat. Dipakai oleh test antar-permintaan. */
    public static function lupakanVersi(): void
    {
        self::$versiPermintaan = null;
    }

    /**
     * Menandai katalog berubah.
     *
     * Menaikkan versi membuat seluruh kunci lama tidak terpakai lagi. Cara ini
     * dipilih daripada menghapus per pola karena pola tidak bekerja sama di
     * semua penyimpan cache, dan penghapusan yang gagal sebagian justru
     * meninggalkan halaman usang.
     */
    public static function tandaiBerubah(?string $sebab = null): void
    {
        try {
            $baru = self::versi() + 1;

            Cache::forever(self::KUNCI_VERSI, $baru);

            // Memo di memori ikut diperbarui, bukan hanya nilai di cache.
            //
            // Tanpa ini, proses yang sama tetap memakai versi lama setelah
            // menandai perubahan — benar selama setiap permintaan dijalankan
            // proses baru, tetapi SALAH di bawah pekerja yang tetap hidup
            // antar-permintaan (Octane, RoadRunner). Akibatnya produk yang
            // baru disimpan tidak muncul, atau produk yang sudah ditarik masih
            // tampil.
            self::$versiPermintaan = $baru;
        } catch (Throwable) {
            // Gagal menandai berarti halaman mungkin masih menampilkan data
            // lama sampai cache-nya kedaluwarsa sendiri. Itu lebih baik
            // daripada menggagalkan penyimpanan produk yang sedang berjalan.
        }

        if ($sebab !== null) {
            CatatanAktivitas::catat('katalog.cache.ditandai', ['sebab' => $sebab]);
        }
    }

    /**
     * Kunci cache untuk sebuah daftar produk.
     *
     * Versi ikut masuk ke dalam kunci, sehingga perubahan katalog otomatis
     * membuat kunci ini tidak lagi cocok.
     *
     * @param  array<string, mixed>  $saringan
     */
    public static function kunci(string $awalan, array $saringan = []): string
    {
        ksort($saringan);

        return $awalan.':v'.self::versi().':'.md5(json_encode($saringan) ?: '');
    }

    /**
     * Membungkus pengambilan daftar produk dengan cache.
     *
     * @template T
     * @param  callable(): T  $hitung
     * @return T
     */
    public static function ingat(string $awalan, array $saringan, int $detik, callable $hitung)
    {
        try {
            return Cache::remember(self::kunci($awalan, $saringan), $detik, $hitung);
        } catch (Throwable) {
            // Penyimpan cache yang bermasalah tidak boleh menjatuhkan halaman
            // katalog. Kueri dijalankan langsung.
            return $hitung();
        }
    }

    /** Menandai perubahan karena satu produk tertentu. */
    public static function tandaiProduk(?Product $produk, string $sebab): void
    {
        self::tandaiBerubah($sebab.($produk?->sku ? ': '.$produk->sku : ''));
    }
}
