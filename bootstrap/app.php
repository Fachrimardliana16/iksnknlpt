<?php

use App\Http\Middleware\BlokirIp;
use App\Http\Middleware\CatatKeamanan;
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
    // Pencarian peristiwa otomatis: App\Events\X terhubung ke App\Listeners\Y
    // tanpa didaftar satu per satu. Tanpa ini listener harus ditulis tangan di
    // AppServiceProvider, dan yang terbuang bukan galat melainkan ketiadaan —
    // peristiwa terkirim, tidak ada yang mendengarkan, dan tidak ada cara
    // mengetahuinya dari luar.
    ->withEvents()
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Di belakang terowongan, permintaan tiba sebagai HTTP polos dari proxy.
         * Tanpa kepercayaan proxy, seluruh URL aset dirender sebagai http dan
         * diblokir peramban sebagai mixed content.
         *
         * JANGAN pakai '*' di produksi: itu membuat Laravel mempercayai header
         * X-Forwarded-For dari siapa pun, dan pemanggil dapat memalsukan alamat
         * IP untuk melewati pembatas laju.
         *
         * Di lingkungan lokal, cloudflared berjalan di mesin yang sama, jadi
         * hanya 127.0.0.1 yang perlu dipercaya. Default itu dipasang di sini,
         * bukan sebagai '*' lebar, supaya terowongan pengembangan bekerja tanpa
         * membuka celah yang sama sekali tidak perlu.
         */
        // HANYA env() yang boleh dipakai di sini, BUKAN config().
        //
        // Closure ini berjalan saat HttpKernel diselesaikan — sebelum
        // konfigurasi terpasang di kontainer. Memanggil config() di sini
        // melempar "ReflectionException: Class config does not exist", dan
        // seluruh situs mati dengan 500 (terbukti saat dicoba).
        //
        // Konsekuensinya: setelah `php artisan config:cache`, env() di LUAR
        // berkas config mengembalikan nilai dari cache konfigurasi, bukan dari
        // .env. Karena itu IKK_TRUSTED_PROXIES harus ada di .env SEBELUM
        // config:cache dijalankan, dan cache dibangun ulang setiap kali nilai
        // itu berubah. Bila kosong, default '127.0.0.1' dipakai untuk
        // lingkungan lokal — cloudflared berjalan di mesin yang sama.
        //
        // BUKAN '*': itu membuat Laravel mempercayai X-Forwarded-For dari siapa
        // pun, dan pemanggil dapat memalsukan IP untuk melewati pembatas laju.
        // Proxy dipercaya lewat MIDDLEWARE SENDIRI, bukan trustProxies() di sini.
        //
        // Dua alasan, keduanya sudah dibuktikan di mesin ini:
        //
        // 1. Closure ini berjalan sebelum konfigurasi terpasang di kontainer:
        //    config() melempar "Class config does not exist" (situs 500),
        //    dan env() mengembalikan null karena setelah config:cache Laravel
        //    tidak lagi memuat .env.
        // 2. $middleware->trustProxies() hanya menyetel NILAI pada kelas
        //    TrustProxies; ia tidak memasang middleware itu ke stack.
        //
        // Akibatnya X-Forwarded-Proto diabaikan, URL aset dirender http, dan
        // peramban memblokirnya sebagai mixed content — halaman tanpa CSS.
        $middleware->append(\App\Http\Middleware\PercayaiProxy::class);

        // Tamu yang membuka panel diarahkan ke halaman masuk PANEL, bukan ke
        // rute bernama "login" yang tidak ada di aplikasi ini.
        //
        // PENTING: ini harus di sini, bukan di routes/admin.php.
        // Dengan `php artisan route:cache`, berkas rute TIDAK dieksekusi —
        // hanya definisi rutenya yang dibaca dari cache. Pemanggilan
        // Authenticate::redirectUsing() di dalam routes/admin.php karena itu
        // hilang begitu rute di-cache, dan situs melempar
        // "Route [login] not defined" (500) pada setiap akses /admin oleh tamu.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

                // Cache-Control untuk aset statis (
        // /build/assets, /storage, manifes PWA). Dipasang GLOBAL, bukan
        // di grup web: berkas di public/ dan /storage/{path} tidak selalu
        // melewati grup web, dan di sanalah sebagian besar bita berada.
        $middleware->append(\App\Http\Middleware\CacheAset::class);

        // Cache halaman publik. Ditaruh SESUDAH grup web (jadi sesi sudah
        // dimulai dan $request->user() dapat dibaca), tetapi sebelum
        // pengendali berjalan. Rute yang tidak boleh disimpan ditolak di
        // dalam middlewarenya sendiri.
        $middleware->web(append: [\App\Http\Middleware\CacheHalaman::class]);

$middleware->web(append: [
            SecurityHeaders::class,
            // Pencatatan dipasang di seluruh permintaan web, supaya
            // penolakan di mana pun meninggalkan jejak. Pemeriksaan blokir
            // TIDAK di sini: ia dipasang khusus pada rute publik di
            // routes/web.php.
            //
            // Alasannya: panel admin memuat halaman untuk MEMBUKA blokir. Bila
            // blokir diperiksa di grup web, pengguna panel yang sudah masuk
            // ikut tertutup bersama halaman itu, dan kesalahan sandi berulang
            // dari satu jaringan mengunci admin dari satu-satunya tempat untuk
            // memperbaikinya.
            CatatKeamanan::class,
        ]);

        $middleware->alias([
            'security.headers' => SecurityHeaders::class,
            'admin' => \App\Http\Middleware\PastikanAdmin::class,
            'blokir.ip' => BlokirIp::class,
            'laju' => \App\Http\Middleware\BatasiLaju::class,
        ]);

        // Bahasa dari cookie. Dipasang pada grup web supaya berlaku di front
        // maupun panel admin.
        $middleware->web(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Kue bahasa tidak dienkripsi. Nilainya bukan rahasia, dan membiarkannya
        // terbaca membuat kue ini dapat diperiksa langsung dari sisi klien.
        $middleware->encryptCookies(except: [
            \App\Http\Middleware\SetLocale::COOKIE,
        ]);
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
