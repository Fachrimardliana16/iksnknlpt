<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menerjemahkan kegagalan teknis menjadi satu kalimat yang layak dibaca
 * pengelola toko, tanpa membocorkan isi pesan exception.
 *
 * Kenapa perlu: beberapa alur menampilkan `$e->getMessage()` langsung ke
 * pengguna. Pesan itu ditulis untuk pengembang — isinya bisa nama tabel,
 * jalur berkas, kelas internal, potongan SQL. Contoh nyata yang pernah
 * keluar di panel: "SQLSTATE[HY000]: General error: 1 no such table: sops".
 * Selain membingungkan, itu memberi penyerang peta dalam aplikasi.
 *
 * Yang dikembalikan di sini sengaja tidak menyebut penyebab. Rinciannya
 * dicatat ke log beserta jejak tumpukan, dan bila perlu dapat dilacak
 * lewat $kode yang ditampilkan bersama pesan.
 */
class Galat
{
    /**
     * @param  array<string, string>  $peta  pecahan pesan => kalimat untuk pengguna
     */
    public static function ramah(Throwable $e, string $umum = 'Terjadi gangguan. Coba lagi beberapa saat lagi.', array $peta = []): string
    {
        $kunci = self::kunci($e->getMessage());

        foreach ($peta as $cari => $pesan) {
            if (str_contains($kunci, self::kunci($cari))) {
                self::catat($e);

                return $pesan;
            }
        }

        self::catat($e);

        return $umum;
    }

    /**
     * Kode pendek untuk melacak kejadian di log. Formatnya sengaja sederhana:
     * tanggal-jam + enam karakter acak, misalnya "20261022-143005-a3f9c1".
     */
    public static function kode(Throwable $e): string
    {
        $hash = substr(hash('sha256', $e->getMessage().$e->getFile().$e->getLine()), 0, 6);

        return now()->format('Ymd-Hi').'-'.$hash;
    }

    /**
     * Pesan umum plus kode pelacakan, untuk ditampilkan di panel admin.
     */
    public static function denganKode(Throwable $e, string $umum = 'Terjadi gangguan. Coba lagi beberapa saat lagi.'): string
    {
        return $umum.' Kode: '.self::kode($e).'.';
    }

    private static function catat(Throwable $e): void
    {
        Log::error('Kegagalan ramah-pengguna', [
            'pesan' => $e->getMessage(),
            'jenis' => $e::class,
            'berkas' => $e->getFile().':'.$e->getLine(),
        ]);
    }

    /**
     * Pesan exception dinormalkan supaya pencocokan tidak rapuh: huruf kecil,
     * spasi ganda hilang, baris baru hilang.
     */
    private static function kunci(string $pesan): string
    {
        $pesan = mb_strtolower($pesan);
        $pesan = preg_replace('/\s+/', ' ', $pesan) ?? $pesan;

        return trim($pesan);
    }
}
