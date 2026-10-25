<?php

namespace App\Http\Middleware;

use App\Support\Internasional;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zona pengiriman pembeli dari cookie.
 *
 * Yang disimpan kode negara tujuan ("ID", "SG"), bukan nama wilayah panjang.
 * Checkout memakainya untuk mengisi kolom negara dan menawarkan mata uang yang
 * sesuai, sehingga pembeli tetap tidak perlu memilih ulang setiap kali datang.
 *
 * Kenapa cookie dan bukan sesi: pembeli yang datang dari tautan KOL atau
 * mesin pencari memesan sekali lalu kembali berhari-hari kemudian. Sesi
 * menghilang saat peramban ditutup, cookie bertahan.
 *
 * Isinya bukan data pribadi — kode negara bukan identitas, dan nilainya dapat
 * dibaca pembeli sendiri di perambannya. Karena itu cookie ini TIDAK
 * HttpOnly: pemeriksaan langsung dari sisi klien memudahkan pemecahan masalah.
 */
class ZonaPelanggan
{
    public const COOKIE = 'iksan_zona';

    /**
     * Persetujuan cookie.
     *
     * Cookie zona bukan cookie esensial: toko tetap berfungsi tanpanya, yang
     * hilang hanya kenyamanan kolom negara yang sudah terisi. Karena itu ia
     * baru boleh ditulis setelah pembeli menyatakan setuju — dan yang menjadi
     * penanda persetujuannya adalah cookie ini sendiri, yang TIDAK dienkripsi
     * agar dapat diperiksa dari sisi klien saat penyebab masalah dilacak.
     */
    public const IZIN = 'iksan_izin';

    public function handle(Request $request, Closure $next): Response
    {
        $zona = strtoupper(trim((string) $request->cookie(self::COOKIE, '')));

        // Nilai yang tidak dikenal dibuang, bukan dipercaya. Cookie dapat
        // diedit pembeli, dan mengizinkan sembarang teks masuk ke alur
        // checkout berarti kolom negara berisi kode yang tak ada di daftar.
        if (! isset(Internasional::NEGARA[$zona])) {
            $zona = '';
        }

        // Zona dipakai di dalam aplikasi, bukan dikirim balik ke keranjang.
        //
        // Dititipkan pada request, bukan pada container. Instance container
        // hidup melewati batas permintaan di test, sehingga nilai dari satu
        // percobaan bocor ke percobaan berikutnya — dan "zona tidak ada"
        // salah terbaca sebagai "zona masih dari percobaan lalu".
        $request->attributes->set('zona.pelanggan', $zona);

        $response = $next($request);

        $sudahAda = collect($response->headers->getCookies())
            ->contains(fn ($cookie) => $cookie->getName() === self::COOKIE);

        // Tanpa persetujuan, cookie zona tidak ditulis di sini. Yang tertulis
        // hanya cookie esensial (sesi, CSRF, bahasa) — zona menunggu sampai
        // pembeli menekan "Setuju" pada pemberitahuan cookie, atau sampai ia
        // memilih negara sendiri saat checkout.
        //
        // Bukan berarti zona berhenti bekerja: nilainya tetap terhitung di
        // dalam permintaan ini lewat $request->attributes, dan yang hilang
        // hanyalah pengingat untuk kunjungan berikutnya.
        if (! $sudahAda && ! self::adaIzin($request)) {
            return $response;
        }

        if (! $sudahAda && $request->cookie(self::COOKIE) !== $zona) {
            $response->headers->setCookie(cookie(
                name: self::COOKIE,
                value: $zona,
                minutes: 60 * 24 * 365,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: false,
                sameSite: 'lax',
            ));
        }

        return $response;
    }

    /**
     * Sudahkah pembeli menyatakan setuju pada cookie non-esensial.
     *
     * Cookie ini ditulis dari sisi klien oleh tombol pada pemberitahuan
     * cookie (lihat partials/front/cookie-notice.blade.php). Karena itu
     * nilainya tidak melewati penyaringan server — pemeriksaannya karena itu
     * hanya "ada atau tidak", bukan isinya.
     */
    public static function adaIzin(?Request $request = null): bool
    {
        $request ??= request();

        return filled($request->cookie(self::IZIN));
    }

    /**
     * Zona pembeli saat ini, atau '' bila belum pernah dipilih.
     *
     * Middleware Livewire dimatikan saat test (`Livewire::test()` memakai
     * `withoutMiddleware()`), sehingga attribute zona tidak pernah diisi dan
     * komponen checkout tidak tahu pembeli dari mana. Karena itu nilai cookie
     * dibaca langsung sebagai cadangan: jalur produksi tetap memakai attribute
     * yang sudah dibersihkan middleware, jalur test memakai cookie apa adanya.
     *
     * Nilai yang tak dikenal di sini dibuang lagi — cookie yang dibaca langsung
     * belum tentu sudah melewati penyaringan.
     */
    public static function sekarang(): string
    {
        $zona = request()->attributes->get('zona.pelanggan');

        if (! is_string($zona) || $zona === '') {
            $zona = strtoupper(trim((string) request()->cookie(self::COOKIE, '')));
        }

        if (! isset(Internasional::NEGARA[$zona])) {
            return '';
        }

        return $zona;
    }
}
