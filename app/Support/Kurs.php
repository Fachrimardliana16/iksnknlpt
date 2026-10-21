<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Kurs mata uang, dan konversi harga ke mata uang asing.
 *
 * Kebutuhan: pembeli kadang menanyakan harga dalam dolar. Menuliskannya sebagai
 * angka tetap di kode akan salah begitu kurs bergerak, jadi kurs diambil dari
 * sumber publik dan disimpan sementara.
 *
 * Aturan yang dipegang:
 *
 *  - GAGAL DENGAN TENANG. Bila sumber kurs tidak dapat dihubungi, halaman tetap
 *    tampil tanpa harga asing. Harga rupiah TIDAK PERNAH bergantung pada ini —
 *    itu harga yang benar-benar ditagih.
 *  - Kurs disimpan 6 jam. Lebih pendek berarti memanggil API terus; lebih
 *    panjang berarti angka yang ditampilkan mulai menyimpang.
 *  - Angka dolar selalu diberi label "perkiraan". Menampilkan kurs hasil konversi
 *    seolah harga pasti membuat pembeli merasa ditipu saat pembayaran.
 */
class Kurs
{
    private const KUNCI = 'kurs:usd-idr';

    /** Nilai cadangan bila sumber tidak dapat dihubungi sama sekali. */
    private const CADANGAN = 16000.0;

    /** Mata uang yang ditampilkan sebagai perkiraan di samping rupiah. */
    public const TAMPIL = ['USD'];

    /**
     * Berapa rupiah untuk 1 USD.
     *
     * @return array{kurs: float, sumber: string, waktu: string}|null
     */
    public static function usd(): ?array
    {
        $tersimpan = Cache::get(self::KUNCI);

        if (is_array($tersimpan) && isset($tersimpan['kurs'])) {
            return $tersimpan;
        }

        // Sumber tanpa kunci API. Bila gagal, tidak ada galat yang dilempar:
        // halaman harus tetap tampil.
        try {
            // Host .app mengalihkan (301) ke .dev; jalur /latest pindah ke /v1.
            // Memakai alamat lama berarti setiap permintaan kena pengalihan,
            // dan Laravel Http tidak mengikutinya — hasilnya selalu kosong.
            $jawab = Http::timeout(4)->retry(1, 200)->get('https://api.frankfurter.dev/v1/latest', [
                'base' => 'USD',
                'symbols' => 'IDR',
            ]);

            if ($jawab->ok()) {
                $kurs = (float) ($jawab->json('rates.IDR') ?? 0);

                if ($kurs > 0) {
                    return self::simpan($kurs, 'frankfurter.dev');
                }
            }
        } catch (\Throwable) {
            // Diabaikan dengan sengaja.
        }

        // Sumber kedua: open.er-api.com (tanpa kunci).
        try {
            $jawab = Http::timeout(4)->get('https://open.er-api.com/v6/latest/USD');

            if ($jawab->ok()) {
                $kurs = (float) ($jawab->json('rates.IDR') ?? 0);

                if ($kurs > 0) {
                    return self::simpan($kurs, 'open.er-api.com');
                }
            }
        } catch (\Throwable) {
            // Diabaikan dengan sengaja.
        }

        return null;
    }

    private static function simpan(float $kurs, string $sumber): array
    {
        $data = [
            'kurs' => $kurs,
            'sumber' => $sumber,
            'waktu' => now()->toAtomString(),
        ];

        Cache::put(self::KUNCI, $data, now()->addHours(6));

        return $data;
    }

    /**
     * Perkiraan harga asing untuk satu nominal rupiah.
     *
     * @return array{mata: string, teks: string, kurs: float, sumber: string}|null
     */
    public static function perkiraan(float|int|string|null $rupiah): ?array
    {
        $nilai = (float) $rupiah;

        if ($nilai <= 0) {
            return null;
        }

        $usd = self::usd();

        if ($usd === null) {
            return null;
        }

        return [
            'mata' => 'USD',
            'teks' => '$'.number_format($nilai / $usd['kurs'], 2, '.', ','),
            'kurs' => $usd['kurs'],
            'sumber' => $usd['sumber'],
        ];
    }
}
