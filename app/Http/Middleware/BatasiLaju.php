<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batas laju di tingkat rute (PRD Bagian 11.1).
 *
 * Ditaruh di middleware, bukan di dalam komponen Livewire, karena yang
 * dihitung adalah permintaan HTTP — dan karena jawabannya memang harus 429,
 * bukan pesan galat di dalam halaman.
 *
 * Pembatas di dalam komponen hanya dapat mengubah tampilan: komponen tetap
 * mengembalikan 200, sehingga pengawas lalu lintas, pengawas uptime, dan
 * peramban tidak pernah tahu ada yang sedang dibatasi. Untuk halaman checkout
 * dan lacak pesanan, keduanya publik, 429 adalah jawaban yang benar.
 *
 * Kuncinya memuat alamat (dalam bentuk sidik jari) dan, bila ada, sesi.
 * Memakai keduanya membuat satu alamat di balik NAT tidak menghabiskan jatah
 * seluruh pengguna di jaringan yang sama, sementara membuka sesi baru tetap
 * tidak cukup untuk mengelak: kunci alamatnya tetap sama.
 */
class BatasiLaju
{
    /**
     * @param  string  $kunci  nama batas pada config('iksan.rate_limits')
     * @param  string|null  $hanyaBila  nama parameter kueri; batas hanya berlaku bila parameter itu terisi
     */
    public function handle(Request $request, Closure $next, string $kunci, ?string $hanyaBila = null): Response
    {
        $batas = (array) config('iksan.rate_limits.'.$kunci, []);

        if (count($batas) < 2) {
            return $next($request);
        }

        // Sebagian batas hanya berlaku untuk permintaan tertentu pada rute yang
        // sama. Pencarian misalnya: halaman /produk dibuka berkali-kali oleh
        // pengunjung biasa yang membaca katalog, dan membatasi seluruhnya berarti
        // menghukum pembacaan, bukan pencarian. Yang dibatasi hanya permintaan
        // yang benar-benar membawa kata kunci.
        if ($hanyaBila !== null && ! $request->filled($hanyaBila)) {
            return $next($request);
        }

        [$percobaan, $menit] = [(int) $batas[0], (int) $batas[1]];

        $sidik = 'laju:'.$kunci.':'.hash('sha256', (string) $request->ip());

        if ($percobaan <= 0) {
            return $next($request);
        }

        if (RateLimiter::tooManyAttempts($sidik, $percobaan)) {
            $tunggu = RateLimiter::availableIn($sidik);

            return response()->view('errors.429', [
                'menit' => max(1, (int) ceil($tunggu / 60)),
            ], 429, [
                'Retry-After' => (string) $tunggu,
                'Cache-Control' => 'no-store',
            ]);
        }

        RateLimiter::hit($sidik, $menit * 60);

        return $next($request);
    }
}
