<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/admin.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Di belakang terowongan, permintaan tiba sebagai HTTP polos dari proxy.
         * Tanpa kepercayaan proxy, seluruh URL aset dirender sebagai http dan
         * diblokir peramban sebagai mixed content.
         *
         * JANGAN pakai '*' di produksi: itu membuat Laravel mempercayai header
         * X-Forwarded-For dari siapa pun, dan pemanggil dapat memalsukan alamat
         * IP untuk melewati pembatas laju.
         */
        $proxies = env('IKK_TRUSTED_PROXIES');

        if (is_string($proxies) && $proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'security.headers' => SecurityHeaders::class,
        ]);

        // Nama kue sesi yang jelas, bukan "laravel_session" bawaan.
        $middleware->encryptCookies(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Permintaan Livewire dan XHR menerima JSON, bukan halaman HTML.
         * Tanpa ini, pembatas laju yang terpicu akan mengembalikan HTML
         * di tengah pembaruan komponen.
         */
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->is('api/*')
                || $request->is('livewire/*')
                || $request->expectsJson();
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status !== 429 || $request->expectsJson() || $request->is('livewire/*')) {
                return null;
            }

            $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

            return response()
                ->view('errors.429', ['retryAfter' => $retryAfter], 429)
                ->header('Retry-After', (string) $retryAfter);
        });
    })->create();
