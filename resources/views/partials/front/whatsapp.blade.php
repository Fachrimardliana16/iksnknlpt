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
    {{-- Bottom bar keranjang lengket (ada di halaman detail produk) setinggi
         60px (bottom-nav) + 56px (tombol) + padding 12px + jeda = ~152px.
         Tanpa kompensasi, floating WA menutupi tombol "Tambah ke Keranjang".
         Perhitungan dilakukan setiap render via Alpine: kita cek apakah
         elemen .fixed.bottom-bar-cart ada di DOM halaman ini. --}}
    <div x-data="{
            buka: !sessionStorage.getItem('iksan.wa.tutup'),
            lebar: window.innerWidth,
            adaBar: false,
            get offsetBottom() {
                if (this.lebar >= 1024) return '24px';
                return this.adaBar
                    ? 'calc(152px + env(safe-area-inset-bottom, 0px))'
                    : 'calc(60px + env(safe-area-inset-bottom, 0px))';
            },
            ukur() {
                this.lebar = window.innerWidth;
                this.adaBar = !!document.querySelector('.bottom-bar-cart');
            }
         }"
         x-init="ukur()"
         x-on:resize.window.debounce.150ms="ukur()"
         x-on:livewire:navigated.window="ukur()"
         class="fixed z-30"
         style="right: 20px;"
         :style="'right:' + (lebar >= 1024 ? '24px' : '20px') + ';bottom:' + offsetBottom">

        <div x-show="buka" x-cloak x-transition.opacity
             class="plate-up plate-besar mb-3 w-[min(78vw,19rem)] p-4">
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
