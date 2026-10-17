@php
    /** @var \App\Support\Pengaturan $toko */
    $wa = preg_replace('/\D/', '', $toko->teks('whatsapp_number'));

    // Pesan berbeda per konteks halaman.
    $produk = request()->route('product');
    $template = $toko->teks('whatsapp_message_template');

    $pesan = match (true) {
        $produk instanceof \App\Models\Product => "Halo {$toko->teks('site_name')}, saya tertarik dengan {$produk->judul()} ({$produk->sku}). Stoknya masih ada?",
        request()->routeIs('front.cart') => "Halo, saya ingin menanyakan pesanan saya dengan ".app(\App\Services\Keranjang::class)->jumlahItem().' item.',
        request()->routeIs('front.products.*') => 'Halo, saya sedang melihat katalog. Boleh minta rekomendasi?',
        default => $template ?: "Halo {$toko->teks('site_name')}, saya ingin bertanya tentang knalpot.",
    };
@endphp

@if ($wa)
    {{-- Satu-satunya elemen di situs yang boleh berbentuk bulat penuh. --}}
    <div x-data="{ buka: !sessionStorage.getItem('iksan.wa.tutup') }" class="fixed z-30"
         style="right: 20px; bottom: calc(84px + env(safe-area-inset-bottom, 0px));"
         :style="window.innerWidth >= 1024 ? 'right:24px;bottom:24px' : 'right:20px;bottom:calc(84px + env(safe-area-inset-bottom,0px))'">

        <div x-show="buka" x-cloak x-transition.opacity
             class="plate-up mb-3 w-[min(78vw,19rem)] p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-display text-lg font-semibold">
                        {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                    </p>
                    <p class="mt-1 flex items-center gap-2 text-xs text-muted">
                        <span class="live-dot" aria-hidden="true"></span>
                        {{ $toko->teks('operational_hours', __('site.contact.hours')) }}
                    </p>
                </div>
                <button type="button" class="btn-icon !h-8 !w-8"
                        @click="buka = false; sessionStorage.setItem('iksan.wa.tutup', '1')">
                    <x-ikon name="close" :size="16" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            <p class="mt-3 text-sm text-muted">{{ __('site.home.cta_body') }}</p>

            <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($pesan) }}"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-primary mt-4 w-full">
                <x-ikon name="whatsapp" :size="18" />
                {{ __('site.home.cta_primary') }}
            </a>
        </div>

        <button type="button" x-show="!buka" x-cloak
                @click="buka = true; sessionStorage.removeItem('iksan.wa.tutup')"
                class="ml-auto flex h-14 w-14 items-center justify-center rounded-full
                       bg-[#25D366] text-white shadow-[var(--shadow-3)]"
                title="{{ __('site.home.cta_primary') }}">
            <x-ikon name="whatsapp" :size="26" />
            <span class="visually-hidden">{{ __('site.home.cta_primary') }}</span>
        </button>
    </div>
@endif
