<?php

namespace App\Support;

/**
 * Sakelar notifikasi yang dapat dinyalakan/dimatikan admin dari panel.
 *
 * Tujuh sakelar butir lonceng (NotifikasiAdmin) ditambah lima sakelar
 * pengiriman. Nilai dibaca dari tabel `settings` — admin yang mengubahnya di
 * panel harus langsung berlaku tanpa menyentuh berkas .env — dan jatuh ke
 * config('iksan.notifikasi.*') bila barisnya belum ada.
 *
 * Config tetap jadi bawaan, bukan sumber kebenaran: bawaan itu berguna saat
 * basis data belum di-seed (test, instalasi baru), sedangkan nilai yang
 * dipakai sehari-hari berasal dari panel.
 */
class SakelarNotifikasi
{
    /**
     * Butir lonceng. Kunci harus sama dengan 'kunci' pada tiap butir di
     * NotifikasiAdmin — di situlah saringan diterapkan, bukan di tampilan.
     *
     * @var array<string, string> kunci settings => kunci butir lonceng
     */
    public const BUTIR = [
        'notif_item_stok_habis' => 'stok-habis',
        'notif_item_stok_menipis' => 'stok-menipis',
        'notif_item_pesanan_siap_kirim' => 'pesanan-perlu-dikirim',
        'notif_item_hampir_batal' => 'hampir-batal',
        'notif_item_masuk_keranjang' => 'masuk-keranjang',
    ];

    /**
     * Sakelar pengiriman ke admin. Nilai jatuh ke config lama supaya
     * deployment yang sudah memakai env tidak berubah perilakunya.
     *
     * @var array<string, string> kunci settings => kunci config
     */
    public const KIRIM = [
        'notif_kirim_dibuat' => 'kirim_saat_dibuat',
        'notif_kirim_dibayar' => 'kirim_saat_dibayar',
        'notif_kirim_dikirim' => 'kirim_saat_dikirim',
        'notif_push_perangkat' => 'push_perangkat',
        'notif_push_surel_cadangan' => 'push_surel_cadangan',
    ];

    /** Semua sakelar, dipakai untuk label dan aturan validasi. */
    public const SEMUA = self::BUTIR + self::KIRIM;

    /** Apakah butir lonceng ini boleh tampil. */
    public static function butirAktif(string $kunciButir): bool
    {
        foreach (self::BUTIR as $kunci => $butir) {
            if ($butir === $kunciButir) {
                return self::baca($kunci, true);
            }
        }

        // Kunci yang tidak dikenal dibiarkan lewat: menyembunyikan butir baru
        // karena lupa mendaftarkannya di sini adalah kegagalan yang senyap.
        return true;
    }

    /** Apakah notifikasi peristiwa ini boleh dikirim ke pembeli. */
    public static function kirimAktif(string $kunciConfig): bool
    {
        foreach (self::KIRIM as $kunci => $config) {
            if ($config === $kunciConfig) {
                return self::baca($kunci, (bool) config('iksan.notifikasi.'.$kunciConfig, true));
            }
        }

        return (bool) config('iksan.notifikasi.'.$kunciConfig, true);
    }

    /** Label manusiawi untuk satu sakelar. */
    public static function label(string $kunci): string
    {
        return match ($kunci) {
            'notif_item_stok_habis' => 'Stok habis',
            'notif_item_stok_menipis' => 'Stok menipis',
            'notif_item_pesanan_siap_kirim' => 'Pesanan siap dikirim',
            'notif_item_hampir_batal' => 'Pesanan hampir batal otomatis',
            'notif_item_masuk_keranjang' => 'Produk masuk keranjang',
            'notif_kirim_dibuat' => 'Surel/WA saat pesanan dibuat',
            'notif_kirim_dibayar' => 'Surel/WA saat pesanan dibayar',
            'notif_kirim_dikirim' => 'Surel/WA saat pesanan dikirim',
            'notif_push_perangkat' => 'Push ke perangkat admin',
            'notif_push_surel_cadangan' => 'Surel cadangan bila push gagal',
            default => $kunci,
        };
    }

    /** Keterangan singkat di bawah tiap sakelar. */
    public static function bantuan(string $kunci): ?string
    {
        return match ($kunci) {
            'notif_item_stok_habis' => 'Varian yang benar-benar tidak dapat dipesan lagi. Paling mendesak.',
            'notif_item_stok_menipis' => 'Varian yang sisanya di bawah ambang stok menipis.',
            'notif_item_pesanan_siap_kirim' => 'Pesanan lunas yang belum dikirim.',
            'notif_item_hampir_batal' => 'Pesanan menunggu bayar yang mendekati batas 24 jam.',
            'notif_item_masuk_keranjang' => 'Keranjang yang masih aktif dalam 24 jam terakhir.',
            'notif_kirim_dibuat' => 'Dikirim ke pembeli saat pesanan baru masuk.',
            'notif_kirim_dibayar' => 'Dikirim ke pembeli setelah pembayaran diverifikasi.',
            'notif_kirim_dikirim' => 'Dikirim ke pembeli saat pesanan berubah menjadi dikirim.',
            'notif_push_perangkat' => 'Notifikasi panel muncul di peramban walau panel tidak terbuka. Perlu izin peramban, satu kali per perangkat.',
            'notif_push_surel_cadangan' => 'Bila push tidak sampai — belum ada perangkat berlangganan atau layanan push bermasalah — kirim surel ke admin sebagai gantinya.',
            default => null,
        };
    }

    private static function baca(string $kunci, bool $bawaan): bool
    {
        // Baris yang belum ada berarti admin belum pernah menyentuh sakelar
        // ini, jadi nilai bawaan (config/env) yang dipakai.
        $nilai = Pengaturan::instance()->ambil($kunci);

        // Nilai yang bukan salah satu ejaan boolean yang dikenal dianggap
        // TIDAK ADA, lalu jatuh ke bawaan. filter_var saja akan mengubah
        // "ya"/"tidak" menjadi true/false yang berbeda arti, dan string
        // rusak menjadi false — yang mematikan notifikasi keamanan tanpa suara.
        if (! in_array($nilai, ['0', '1', 'true', 'false', 'on', 'off'], true)) {
            return $bawaan;
        }

        return $nilai === '1' || $nilai === 'true' || $nilai === 'on';
    }
}
