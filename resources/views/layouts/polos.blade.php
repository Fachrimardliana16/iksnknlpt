<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Token CSRF untuk skrip yang memanggil /livewire/update sendiri.

         Livewire 3 memakai cookie XSRF-TOKEN dan tetap bekerja tanpa meta ini,
         tetapi skrip lain — termasuk alat uji yang memanggil jalur Livewire
         seperti peramban — membacanya dari DOM. Tanpa meta itu, token terbaca
         kosong dan setiap permintaan POST dijawab 419. Terbukti: halaman masuk
         panel dipanggil dari alat uji, token terbaca 0 karakter, login 419,
         dan seluruh sesi setelahnya diam-diam jatuh kembali ke halaman masuk. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Masuk' }} · {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>
    <script nonce="{{ csp_nonce() }}">
        (function () {
            try {
                var t = localStorage.getItem('iksan.theme');
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire dimuat eksplisit; lihat catatan di layouts/front.blade.php. --}}
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-bg px-4 py-10 text-ink">
    {{ $slot }}
    @livewireScripts
</body>
</html>
