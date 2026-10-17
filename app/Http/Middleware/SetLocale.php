<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa antarmuka dari cookie. Pilihan pengguna bertahan satu tahun dan
 * berlaku pada permintaan berikutnya tanpa perlu login.
 */
class SetLocale
{
    public const COOKIE = 'iksan_locale';

    /** @var array<int, string> */
    public const DIDUKUNG = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(self::COOKIE);
        $locale = $request->query('lang', $locale);

        if (! in_array($locale, self::DIDUKUNG, true)) {
            $locale = config('app.locale', 'id');
        }

        app()->setLocale($locale);

        $response = $next($request);

        // Cookie ditulis hanya saat nilainya berbeda, agar tidak menulis ulang
        // pada setiap permintaan.
        if ($request->cookie(self::COOKIE) !== $locale) {
            $response->headers->setCookie(cookie(
                name: self::COOKIE,
                value: $locale,
                minutes: 60 * 24 * 365,
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return $response;
    }

    public static function lain(string $locale): string
    {
        return $locale === 'en' ? 'id' : 'en';
    }
}
