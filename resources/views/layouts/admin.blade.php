<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $judul ?? 'Panel' }} · {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>

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
<body class="min-h-screen bg-bg text-ink">

@php
    // Sidebar admin. Desktop: kolom tetap yang dapat diringkas. Tablet dan
    // mobile: drawer, dan bar navigasi bawah mengambil alih menu utama.
    $menu = [
        ['rute' => 'admin.dashboard', 'pola' => 'admin.dashboard', 'ikon' => 'activity', 'label' => 'Dashboard', 'bawah' => true],
        ['rute' => 'admin.products.index', 'pola' => 'admin.products.*', 'ikon' => 'box', 'label' => 'Produk', 'bawah' => true],
        ['rute' => 'admin.categories.index', 'pola' => 'admin.categories.*', 'ikon' => 'tag', 'label' => 'Kategori', 'bawah' => false],
        ['rute' => 'admin.orders.index', 'pola' => 'admin.orders.*', 'ikon' => 'receipt', 'label' => 'Pesanan', 'bawah' => true],
        ['rute' => 'admin.hero.index', 'pola' => 'admin.hero.*', 'ikon' => 'image', 'label' => 'Hero', 'bawah' => false],
        ['rute' => 'admin.customers.index', 'pola' => 'admin.customers.*', 'ikon' => 'users', 'label' => 'Pelanggan', 'bawah' => false],
        ['rute' => 'admin.reports.index', 'pola' => 'admin.reports.*', 'ikon' => 'chart', 'label' => 'Laporan', 'bawah' => false],
        ['rute' => 'admin.settings.index', 'pola' => 'admin.settings.*', 'ikon' => 'sliders', 'label' => 'Pengaturan', 'bawah' => true],
        ['rute' => 'admin.security.index', 'pola' => 'admin.security.*', 'ikon' => 'shield', 'label' => 'Keamanan', 'bawah' => false],
    ];

    $bawah = array_values(array_filter($menu, fn ($m) => $m['bawah']));
    $aktif = fn (string $pola) => request()->routeIs($pola);
@endphp

<div x-data="{ ringkas: localStorage.getItem('iksan.admin.ringkas') === '1', drawer: false, akun: false }"
     @class(['min-h-screen lg:pl-[260px]', 'lg:!pl-[72px]' => false])
     :class="ringkas ? 'lg:pl-[72px]' : 'lg:pl-[260px]'">

    {{-- ── Sidebar desktop ────────────────────────────────────────── --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden border-r border-line-soft bg-surface lg:flex lg:flex-col"
           :class="ringkas ? 'w-[72px]' : 'w-[260px]'">

        <div class="flex h-16 flex-none items-center gap-2 border-b border-line-soft px-3">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2" wire:navigate>
                <span class="torque-stripe flex-none" aria-hidden="true"><i></i><i></i><i></i></span>
                <span x-show="!ringkas" class="truncate font-display text-lg font-semibold">
                    {{ $toko->teks('site_name') }}
                </span>
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto p-2" aria-label="Navigasi panel">
            @foreach ($menu as $item)
                @continue(! \Illuminate\Support\Facades\Route::has($item['rute']))
                <a href="{{ route($item['rute']) }}" wire:navigate
                   class="flex min-h-[44px] items-center gap-3 px-3 text-sm
                          {{ $aktif($item['pola']) ? 'bg-sunken text-accent' : 'text-muted hover:bg-sunken hover:text-ink' }}"
                   :title="ringkas ? '{{ $item['label'] }}' : null">
                    <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                    <span x-show="!ringkas" class="truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="flex-none border-t border-line-soft p-2">
            <button type="button"
                    @click="ringkas = !ringkas; localStorage.setItem('iksan.admin.ringkas', ringkas ? '1' : '0')"
                    class="flex min-h-[44px] w-full items-center gap-3 px-3 text-sm text-muted hover:text-ink">
                <x-ikon name="chevron-left" :size="20" class="flex-none transition-transform"
                        ::class="ringkas ? 'rotate-180' : ''" />
                <span x-show="!ringkas">Ringkas</span>
                <span class="visually-hidden">Ringkas sidebar</span>
            </button>
        </div>
    </aside>

    {{-- ── Drawer tablet dan mobile ───────────────────────────────── --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="drawer = false" x-transition.opacity></div>

        <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-xs flex-col bg-surface"
             x-transition:enter="transition-transform duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:leave="transition-transform duration-150"
             x-transition:leave-end="-translate-x-full">

            <div class="flex h-16 flex-none items-center justify-between border-b border-line-soft px-3">
                <div class="flex items-center gap-2">
                    <span class="torque-stripe" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="font-display text-lg font-semibold">{{ $toko->teks('site_name') }}</span>
                </div>
                <button type="button" class="btn-icon" @click="drawer = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto p-2">
                @foreach ($menu as $item)
                    @continue(! \Illuminate\Support\Facades\Route::has($item['rute']))
                    <a href="{{ route($item['rute']) }}" wire:navigate @click="drawer = false"
                       class="flex min-h-[48px] items-center gap-3 px-3 text-base
                              {{ $aktif($item['pola']) ? 'bg-sunken text-accent' : 'text-muted' }}">
                        <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex-none border-t border-line-soft p-3">
                <a href="{{ route('front.home') }}" class="btn btn-outline w-full" target="_blank" rel="noopener">
                    Lihat situs
                </a>
            </div>
        </div>
    </div>

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 border-b border-line-soft bg-surface/95 backdrop-blur">
        <div class="flex h-16 items-center gap-2 px-3 lg:px-6">

            <button type="button" class="btn-icon lg:hidden" @click="drawer = true">
                <x-ikon name="menu" />
                <span class="visually-hidden">{{ __('site.nav.open_menu') }}</span>
            </button>

            <div class="min-w-0 flex-1">
                <p class="part-no truncate">{{ $bagian ?? 'Panel' }}</p>
                <p class="truncate font-display text-lg font-semibold">{{ $judul ?? 'Dashboard' }}</p>
            </div>

            <a href="{{ route('front.home') }}" target="_blank" rel="noopener" class="btn-icon hidden lg:inline-flex"
               title="Lihat situs">
                <x-ikon name="eye" />
                <span class="visually-hidden">Lihat situs</span>
            </a>

            <button type="button" class="btn-icon" onclick="window.iksanTheme.toggle()"
                    title="{{ __('site.nav.theme') }}">
                <span class="hidden [html[data-theme='dark']_&]:block"><x-ikon name="sun" /></span>
                <span class="block [html[data-theme='dark']_&]:hidden"><x-ikon name="moon" /></span>
                <span class="visually-hidden">{{ __('site.nav.theme') }}</span>
            </button>

            <div class="relative" @click.outside="akun = false">
                <button type="button" class="btn-icon" @click="akun = !akun" :aria-expanded="akun">
                    <x-ikon name="users" />
                    <span class="visually-hidden">Akun</span>
                </button>

                <div x-show="akun" x-cloak x-transition.opacity
                     class="plate-up absolute right-0 top-full mt-1 w-56 py-1">
                    <div class="border-b border-line-soft px-3 py-2">
                        <p class="truncate text-sm font-medium">{{ auth()->user()?->name }}</p>
                        <p class="truncate text-xs text-muted">{{ auth()->user()?->role?->label() }}</p>
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('admin.profile'))
                        <a href="{{ route('admin.profile') }}" class="block px-3 py-2 text-sm hover:bg-sunken">
                            Profil dan keamanan
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-danger hover:bg-sunken">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ── Isi ────────────────────────────────────────────────────── --}}
    <main class="px-3 py-4 pb-24 lg:px-6 lg:py-6 lg:pb-8">
        {{ $slot }}
    </main>

    {{-- ── Bar navigasi bawah: tablet dan mobile ─────────────────── --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line-soft bg-surface lg:hidden"
         style="padding-bottom: env(safe-area-inset-bottom, 0px)"
         aria-label="Navigasi panel">
        <div class="grid grid-cols-4">
            @foreach ($bawah as $item)
                @continue(! \Illuminate\Support\Facades\Route::has($item['rute']))
                <a href="{{ route($item['rute']) }}" wire:navigate
                   class="relative flex min-h-[60px] flex-col items-center justify-center gap-1
                          {{ $aktif($item['pola']) ? 'text-accent' : 'text-muted' }}">
                    <x-ikon :name="$item['ikon']" :size="22" />
                    <span class="text-[0.6875rem] font-medium leading-none">{{ $item['label'] }}</span>

                    @if ($aktif($item['pola']))
                        <span class="absolute inset-x-6 top-0 h-0.5 bg-accent" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</div>

</body>
</html>
