{{-- Tombol tambah ke keranjang pada kartu katalog.

     Tombol ini berada di atas lapisan tautan kartu (z-10), jadi menekannya
     menambah barang, bukan membuka halaman produk.

     Tata letak: lebar penuh, tinggi sentuh 44px. Dulu kotak 40px di sisi harga;
     karena kartu dua kolom di ponsel sempit, tombol itu mengambil ruang
     horizontal sehingga harga "Rp 1.250.000" terpotong.

     Umpan balik sengaja TIDAK memesan tinggi tetap. Versi sebelumnya selalu
     menyisakan satu baris kosong di bawah tombol pada setiap kartu, dan itu
     menambah tinggi kartu tanpa alasan — kartu terlihat renggang dan tidak
     seimbang. Sekarang baris itu hanya ada bila memang ada pesan. --}}
<div class="flex flex-col gap-1">
    <button type="button"
            wire:click="tambah"
            wire:loading.attr="disabled"
            wire:target="tambah"
            aria-label="{{ __('site.product.add_to_cart') }}"
            title="{{ __('site.product.add_to_cart') }}"
            class="flex min-h-[44px] w-full items-center justify-center gap-1.5 rounded-[var(--r-btn)]
                   border border-transparent bg-accent px-3 text-accent-fg
                   transition duration-200 hover:bg-accent-hover hover:shadow-[0_6px_16px_color-mix(in_srgb,var(--c-accent)_32%,transparent)]
                   active:scale-[0.98] disabled:opacity-60">

        <span wire:loading.remove wire:target="tambah" class="flex min-w-0 items-center justify-center gap-1.5">
            <x-ikon name="plus" :size="16" class="flex-none" />
            {{-- Tidak memakai truncate: pada kartu dua kolom di ponsel sempit,
                 "Tambah ke Keranjang" terpotong menjadi "Tambah ke Ker…".
                 leading-tight + text-center membuatnya turun ke dua baris
                 dengan rapi alih-alih dipotong. --}}
            <span class="text-center text-xs font-medium leading-tight sm:text-[0.8125rem]">
                {{ __('site.product.add_to_cart') }}
            </span>
        </span>
        <span wire:loading wire:target="tambah" class="tech text-xs">…</span>
    </button>

    @if ($pesanKunci !== '' || $jumlahDiKeranjang > 0)
        {{-- Umpan balik singkat. Pembaca layar membacanya karena aria-live. --}}
        <span aria-live="polite" class="tech text-[0.625rem] leading-none
            {{ $nada === 'danger' ? 'text-danger' : 'text-ok' }}">
            @if ($pesanKunci !== '')
                {{ __($pesanKunci) }}
            @else
                {{ $jumlahDiKeranjang }} {{ __('site.cart.in_cart') }}
            @endif
        </span>
    @endif
</div>
