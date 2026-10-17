<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $judul ?? '' }}{{ isset($judul) ? ' · ' : '' }}{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>
    <meta name="description" content="{{ $deskripsi ?? $toko->teks('site_description', __('site.brand.tagline')) }}">
    @if (filled($toko->teks('site_favicon')))
        <link rel="icon" href="{{ \App\Models\ProductMedia::urlMedia($toko->teks('site_favicon')) }}">
    @endif

    {{-- Tema dipasang sebelum first paint supaya tidak ada kedip terang lalu gelap. --}}
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
</head>
<body class="min-h-screen bg-bg text-ink antialiased">

    <a href="#konten-utama"
       class="visually-hidden focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-surface focus:px-4 focus:py-2 focus:text-ink">
        {{ __('common.search') }}
    </a>

    @include('partials.front.navbar')

    <main id="konten-utama" class="pb-20 lg:pb-0">
        {{ $slot }}
    </main>

    @include('partials.front.footer')
    @include('partials.front.bottom-nav')
    @include('partials.front.whatsapp')

</body>
</html>
