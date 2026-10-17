{{-- Tombol tambah ke keranjang pada kartu katalog.

     Tombol ini berada di atas lapisan tautan kartu (z-10), jadi menekannya
     menambah barang, bukan membuka halaman produk. Ukurannya 40px dan
     memenuhi sasaran sentuh minimum secara diagonal; tombol 44px penuh akan
     memakan lebar yang tidak tersedia di kartu dua kolom pada ponsel. --}}
<div class="flex flex-col items-end gap-1">
    <button type="button"
            wire:click="tambah"
            wire:loading.attr="disabled"
            wire:target="tambah"
            aria-label="{{ __('site.product.add_to_cart') }}"
            title="{{ __('site.product.add_to_cart') }}"
            class="flex h-10 w-10 flex-none items-center justify-center rounded-[var(--r-btn)]
                   border border-transparent bg-accent text-accent-fg
                   transition duration-200 hover:bg-accent-hover hover:shadow-[0_6px_16px_color-mix(in_srgb,var(--c-accent)_32%,transparent)]
                   active:scale-95 disabled:opacity-60">

        <span wire:loading.remove wire:target="tambah">
            <x-ikon name="plus" :size="18" />
        </span>
        <span wire:loading wire:target="tambah" class="tech text-xs">…</span>
    </button>

    {{-- Umpan balik singkat. Pembaca layar membacanya karena aria-live. --}}
    <span aria-live="polite" class="tech text-[0.625rem] leading-none
        {{ $nada === 'danger' ? 'text-danger' : 'text-ok' }}">
        @if ($pesanKunci !== '')
            {{ __($pesanKunci) }}
        @elseif ($jumlahDiKeranjang > 0)
            {{ $jumlahDiKeranjang }} {{ __('site.cart.in_cart') }}
        @endif
    </span>
</div>
