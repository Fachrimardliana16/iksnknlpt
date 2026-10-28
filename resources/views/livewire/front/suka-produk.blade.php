{{-- Tombol suka pada kartu katalog.

     Letaknya di atas gambar, di kanan atas, BUKAN di baris harga: baris harga
     sudah memuat tombol keranjang, dan menambah tombol ketiga di sana membuat
     kartu tinggi tanpa menambah informasi.

     Bentuknya kotak ikon 36px, bukan pil bertulisan. Tiga alasan:
       1. Kartu katalog dua kolom di ponsel sempit — pil "Suka" memakan lebar
          yang dipakai harga.
       2. Jumlah suka lebih kecil daripada harga; ia keterangan, bukan tombol.
       3. Tombol keranjang tetap satu-satunya tindakan utama pada kartu.

     Jumlah suka tampil di bawah ikon, hanya bila ada yang menyukai. Nol suka
     tidak memesan ruang — setiap kartu akan menyisakan satu baris kosong. --}}
<div class="flex flex-col items-center gap-0.5" wire:key="suka-{{ $produkId }}">

    <button type="button"
            wire:click="alihkan"
            wire:loading.attr="disabled"
            wire:target="alihkan"
            aria-pressed="{{ $disukai ? 'true' : 'false' }}"
            aria-label="{{ $disukai ? 'Batalkan suka produk ini' : 'Sukai produk ini' }}"
            title="{{ $disukai ? 'Batalkan suka' : 'Suka' }}"
            class="flex h-9 w-9 items-center justify-center rounded-[var(--r-kecil)] border
                   transition duration-200 active:scale-95 disabled:opacity-60
                   {{ $disukai
                        ? 'border-accent bg-accent/10 text-accent'
                        : 'border-line bg-surface/90 text-muted hover:text-accent' }}">

        {{-- Ikon hati: terisi saat disukai, garis saat belum. --}}
        <svg viewBox="0 0 24 24" width="16" height="16" fill="{{ $disukai ? 'currentColor' : 'none' }}"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l8.84 8.84 8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
    </button>

    @if ($jumlah > 0)
        <span aria-live="polite"
              class="tech text-[0.625rem] leading-none {{ $disukai ? 'text-accent' : 'text-dim' }}">
            {{ $jumlah }}
        </span>
    @endif

    @if ($pesan !== '')
        <span aria-live="polite" class="text-[0.625rem] leading-none text-danger">{{ $pesan }}</span>
    @endif
</div>
