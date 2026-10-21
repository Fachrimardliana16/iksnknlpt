<?php

namespace App\Support;

/**
 * Bantuan pengiriman internasional.
 *
 * Situs ini memesan dalam Rupiah di basis data. Pembeli luar negeri tidak
 * perlu melihat nominal milyaran rupiah untuk memahami harga — mereka perlu
 * perkiraan dalam mata uang yang mereka pakai sehari-hari. Yang dikembalikan
 * PERKIRAAN, bukan harga yang harus dihormati: kurs berubah, dan yang dipakai
 * untuk pembayaran tetap IDR.
 *
 * Mengapa tidak membuat seluruh toko multi-mata-uang: satu baris harga di
 * basis data, satu nilai di pesanan, satu laporan. Menyimpan beberapa harga
 * per mata uang berarti beberapa angka yang bisa berbeda untuk barang yang
 * sama, dan pertanyaan "berapa harga produk X?" berhenti punya satu jawaban.
 */
class Internasional
{
    /**
     * Negara tujuan yang paling sering kirim di luar negeri.
     *
     * Daftar pendek dengan sengaja: 21 negara Asia-Pasifik dan Amerika, bukan
     * 200 negara dunia. Daftar panjang hanya membuat kolom susun pilihan yang
     * panjang tanpa memperjelas apa pun.
     *
     * @var array<string, string>
     */
    public const NEGARA = [
        'ID' => 'Indonesia',
        'SG' => 'Singapura',
        'MY' => 'Malaysia',
        'TH' => 'Thailand',
        'VN' => 'Vietnam',
        'PH' => 'Filipina',
        'BN' => 'Brunei Darussalam',
        'KH' => 'Kamboja',
        'LA' => 'Laos',
        'MM' => 'Myanmar',
        'CN' => 'Tiongkok',
        'HK' => 'Hong Kong',
        'TW' => 'Taiwan',
        'JP' => 'Jepang',
        'KR' => 'Korea Selatan',
        'IN' => 'India',
        'AU' => 'Australia',
        'NZ' => 'Selandia Baru',
        'US' => 'Amerika Serikat',
        'CA' => 'Kanada',
        'NL' => 'Belanda',
    ];

    /**
     * Kode negara yang memakai nomor telepon dengan kode yang biasa dipakai.
     * Dipakai untuk memandu pengisi, bukan untuk menolak nomor: nomor dari
     * negara lain tetap sah.
     *
     * @var array<string, string>
     */
    public const KODE_TELEPON = [
        'ID' => '+62',
        'SG' => '+65',
        'MY' => '+60',
        'TH' => '+66',
        'VN' => '+84',
        'PH' => '+63',
        'CN' => '+86',
        'HK' => '+852',
        'TW' => '+886',
        'JP' => '+81',
        'KR' => '+82',
        'IN' => '+91',
        'AU' => '+61',
        'NZ' => '+64',
        'US' => '+1',
        'CA' => '+1',
        'NL' => '+31',
    ];

    public static function namaNegara(string $kode): string
    {
        return self::NEGARA[$kode] ?? $kode;
    }

    public static function kodeTelepon(string $kode): string
    {
        return self::KODE_TELEPON[$kode] ?? '';
    }

    public static function dalamNegeri(string $kode): bool
    {
        return $kode === 'ID' || $kode === '';
    }

    /**
     * Mata uang pembayaran untuk pesanan.
     *
     * Pesanan dalam negeri selalu IDR. Pesanan luar negeri tetap memakai IDR
     * di basis data, tetapi membawa mata uang yang dipilih pembeli supaya
     * konversi dan tampilan pay-off-nya bisa ditampilkan — satu angka, bukan
     * dua yang saling bersaing.
     */
    public static function mataUang(string $kodeNegara, string $pilihan): string
    {
        $pilihan = strtoupper($pilihan);

        if (in_array($pilihan, ['USD', 'IDR'], true)) {
            return $pilihan;
        }

        return self::dalamNegeri($kodeNegara) ? 'IDR' : 'USD';
    }

    /**
     * Perkiraan nilai rupiah dalam mata uang lain.
     *
     * Mengembalikan null bila kurs tidak tersedia (API kurs sedang gagal).
     * Yang memanggil WAJIB menangani null — menampilkan angka palsu lebih
     * buruk daripada tidak menampilkan apa-apa.
     */
    public static function perkiraan(float $rupiah, string $mataUang): ?array
    {
        if ($mataUang === 'IDR') {
            return [
                'angka' => $rupiah,
                'teks' => 'Rp'.number_format($rupiah, 0, ',', '.'),
                'kurs' => 1.0,
                'sumber' => 'asli',
            ];
        }

        $hasil = Kurs::perkiraan($rupiah);

        return $hasil === null ? null : [
            'mata' => $hasil['mata'],
            'teks' => $hasil['teks'],
            'kurs' => $hasil['kurs'],
            'sumber' => $hasil['sumber'],
        ];
    }
}
