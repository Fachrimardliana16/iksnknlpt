<?php

namespace App\Http\Middleware;

use App\Enums\TipeKejadianKeamanan;
use App\Support\Keamanan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat percobaan akses yang ditolak (checklist F2-06).
 *
 * Dipasang di seluruh rute, bukan hanya di panel admin. Sebelumnya pencatatan
 * hanya terjadi di dalam gerbang panel, sehingga permintaan yang ditolak di
 * tempat lain — berkas bukti pembayaran yang sudah kedaluwarsa tanda
 * tangannya, rute yang tidak ada berkali-kali, unggahan yang terlalu besar —
 * tidak meninggalkan jejak sama sekali.
 *
 * Yang dicatat HANYA tanggapan yang benar-benar menandakan percobaan akses:
 * 403 dan 429. Kesalahan 404 tidak dicatat karena peramban dan pemindai
 * menghasilkan ribuan 404 yang wajar setiap hari; mencatatnya akan menenggelamkan
 * kejadian yang penting dan membuat tabel jejak tidak berguna.
 */
class CatatKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);
        $kode = $respons->getStatusCode();

        if ($kode === 429) {
            Keamanan::catat(
                TipeKejadianKeamanan::BatasLaju,
                $request,
                $request->user()?->email,
                $request->user()?->id,
                'Batas laju terlampaui pada '.$request->path(),
            );

            return $respons;
        }

        // 403 dari gerbang panel sudah dicatat di sana dengan keterangan yang
        // lebih tepat. Mencatatnya lagi di sini akan menggandakan baris yang
        // sama dan mengaburkan jumlah kejadian sebenarnya.
        if ($kode === 403 && ! self::sudahDicatat($respons)) {
            Keamanan::catat(
                TipeKejadianKeamanan::AksesTidakSah,
                $request,
                $request->user()?->email,
                $request->user()?->id,
                'Akses ditolak pada '.$request->path(),
            );
        }

        return $respons;
    }

    /**
     * Menandai tanggapan yang pencatatannya sudah ditangani di tempat lain.
     *
     * Dipakai lewat header, karena middleware ini berada di lapisan HTTP dan
     * tidak memiliki cara lain untuk mengetahui apa yang terjadi di dalam
     * pengendali.
     */
    private static function sudahDicatat(Response $respons): bool
    {
        return $respons->headers->has('X-Keamanan-Dicatat');
    }

    /** Menandai sebuah tanggapan supaya tidak dicatat dua kali. */
    public static function tandaiSudahDicatat(Response $respons): Response
    {
        $respons->headers->set('X-Keamanan-Dicatat', '1');

        return $respons;
    }
}
