@props(['produk'])

@php
    /** @var \App\Models\Product $produk */
    $media = $produk->gambarUtama();
    $adaStok = $produk->adaStok();
    $harga = $produk->hargaMulai();
    $jumlahVarian = $produk->variants->where('is_active', true)->count();
@endphp

<article class="plate group relative flex flex-col overflow-hidden">
    <a href="{{ route('front.products.show', $produk) }}" wire:navigate
       class="block aspect-[4/3] overflow-hidden bg-sunken">
        @if ($media)
            <img src="{{ $media->url('card') }}"
                 srcset="{{ $media->url('thumb') }} 320w, {{ $media->url('card') }} 640w, {{ $media->url('full') }} 1600w"
                 sizes="(min-width: 1024px) 25vw, 50vw"
                 width="{{ $media->width ?: 640 }}" height="{{ $media->height ?: 480 }}"
                 alt="{{ $media->alt() }}"
                 loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
        @else
            <div class="flex h-full w-full items-center justify-center text-dim">
                <x-ikon name="image" :size="32" />
            </div>
        @endif
    </a>

    {{-- Badge habis hanya muncul bila seluruh varian stoknya nol. --}}
    @unless ($adaStok)
        <span class="absolute left-2 top-2 badge badge-danger bg-surface">{{ __('site.product.out_of_stock') }}</span>
    @endunless

    <div class="flex flex-1 flex-col p-3 lg:p-4">
        <p class="part-no">{{ $produk->category?->nama() }}</p>

        <h3 class="mt-1.5 line-clamp-2 text-base font-semibold leading-snug lg:text-lg">
            <a href="{{ route('front.products.show', $produk) }}" wire:navigate>
                {{ $produk->judul() }}
            </a>
        </h3>

        <p class="tech mt-2 text-sm text-dim">{{ $produk->sku }}</p>

        <div class="mt-auto pt-3">
            <p class="part-no">{{ __('site.product.from') }}</p>
            <p class="tech text-lg font-medium">{{ rupiah($harga) }}</p>

            @if ($jumlahVarian > 1)
                <p class="mt-0.5 text-xs text-dim">{{ $jumlahVarian }} {{ __('site.product.variant') }}</p>
            @endif
        </div>
    </div>
</article>
