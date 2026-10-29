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

    @isset($kanonikal)
        <link rel="canonical" href="{{ $kanonikal }}">
        <meta property="og:url" content="{{ $kanonikal }}">
    @endisset

    {{--
        GEO (Generative Engine Optimization).

        Mesin jawaban — ChatGPT, Perplexity, Gemini, AI Overviews — mengutip
        dari halaman yang jawabannya eksplisit. Tiga penanda di bawah memberi
        mereka izin dan bentuk kutipan yang jelas:

          ai-summary      ringkasan satu paragraf yang boleh dikutip apa adanya;
          ai-content-declaration  menyatakan isi ini untuk dikutip, bukan
                          diabaikan;
          max-snippet:-1  mencabut batas panjang cuplikan (bawaan mesin
                          pencari bisa memotong di tengah kalimat).

        Tanpa ringkasan eksplisit, mesin jawaban menyusun jawabannya sendiri
        dari menu navigasi, dan hasilnya sering salah sebut harga atau layanan.
    --}}
    @isset($ringkasanAi)
        @if (filled($ringkasanAi))
            <meta name="ai-summary" content="{{ $ringkasanAi }}">
            <meta name="ai-content-declaration" content="original">
        @endif
    @endisset

    <meta name="robots" content="{{ isset($tanpaIndeks) ? 'noindex, nofollow' : 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' }}">

    <?php /* Bahasa alternatif: halaman yang sama tersedia dalam dua bahasa
             lewat cookie, bukan lewat URL terpisah. Untuk mesin pencari, satu
             URL dengan dua bahasa dinyatakan sebagai dua hreflang pada URL
             yang sama — itu cara yang sah menyatakan "isi ini juga tersedia
             dalam bahasa lain" tanpa membuat isi ganda. */ ?>
    <link rel="alternate" hreflang="id" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="en" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

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
    {{-- Slide hero pertama dimuat lebih dulu: peramban mulai mengunduhnya saat
         masih mengurai <head>, bukan setelah CSS dan skrip selesai. Tanpa ini,
         gambar terbesar di beranda baru diminta paling akhir. --}}
    @isset($heroPertama)
        @if (filled($heroPertama))
            <link rel="preload" as="image" href="{{ $heroPertama }}" fetchpriority="high">
        @endif
    @endisset

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

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/bar-aksi.js'])

    {{-- Livewire WAJIB dimuat eksplisit di sini.
         ...
         Tanpa kedua direktif ini, markup komponen dirender (wire:id,
         wire:snapshot ada di HTML) tetapi livewire.js tidak pernah dimuat,
         sehingga SEMUA wire:click mati — tombol + dan − pada pemilih jumlah,
         tambah ke keranjang, filter katalog, semuanya tidak bereaksi.
         Itu bukan kegagalan sebagian: seluruh interaksi Livewire berhenti,
         dan halaman tampak "normal" karena CSS-nya tetap termuat.
    --}}
    @livewireStyles
</head>
<body class="min-h-screen bg-bg text-ink antialiased">

    <a href="#konten-utama"
       class="visually-hidden focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:bg-surface focus:px-4 focus:py-2 focus:text-ink">
        {{ __('site.common.search') }}
    </a>

    @include('partials.front.navbar')

    {{-- Isi halaman ikut BERGESER saat sidebar tampil.

         Sebelumnya sidebar hanya menumpuk di atas konten: isi halaman tetap
         selebar layar dan beberapa bagian masuk ke bawah kolom sidebar. Di
         sini lebarnya yang disesuaikan, bukan sekadar ditumpuk — saat sidebar
         tampil (data-sidebar-tampil ada), isi digeser `md:pl-64`; saat
         sidebar keluar (digulir), kembali `md:pl-0`.

         Transisinya `transition-all duration-300` sesuai permintaan, tetapi
         hanya properti yang benar-benar berubah yang dianimasikan (padding).
         `transition-all` dipakai apa adanya supaya aturan ini mudah dibaca
         dan disetel tanpa menghitung daftar properti.

         Kelas `konten-utama` dipakai app.js untuk menandai keadaan, jadi
         aturannya hanya perlu ditulis sekali di sini. --}}
    <main id="konten-utama"
          class="konten-utama pb-20 transition-all duration-300 ease-out lg:pb-0">
        {{ $slot }}
    </main>

    @include('partials.front.footer')
    @include('partials.front.bottom-nav')
    @include('partials.front.whatsapp')
    @include('partials.front.cookie-notice')

    @isset($remah)
        @if (is_array($remah) && count($remah) > 1)
            @php
                $daftarRemah = [];

                foreach (array_values($remah) as $i => $r) {
                    $daftarRemah[] = [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $r['nama'],
                        'item' => $r['tautan'],
                    ];
                }

                // Store ikut di setiap halaman yang mengirim remah. Mesin
                // jawaban sering mengutip halaman kategori tanpa membuka
                // beranda, jadi identitas penjual harus ada di sini juga.
                $jsonRemah = json_encode([
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        \App\Support\DataTerstruktur::toko(),
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => $daftarRemah,
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $jsonRemah = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $jsonRemah);
            @endphp
            <script type="application/ld+json">
{!! $jsonRemah !!}
            </script>
        @endif
    @endisset

    @isset($faq)
        @if (is_array($faq) && $faq !== [])
            @php
                $grafik = \App\Support\DataTerstruktur::untuk(['faq' => $faq]);
                $jsonFaq = json_encode($grafik, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $jsonFaq = str_replace(['</', '<!--'], ['<\/', '<\!--'], (string) $jsonFaq);
            @endphp
            <script type="application/ld+json">
{!! $jsonFaq !!}
            </script>
        @endif
    @endisset

    @if (filled($kepala ?? null))
        {{-- Sudah berupa HTML yang dirender view lain (misalnya blok JSON-LD),
             jadi dicetak apa adanya. Isinya dibangun dari json_encode sehingga
             tanda kurung sudut di dalam data sudah dilolosikan lebih dulu. --}}
        {!! $kepala !!}
    @endif

    @livewireScripts
</body>
</html>
