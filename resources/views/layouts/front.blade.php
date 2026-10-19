<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Judul halaman.

        Dibaca dari $title, bukan $judul. Livewire menyerahkan judul lewat
        View::title(), dan makro itu menyimpannya sebagai parameter bernama
        "title" pada layout. Selama nama yang dibaca di sini berbeda, seluruh
        tag <title> situs jatuh ke nama toko saja — judul halaman detail produk
        tidak pernah muncul di tab peramban maupun di hasil pencarian.
    --}}
    <title>{{ isset($title) && filled($title) ? $title : $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>
    <meta name="description" content="{{ $deskripsi ?? $toko->teks('site_description', __('site.brand.tagline')) }}">

    @isset($tanpaIndeks)
        <meta name="robots" content="noindex, nofollow">
    @endisset

    @isset($kanonikal)
        <link rel="canonical" href="{{ $kanonikal }}">
        <meta property="og:url" content="{{ $kanonikal }}">
    @endisset

    @isset($metaOg)
        <meta property="og:type" content="{{ $metaOg['tipe'] ?? 'website' }}">
        <meta property="og:title" content="{{ $metaOg['judul'] }}">
        <meta property="og:description" content="{{ $metaOg['deskripsi'] }}">
        <meta property="og:site_name" content="{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}">
        <meta name="twitter:card" content="summary_large_image">
        @if (filled($metaOg['gambar'] ?? null))
            <meta property="og:image" content="{{ $metaOg['gambar'] }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="{{ $metaOg['alt'] ?? $metaOg['judul'] }}">
        @endif
    @endisset
    {{-- PWA (Fase 2: P2-03). Manifes memasang nama, ikon, dan tombol pasang;
         service worker menyimpan aset statis dan halaman luring. Keduanya
         diletakkan di <head> supaya peramban menemukannya sebelum aset lain
         dimuat — manifes yang ditemukan terlambat tidak dianggap ada. --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#111114">
    <link rel="apple-touch-icon" href="/ikon-pwa-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $toko->teks('site_name', __('site.brand.fallback_name')) }}">

    <script nonce="{{ csp_nonce() }}">
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
                    /* Pendaftaran gagal tidak boleh menghentikan halaman. */
                });
            });
        }
    </script>

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

    {{-- Dua berkas font pertama dimuat lebih awal (checklist F11-04).
         Yang dimuat lebih awal hanya bobot yang benar-benar dipakai untuk teks
         utama di layar pertama; menyiapkan semua berkas justru menghabiskan
         lebar pita dan menunda hal lain. --}}
    <link rel="preload" href="{{ asset('fonts/barlow-400-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/barlow-600-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

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

    @if (filled($kepala ?? null))
        {{-- Sudah berupa HTML yang dirender view lain (misalnya blok JSON-LD),
             jadi dicetak apa adanya. Isinya dibangun dari json_encode sehingga
             tanda kurung sudut di dalam data sudah dilolosikan lebih dulu. --}}
        {!! $kepala !!}
    @endif

</body>
</html>
