<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan PRD Bagian 11.3.
 *
 * CSP sengaja TIDAK memakai 'unsafe-inline'. Skrip inline yang dibutuhkan
 * (penetapan tema sebelum first paint) diberi nonce lewat csp_nonce().
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->nonce();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->csp($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self "/admin"), microphone=(), geolocation=(), payment=()',
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
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://www.paypal.com https://www.sandbox.paypal.com",
            // Tailwind dan Livewire memasang gaya lewat atribut style, bukan tag.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self' https://www.paypal.com https://www.sandbox.paypal.com",
            'frame-src '.$paymentHosts,
            "media-src 'self'",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        return implode('; ', $directives);
    }
}
