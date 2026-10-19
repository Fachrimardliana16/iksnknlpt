<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan PRD Bagian 11.3.
 *
 * CSP tanpa 'unsafe-inline' (PRD 11.3). Skrip inline diberi nonce lewat
 * csp_nonce(), dan nonce yang sama diserahkan ke Vite supaya Livewire ikut
 * memakainya saat merender tag skripnya sendiri.
 *
 * 'unsafe-eval' tetap diperlukan. Alpine — yang dibundel bersama Livewire —
 * mengubah setiap ekspresi x-data, x-show, x-if, dan @click menjadi fungsi
 * lewat AsyncFunction, dan itu diblokir tanpa 'unsafe-eval'. Bundel Livewire 3
 * tidak menyediakan build Alpine yang aman-CSP: yang tersedia memuat
 * generateEvaluatorFromString yang membangun AsyncFunction. Satu-satunya
 * alternatif adalah mengganti seluruh ekspresi Alpine menjadi komponen
 * Alpine.data() terdaftar; jumlahnya 59 dan itu pekerjaan tersendiri.
 *
 * Yang dibiarkan tertutup: 'unsafe-inline' tidak dipakai, jadi menyisipkan
 * tag <script> baru tetap diblokir. 'unsafe-eval' hanya mengizinkan eval dari
 * skrip yang sudah boleh berjalan, bukan memuat skrip dari luar.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->nonce();

        // Tag skrip Livewire dirender di dalam $next(), dan nonce-nya diambil
        // dari Vite::cspNonce(). Harus disetel sebelum $next() dipanggil,
        // kalau tidak tag itu dirender tanpa nonce saat CSP sudah aktif.
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->csp($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $this->permissionsPolicy($request),
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    /**
     * Izin peramban. Bentuk "camera=(self \"/admin\")" memakai sintaks
     * origin-dengan-jalur yang belum dikenali peramban dan dilaporkan sebagai
     * galat di console. Direktif ini berlaku per-asal, bukan per-jalur.
     *
     * Kamera dibuka hanya di jalur panel yang memang memakainya — pemindai kode
     * 2FA di halaman profil admin. Di sisi depan nilainya harus persis "()",
     * bukan "(())" yang membuat peramban gagal mengurai seluruh direktif.
     */
    private function permissionsPolicy(Request $request): string
    {
        $kamera = str_starts_with($request->path(), 'admin') ? 'self' : '';

        return 'camera=('.$kamera.'), microphone=(), geolocation=(), payment=()';
    }

    private function nonce(): string
    {
        if (! app()->bound('csp.nonce')) {
            app()->instance('csp.nonce', rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
        }

        return app('csp.nonce');
    }

    private function csp(string $nonce): string
    {
        $paymentHosts = 'https://www.paypal.com https://www.sandbox.paypal.com';

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self' {$paymentHosts}",
            "frame-ancestors 'self'",
            "object-src 'none'",
            // 'strict-dynamic' berlaku untuk skrip, tetapi modul yang dimuat
            // <model-viewer> tetap memerlukan asalnya terdaftar — tanpa itu
            // pemutar 3D (Fase 2: P2-01) tidak pernah muncul, tanpa pesan.
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https://www.paypal.com https://www.sandbox.paypal.com https://cdn.jsdelivr.net",
            // Tailwind dan Livewire memasang gaya lewat atribut style, bukan tag.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            // model-viewer mengambil .glb lewat fetch, jadi asalnya harus
            // diizinkan di connect-src, bukan hanya di script-src.
            "connect-src 'self' https://www.paypal.com https://www.sandbox.paypal.com https://cdn.jsdelivr.net",
            'frame-src '.$paymentHosts,
            "media-src 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
