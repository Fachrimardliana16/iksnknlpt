@php
    /** @var \App\Support\Pengaturan $toko */
    $kategoriNav = \App\Models\Category::query()->aktif()->terurut()->take(8)->get();
    $logo = $toko->teks('site_logo_light');
    $logoTerang = $toko->teks('site_logo_dark');
@endphp

{{-- Bar atas. Desktop: satu baris penuh. Tablet dan mobile: bar ringkas,
     menu lengkap pindah ke drawer dan bar navigasi bawah. --}}
<header class="sticky top-0 z-40 border-b border-line-soft bg-surface/95 backdrop-blur"
        x-data="{ menu: false, cari: false, kategori: false }">

    <div class="container-page flex h-14 items-center gap-2 lg:h-[72px] lg:gap-6">

        {{-- Mobile dan tablet: tombol menu --}}
        <button type="button" class="btn-icon lg:hidden" @click="menu = true"
                :aria-expanded="menu" aria-controls="drawer-menu">
            <x-ikon name="menu" />
            <span class="visually-hidden">{{ __('site.nav.open_menu') }}</span>
        </button>

        {{-- Logo --}}
        <a href="{{ route('front.home') }}" class="flex min-w-0 items-center gap-2">
            @if ($logo || $logoTerang)
                <img src="{{ Storage::disk('public')->url($logo ?: $logoTerang) }}"
                     alt="{{ $toko->teks('site_name') }}"
                     width="140" height="40" class="h-8 w-auto lg:h-9">
            @else
                <span class="font-display text-xl font-semibold tracking-tight lg:text-2xl">
                    {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                </span>
            @endif
        </a>

        {{-- Desktop: menu utama --}}
        <nav class="hidden flex-1 items-center gap-1 lg:flex" aria-label="{{ __('site.nav.menu') }}">
            <a href="{{ route('front.home') }}"
               class="btn btn-ghost {{ request()->routeIs('front.home') ? '!text-ink' : '' }}">
                {{ __('site.nav.home') }}
            </a>
            <a href="{{ route('front.products.index') }}"
               class="btn btn-ghost {{ request()->routeIs('front.products.*') ? '!text-ink' : '' }}">
                {{ __('site.nav.products') }}
            </a>

            <div class="relative" @click.outside="kategori = false">
                <button type="button" class="btn btn-ghost" @click="kategori = !kategori"
                        :aria-expanded="kategori">
                    {{ __('site.nav.categories') }}
                    <x-ikon name="chevron-down" :size="16" />
                </button>
                <div x-show="kategori" x-cloak x-transition.opacity
                     class="plate-up absolute left-0 top-full mt-1 w-56 py-1">
                    @foreach ($kategoriNav as $kat)
                        <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                           class="block px-3 py-2 text-sm hover:bg-sunken">
                            {{ $kat->nama() }}
                        </a>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('front.about') }}" class="btn btn-ghost">{{ __('site.nav.about') }}</a>
            <a href="{{ route('front.contact') }}" class="btn btn-ghost">{{ __('site.nav.contact') }}</a>
        </nav>

        <div class="ml-auto flex items-center gap-0.5">

            {{-- Bahasa --}}
            @php $localeLain = \App\Http\Middleware\SetLocale::lain(app()->getLocale()); @endphp
            <a href="{{ route('front.lang', $localeLain) }}"
               class="btn-icon font-mono text-[0.6875rem] font-medium uppercase"
               title="{{ __('site.nav.language') }}">
                <span>{{ app()->getLocale() }}</span>
                <span class="visually-hidden">{{ __('site.nav.language') }}</span>
            </a>

            {{-- Tema --}}
            <button type="button" class="btn-icon" onclick="window.iksanTheme.toggle()"
                    title="{{ __('site.nav.theme') }}">
                <span class="hidden [html[data-theme='dark']_&]:block"><x-ikon name="sun" /></span>
                <span class="block [html[data-theme='dark']_&]:hidden"><x-ikon name="moon" /></span>
                <span class="visually-hidden">{{ __('site.nav.theme') }}</span>
            </button>

            {{-- Pencarian --}}
            <button type="button" class="btn-icon" @click="cari = true"
                    title="{{ __('site.nav.search') }}">
                <x-ikon name="search" />
                <span class="visually-hidden">{{ __('site.nav.open_search') }}</span>
            </button>

            {{-- Keranjang --}}
            <a href="{{ route('front.cart') }}" class="btn-icon" title="{{ __('site.nav.cart') }}"
               wire:navigate>
                <x-ikon name="cart" />
                <livewire:front.jumlah-keranjang wire:key="badge-atas" />
                <span class="visually-hidden">{{ __('site.nav.open_cart') }}</span>
            </a>
        </div>
    </div>

    {{-- Drawer menu: tablet dan mobile --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="menu = false" x-transition.opacity></div>

        <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-sm flex-col bg-surface"
             x-transition:enter="transition-transform duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:leave="transition-transform duration-150"
             x-transition:leave-end="-translate-x-full">

            <div class="flex h-14 flex-none items-center justify-between border-b border-line-soft px-3">
                <span class="part-no">01 / {{ __('site.nav.menu') }}</span>
                <button type="button" class="btn-icon" @click="menu = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto p-3" aria-label="{{ __('site.nav.menu') }}">
                <a href="{{ route('front.home') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="home" /> {{ __('site.nav.home') }}
                </a>
                <a href="{{ route('front.products.index') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="grid" /> {{ __('site.nav.products') }}
                </a>

                <div class="measure-line my-3"></div>
                <p class="part-no mb-1 px-2">{{ __('site.nav.categories') }}</p>

                @foreach ($kategoriNav as $kat)
                    <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                       class="flex min-h-[44px] items-center gap-2 px-2 text-sm text-muted">
                        <x-ikon name="tag" :size="16" /> {{ $kat->nama() }}
                    </a>
                @endforeach

                <div class="measure-line my-3"></div>

                <a href="{{ route('front.about') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="info" /> {{ __('site.nav.about') }}
                </a>
                <a href="{{ route('front.contact') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="phone" /> {{ __('site.nav.contact') }}
                </a>
                <a href="{{ route('front.order.track') }}" class="flex min-h-[44px] items-center gap-3 px-2 text-base">
                    <x-ikon name="box" /> {{ __('site.order.track') }}
                </a>
            </nav>
        </div>
    </div>

    {{-- Overlay pencarian --}}
    <div x-show="cari" x-cloak class="fixed inset-0 z-50 bg-bg" role="dialog" aria-modal="true"
         @keydown.escape.window="cari = false">
        <div class="container-page py-4">
            <div class="flex items-center gap-2">
                <span class="part-no">02 / {{ __('site.nav.search') }}</span>
                <button type="button" class="btn-icon ml-auto" @click="cari = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <livewire:front.pencarian />
        </div>
    </div>
</header>
