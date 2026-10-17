@php
    $jumlahKeranjang = app(\App\Services\Keranjang::class)->jumlahItem();
    $aktif = fn (string $pola) => request()->routeIs($pola);

    $slotNav = [
        ['nama' => 'home', 'pola' => 'front.home', 'rute' => route('front.home'), 'label' => __('site.nav.home')],
        ['nama' => 'grid', 'pola' => 'front.products.*', 'rute' => route('front.products.index'), 'label' => __('site.nav.products')],
        ['nama' => 'cart', 'pola' => 'front.cart', 'rute' => route('front.cart'), 'label' => __('site.nav.cart')],
        ['nama' => 'phone', 'pola' => 'front.contact', 'rute' => route('front.contact'), 'label' => __('site.nav.contact')],
    ];
@endphp

{{-- Bar navigasi bawah. Hanya tablet dan mobile. Situs harus terasa seperti
     aplikasi, bukan situs yang diciutkan. Disembunyikan saat mengetik input
     supaya tidak menutupi papan tuts. --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line-soft bg-surface lg:hidden
            [html[data-typing]_&]:hidden"
     style="padding-bottom: env(safe-area-inset-bottom, 0px)"
     aria-label="{{ __('site.nav.menu') }}">

    <div class="grid grid-cols-4">
        @foreach ($slotNav as $slot)
            <a href="{{ $slot['rute'] }}" wire:navigate
               class="relative flex min-h-[60px] flex-col items-center justify-center gap-1
                      {{ $aktif($slot['pola']) ? 'text-accent' : 'text-muted' }}">
                <x-ikon :name="$slot['nama']" :size="22" />
                <span class="text-[0.6875rem] font-medium leading-none">{{ $slot['label'] }}</span>

                @if ($slot['nama'] === 'cart' && $jumlahKeranjang > 0)
                    <span class="dot-count !right-[calc(50%-22px)] !top-1.5">
                        {{ $jumlahKeranjang > 99 ? '99+' : $jumlahKeranjang }}
                    </span>
                @endif

                @if ($aktif($slot['pola']))
                    <span class="absolute inset-x-6 top-0 h-0.5 bg-accent" aria-hidden="true"></span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
