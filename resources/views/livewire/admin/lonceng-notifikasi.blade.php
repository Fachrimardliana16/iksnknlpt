{{-- Lonceng notifikasi panel.

     Isinya dihitung dari kueri saat dirender, jadi tidak ada notifikasi basi.
     Setiap butir membawa tautan; notifikasi yang tidak dapat ditindaklanjuti
     hanya menambah kekhawatiran tanpa menambah kemampuan. --}}
<div class="relative" wire:key="lonceng-notifikasi">

    <button type="button" class="btn-icon relative" wire:click="alihkan"
            aria-expanded="{{ $buka ? 'true' : 'false' }}"
            title="Notifikasi" aria-label="Notifikasi">
        <x-ikon name="bell" :size="19" />

        @if ($butir->isNotEmpty())
            <span class="dot-count" aria-hidden="true">{{ $butir->count() > 9 ? '9+' : $butir->count() }}</span>
        @endif

        @if ($mendesak > 0)
            {{-- Cincin berdenyut hanya untuk hal yang benar-benar mendesak:
                 stok habis dan pesanan yang hampir batal otomatis. --}}
            <span class="absolute inset-1 animate-ping rounded-full bg-danger/25" aria-hidden="true"></span>
            <span class="visually-hidden">{{ $mendesak }} notifikasi mendesak</span>
        @endif
    </button>

    @if ($buka)
        {{-- Penutup klik-di-luar tanpa Alpine: satu lapisan transparan yang
             menutupi layar, di bawah panel. --}}
        <div class="fixed inset-0 z-40" wire:click="alihkan" aria-hidden="true"></div>

        <div class="plate-up absolute right-0 top-full z-50 mt-2 w-[min(92vw,22rem)]" role="dialog"
             aria-label="Daftar notifikasi">

            <div class="flex items-center justify-between border-b border-line-soft px-4 py-3">
                <p class="part-no">Perlu perhatian</p>

                <button type="button" class="btn-icon !h-8 !w-8" wire:click="segarkan" title="Segarkan">
                    <x-ikon name="refresh" :size="15" />
                    <span class="visually-hidden">Segarkan</span>
                </button>
            </div>

            @if ($butir->isEmpty())
                <div class="p-6 text-center">
                    <x-ikon name="check" :size="26" class="mx-auto text-ok" />
                    <p class="mt-2 text-sm font-medium">Tidak ada yang perlu ditangani.</p>
                    <p class="mt-1 text-xs text-muted">
                        Stok aman, tidak ada pesanan menunggu, tidak ada keranjang mengendap.
                    </p>
                </div>
            @else
                <ul class="max-h-[70vh] divide-y divide-line-soft overflow-y-auto">
                    @foreach ($butir as $b)
                        <li>
                            <a href="{{ $b['rute'] }}" wire:navigate
                               class="flex items-start gap-3 px-4 py-3 hover:bg-sunken">
                                <span class="mt-0.5 flex-none rounded-[var(--r-kecil)] p-1.5
                                    @switch($b['nada'])
                                        @case('danger') bg-danger/12 text-danger @break
                                        @case('warn') bg-warn/12 text-warn @break
                                        @case('steel') bg-steel/12 text-steel @break
                                        @default bg-accent/12 text-accent
                                    @endswitch">
                                    <x-ikon :name="$b['ikon']" :size="17" />
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">{{ $b['teks'] }}</span>

                                    @if (filled($b['rinci']))
                                        <span class="mt-0.5 block text-xs text-muted">{{ $b['rinci'] }}</span>
                                    @endif
                                </span>

                                <x-ikon name="chevron-right" :size="15" class="mt-1 flex-none text-dim" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
