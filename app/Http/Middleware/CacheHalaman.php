<?php

namespace App\Http\Middleware;

use App\Support\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache halaman publik (optimasi waktu muat).
 *
 * Yang diukur lebih dulu, baru diputuskan: satu halaman katalog menghabiskan
 * 100–150 ms CPU untuk 7–16 kueri, dan mesin pengembangan ini melayani satu
 * permintaan pada satu waktu. Halaman yang sama dibuka ratusan kali sehari
 * dengan hasil yang sama persis, jadi menyimpannya sekali berarti permintaan
 * berikutnya tidak menyentuh basis data maupun Blade.
 *
 * Batasnya dipilih supaya tidak pernah menyajikan data yang salah:
 *
 *  - hanya GET, hanya tamu (tanpa sesi login), hanya respons 200;
 *  - tidak untuk /admin, /livewire, /keranjang, /checkout, /pesanan, /lacak;
 *  - kunci memuat versi katalog, sehingga satu perubahan dari panel admin
 *    membatalkan SELURUH cache halaman sekaligus — tidak ada daftar halaman
 *    yang harus diingat;
 *  - umur 10 menit sebagai jaring pengaman bila ada yang lupa membatalkan.
 *
 * Dapat dimatikan lewat pengaturan `cache_halaman` tanpa mengubah kode.
 */
class CacheHalaman
{
    /** Awalan rute yang tidak boleh disimpan sama sekali. */
    private const TERLARANG = [
        'admin', 'livewire', 'keranjang', 'checkout', 'pesanan', 'lacak',
        'bukti-bayar', 'storage', 'build',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->boleh($request)) {
            return $next($request);
        }

        $kunci = $this->kunci($request);

        $tersimpan = Cache::get($kunci);

        if (is_array($tersimpan) && isset($tersimpan['isi'])) {
            // Cache lama (sebelum perbaikan) mungkin masih berisi halaman
            // bertoken. Jangan disajikan: buang lalu render ulang.
            if (str_contains((string) $tersimpan['isi'], 'data-csrf')
                || str_contains((string) $tersimpan['isi'], 'wire:snapshot')) {
                Cache::forget($kunci);
            } else {
                $response = response($tersimpan['isi'], 200, $tersimpan['kepala'] ?? []);
                $response->headers->set('X-Cache-Halaman', 'hit');

                return $response;
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $isi = $response->getContent();

        if (! is_string($isi) || $isi === '') {
            return $response;
        }

        // Halaman yang memuat token CSRF ATAU snapshot Livewire TIDAK disimpan.
        //
        // Bukti kegagalannya: dua sesi berbeda menerima token CSRF yang SAMA
        // persis (data-csrf dan meta csrf-token identik), karena HTML-nya
        // diambil dari cache. Token itu terikat pada sesi pemiliknya, jadi
        // setiap pengunjung lain mendapat 419 "CSRF token mismatch" saat
        // menekan tombol apa pun — dan snapshot Livewire ikut membawa state
        // milik orang lain.
        //
        // Ini bukan sekadar bug fungsional: menyajikan token sesi orang lain
        // adalah kebocoran. Halaman seperti itu memang tidak layak di-cache
        // utuh; optimasinya tetap berlaku untuk halaman yang tidak memuat
        // token (halaman statis, dsb).
        if (str_contains($isi, 'data-csrf') || str_contains($isi, 'wire:snapshot')) {
            $response->headers->set('X-Cache-Halaman', 'lewati-token');

            return $response;
        }

        // Nonce CSP disimpan bersama isinya: skrip inline di dalam HTML memakai
        // nonce yang sama, dan header CSP untuk jawaban ini diambil dari cache
        // juga. Menyimpan isi tanpa nonce berarti skripnya diblokir di
        // kunjungan kedua — halaman tampil, tetapi seluruh tombol diam.
        Cache::put($kunci, [
            'isi' => $isi,
            'kepala' => [
                'Content-Type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
                'Content-Security-Policy' => (string) $response->headers->get('Content-Security-Policy'),
                'X-Content-Type-Options' => (string) $response->headers->get('X-Content-Type-Options'),
                'X-Frame-Options' => (string) $response->headers->get('X-Frame-Options'),
                'Referrer-Policy' => (string) $response->headers->get('Referrer-Policy'),
                'Permissions-Policy' => (string) $response->headers->get('Permissions-Policy'),
            ],
        ], now()->addMinutes(10));

        $response->headers->set('X-Cache-Halaman', 'miss');

        return $response;
    }

    private function boleh(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Tamu saja. Pengunjung yang sudah masuk mendapat jawaban berbeda
        // (nama, keranjang), dan menyimpannya berarti membocorkan sesi orang
        // lain ke pengunjung berikutnya. Middleware ini berjalan sesudah sesi
        // dimulai, jadi `user()` di sini sudah membaca sesi yang sebenarnya.
        if ($request->user() !== null) {
            return false;
        }

        if ($request->headers->has('Authorization')) {
            return false;
        }

        $jalur = $request->path();

        foreach (self::TERLARANG as $awalan) {
            if ($jalur === $awalan || str_starts_with($jalur, $awalan.'/')) {
                return false;
            }
        }

        // Dimatikan lewat pengaturan tanpa mengubah kode.
        try {
            $aktif = Pengaturan::instance()?->teks('cache_halaman', '1');
        } catch (\Throwable) {
            $aktif = '1';
        }

        return in_array((string) $aktif, ['1', 'true', 'on', 'ya'], true);
    }

    /**
     * Kunci cache: alamat + kata kunci saringan + versi katalog.
     *
     * Versi katalog dinaikkan setiap ada perubahan dari panel admin, sehingga
     * satu perubahan membatalkan seluruh halaman yang tersimpan.
     */
    private function kunci(Request $request): string
    {
        $versi = (string) (Cache::get(\App\Support\CacheKatalog::KUNCI_VERSI_KATALOG) ?? '1');

        // Bahasa ikut kunci. Isi halaman berbeda per bahasa (judul, tombol,
        // deskripsi produk), dan cookie bahasa TIDAK mengubah URL — tanpa
        // bahasa di kunci, pengunjung berbahasa Inggris menerima halaman
        // Indonesia yang tersimpan lebih dulu. Diuji
        // KatalogFrontTest::test_deskripsi_hanya_menampilkan_bahasa_yang_aktif.
        $bahasa = (string) ($request->cookie(\App\Http\Middleware\SetLocale::COOKIE)
            ?: app()->getLocale());

        return 'halaman:'.sha1($request->fullUrl().'|'.$versi.'|'.$bahasa);
    }
}
