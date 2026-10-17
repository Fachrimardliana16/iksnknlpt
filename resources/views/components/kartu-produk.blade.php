{{--
    Kartu produk.

    Seluruh badan kartu sekarang dapat diklik, bukan hanya gambar dan judul.
    Sebelumnya hanya dua elemen itu yang bertaut; kategori, SKU, dan harga
    tidak bereaksi apa pun saat disentuh — pada ponsel itu terasa seperti kartu
    rusak, karena separuh luas kartu memang mati.

    Caranya: satu lapisan tautan absolut menutupi seluruh kartu, lalu elemen
    yang punya tindakan sendiri (tombol tambah ke keranjang) dinaikkan ke atas
    lapisan itu. Pola ini menjaga kartu tetap satu tautan bagi pembaca layar —
    tidak ada tautan bersarang yang membingungkan — tanpa JavaScript.

    Karena tombol keranjang harus terlihat di ponsel, kartu memuat satu
    formulir Livewire kecil per produk. Sepuluh kartu berarti sepuluh komponen,
    dan itu masih jauh di bawah ambang yang membuat halaman berat.
--}}
@php
    /** @var \App\Models\Product $produk */
    $media = $produk->gambarUtama();
    $adaStok = $produk->adaStok();
    $harga = $produk->hargaMulai();
    $jumlahVarian = $produk->variants->where('is_active', true)->count();
    $tujuan = route('front.products.show', $produk);
    $varianPertama = $produk->variants->where('is_active', true)->first();
@endphp

<article class="plate plate-interaktif group relative flex h-full flex-col overflow-hidden">

    {{-- Lapisan tautan: menutupi seluruh kartu. z-0, di bawah isi. --}}
    <a href="{{ $tujuan }}" wire:navigate
       class="absolute inset-0 z-0"
       aria-label="{{ $produk->judul() }}">
        <span class="visually-hidden">{{ $produk->judul() }}</span>
    </a>

    <div class="relative z-0 aspect-[4/3] overflow-hidden bg-sunken">
        @if ($media)
            <img src="{{ $media->url('card') }}"
                 srcset="{{ $media->url('thumb') }} 320w, {{ $media->url('card') }} 640w, {{ $media->url('full') }} 1600w"
                 sizes="(min-width: 1024px) 25vw, 50vw"
                 width="{{ $media->width ?: 640 }}" height="{{ $media->height ?: 480 }}"
                 alt="{{ $media->alt() }}"
                 loading="lazy" decoding="async"
                 class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.05]">
        @else
            <div class="flex h-full w-full items-center justify-center text-dim">
                <x-ikon name="image" :size="32" />
            </div>
        @endif
    </div>

    {{-- Badge habis hanya muncul bila seluruh varian stoknya nol. --}}
    @unless ($adaStok)
        <span class="absolute left-2.5 top-2.5 z-10 badge badge-danger bg-surface">
            {{ __('site.product.out_of_stock') }}
        </span>
    @endunless

    <div class="flex flex-1 flex-col p-3 lg:p-4">
        <p class="part-no">{{ $produk->category?->nama() }}</p>

        <h3 class="mt-1.5 line-clamp-2 text-base font-semibold leading-snug lg:text-lg">
            {{-- Teks judul dibiarkan tanpa tautan sendiri: lapisan di atas sudah
                 menjadi tautannya, dan dua tautan bernama sama membingungkan
                 pembaca layar. pointer-events-none agar klik jatuh ke lapisan. --}}
            <span class="pointer-events-none">{{ $produk->judul() }}</span>
        </h3>

        <p class="tech mt-2 text-sm text-dim">{{ $produk->sku }}</p>

        <div class="mt-auto flex items-end justify-between gap-2 pt-3">
            <div class="pointer-events-none min-w-0">
                <p class="part-no">{{ __('site.product.from') }}</p>
                <p class="tech text-lg font-medium">{{ rupiah($harga) }}</p>

                @if ($jumlahVarian > 1)
                    <p class="mt-0.5 text-xs text-dim">{{ $jumlahVarian }} {{ __('site.product.variant') }}</p>
                @endif
            </div>

            {{-- Tombol keranjang: dinaikkan di atas lapisan tautan. Produk tanpa
                 stok tidak mendapat tombol. --}}
            @if ($adaStok && $varianPertama !== null)
                <div class="relative z-10 flex-none">
                    <livewire:front.tambah-keranjang-cepat
                        :varian-id="$varianPertama->id"
                        :key="'cepat-'.$produk->id" />
                </div>
            @endif
        </div>
    </div>
</article>
