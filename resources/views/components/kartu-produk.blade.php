{{--
    Kartu produk.

    Seluruh badan kartu dapat diklik, bukan hanya gambar dan judul. Satu
    lapisan tautan absolut menutupi kartu, lalu elemen yang punya tindakan
    sendiri (tombol keranjang) dinaikkan ke atas lapisan itu. Pola ini menjaga
    kartu tetap satu tautan bagi pembaca layar — tanpa tautan bersarang, tanpa
    JavaScript.

    PROPORSI. Versi sebelumnya menumpuk enam lapis teks (kategori, judul, SKU,
    "mulai dari", harga, dolar, jumlah varian) di bawah gambar 4:3. Di grid dua
    kolom pada ponsel hasilnya kartu kurus dan memanjang — terlihat tidak
    seimbang. Perbaikannya bukan memperkecil font, melainkan membuang yang tidak
    perlu dan memindahkan yang bisa dipindah:

      1. SKU dihapus dari kartu. Nomor bagian itu berguna di halaman produk,
         tidak di daftar — pembeli memilih dari gambar dan harga.
      2. Jumlah varian pindah ke atas gambar sebagai lencana, bukan baris teks
         tersendiri. Ia informasi sekunder dan tidak boleh memakan tinggi.
      3. Dolar digabung SEBARIS dengan rupiah, dipisah titik tengah. Dua baris
         harga untuk satu angka membuat mata berhenti dua kali.
      4. Judul diberi tinggi tetap dua baris (min-h). Tanpa itu, kartu dengan
         judul satu baris dan dua baris berdampingan jadi berbeda tinggi, dan
         baris harganya tidak sejajar — itulah yang paling terasa "tidak rapi".
      5. Umpan balik tombol tidak lagi memesan ruang kosong. Ia hanya muncul
         saat ada pesan, jadi kartu tenang tidak menyisakan celah.
--}}
@php
    /** @var \App\Models\Product $produk */
    $media = $produk->gambarUtama();
    $adaStok = $produk->adaStok();
    $harga = $produk->hargaMulai();
    $jumlahVarian = $produk->variants->where('is_active', true)->count();
    $tujuan = route('front.products.show', $produk);
    $varianPertama = $produk->variants->where('is_active', true)->first();
    $perkiraan = \App\Support\Kurs::perkiraan($harga);
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

        {{-- Lencana di atas gambar: informasi sekunder yang tidak memakan
             tinggi kartu. --}}
        <div class="pointer-events-none absolute inset-x-2.5 top-2.5 z-10 flex items-start justify-between gap-2">
            @unless ($adaStok)
                <span class="badge badge-danger bg-surface">
                    {{ __('site.product.out_of_stock') }}
                </span>
            @else
                <span></span>
            @endunless

            @if ($jumlahVarian > 1)
                <span class="badge bg-surface/90 text-[0.625rem] backdrop-blur">
                    {{ $jumlahVarian }} {{ __('site.product.variant') }}
                </span>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-3 lg:p-4">
        <p class="part-no truncate">{{ $produk->category?->nama() }}</p>

        {{-- Tinggi tetap dua baris: kartu yang berdampingan tetap sejajar, dan
             baris harga selalu jatuh di garis yang sama. --}}
        <h3 class="mt-1 line-clamp-2 min-h-[2.6em] text-[0.9375rem] font-semibold leading-snug lg:text-base">
            {{-- Teks judul tanpa tautan sendiri: lapisan di atas sudah menjadi
                 tautannya, dan dua tautan bernama sama membingungkan pembaca
                 layar. pointer-events-none agar klik jatuh ke lapisan. --}}
            <span class="pointer-events-none">{{ $produk->judul() }}</span>
        </h3>

        <div class="mt-auto pt-3">
            {{-- Rupiah dan perkiraan dolar SEBARIS. Rupiah tidak boleh
                 terpotong dan tidak boleh meluber ke samping: tabular-nums
                 menjaga lebarnya tetap, basis-0 + truncate menahan luberan. --}}
            <p class="pointer-events-none flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                <span class="tech text-base font-semibold tabular-nums lg:text-lg">{{ rupiah($harga) }}</span>

                @if ($perkiraan !== null)
                    <span class="text-[0.6875rem] tabular-nums text-dim"
                          title="Perkiraan pada kurs Rp {{ number_format($perkiraan['kurs'], 0, ',', '.') }} per USD">
                        ≈ {{ $perkiraan['teks'] }}
                    </span>
                @endif
            </p>

            @unless ($adaStok)
                <p class="mt-1 text-xs text-dim">{{ __('site.product.out_of_stock') }}</p>
            @endunless

            {{-- Tombol keranjang: barisnya sendiri di bawah harga, lebar penuh.
                 Ia dinaikkan di atas lapisan tautan (z-10) supaya menambah
                 barang, bukan membuka halaman produk. Produk tanpa stok tidak
                 mendapat tombol. --}}
            @if ($adaStok && $varianPertama !== null)
                <div class="relative z-10 mt-2.5">
                    <livewire:front.tambah-keranjang-cepat
                        :varian-id="$varianPertama->id"
                        :key="'cepat-'.$produk->id" />
                </div>
            @endif
        </div>
    </div>
</article>
