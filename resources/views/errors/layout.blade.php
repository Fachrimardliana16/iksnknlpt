@php
    /** @var \App\Support\Pengaturan $toko */
    $kode = $kode ?? 500;
    $headline = $headline ?? __('site.errors.500_title');
    $pesan = $pesan ?? '';
    $detail = $detail ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $kode }} · {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>
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
<body class="flex min-h-screen flex-col items-center justify-center bg-bg px-4 text-ink">

    <div class="w-full max-w-lg">
        <div class="flex items-center gap-3">
            <span class="torque-stripe" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="part-no">{{ str_pad((string) $kode, 3, '0', STR_PAD_LEFT) }}</span>
        </div>

        <h1 class="mt-4 font-display text-4xl font-semibold sm:text-5xl">{{ $headline }}</h1>

        @if (filled($pesan))
            <p class="mt-3 text-muted">{{ $pesan }}</p>
        @endif

        @if (filled($detail))
            <p class="mt-2 text-sm text-dim">{{ $detail }}</p>
        @endif

        <div class="measure-line my-8"></div>

        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('site.errors.back_home') }}</a>
    </div>

</body>
</html>
