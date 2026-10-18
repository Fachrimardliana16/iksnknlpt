<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · keranjang</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Keranjang Terbuka</h1>
            <p class="mt-1 text-sm text-muted">
                Barang yang sudah dimasukkan pembeli tetapi belum dibayar.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-outline" wire:click="perbarui"
                    wire:loading.attr="disabled" wire:target="perbarui">
                <x-ikon name="refresh" :size="16" />
                <span wire:loading.remove wire:target="perbarui">Segarkan</span>
                <span wire:loading wire:target="perbarui">Memuat…</span>
            </button>

            <button type="button" class="btn btn-outline text-danger"
                    wire:click="bersihkanKedaluwarsa"
                    wire:confirm="Buang keranjang yang tidak bergerak lebih dari 7 hari?"
                    title="Buang keranjang yang tidak bergerak lebih dari 7 hari">
                <x-ikon name="trash" :size="16" />
                Bersihkan
            </button>
        </div>
    </div>

    @if (session('pesan-admin'))
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ session('pesan-admin') }}</div>
    @endif

    {{-- ── Angka ringkas ───────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Keranjang aktif', 'nilai' => number_format($ringkas['total'], 0, ',', '.'), 'ikon' => 'cart'],
            ['label' => 'Nilai tertahan', 'nilai' => rupiah($ringkas['nilai']), 'ikon' => 'chart'],
            ['label' => 'Baru (24 jam)', 'nilai' => number_format($ringkas['harian'], 0, ',', '.'), 'ikon' => 'activity'],
            ['label' => 'Mengendap', 'nilai' => number_format($ringkas['mengendap'], 0, ',', '.'), 'ikon' => 'clock'],
        ] as $k)
            <div class="plate p-4">
                <div class="flex items-center justify-between">
                    <p class="part-no">{{ $k['label'] }}</p>
                    <x-ikon :name="$k['ikon']" :size="17" class="text-dim" />
                </div>
                <p class="tech mt-3 text-xl font-medium lg:text-2xl">{{ $k['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Saringan ────────────────────────────────────────────────── --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="flex gap-1" role="group" aria-label="Umur keranjang">
            @foreach (['semua' => 'Semua', 'harian' => 'Baru', 'tua' => 'Mengendap'] as $nilai => $label)
                <button type="button" wire:click="aturUmur('{{ $nilai }}')"
                        class="btn {{ $umur === $nilai ? 'btn-primary' : 'btn-ghost' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <label class="ml-auto flex min-w-[12rem] flex-1 items-center gap-2 lg:max-w-xs">
            <x-ikon name="search" :size="17" class="flex-none text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari"
                   class="field" placeholder="Cari nama pelanggan atau produk" />
        </label>
    </div>

    {{-- ── Daftar keranjang ────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse ($keranjang as $k)
            <article class="plate p-4" wire:key="keranjang-{{ $k->id }}">

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $this->pemilik($k) }}</p>

                            @if ($k->user === null)
                                <span class="badge badge-neutral">tanpa akun</span>
                            @endif

                            <span class="badge {{ $k->updated_at->gt(now()->subDay()) ? 'badge-ok' : 'badge-warn' }}">
                                {{ $this->sejak($k->updated_at) }}
                            </span>

                            @if ($this->perluPerhatian($k->items))
                                <span class="badge badge-danger">stok tidak cukup</span>
                            @endif

                            @if ($k->coupon)
                                <span class="badge badge-accent">{{ $k->coupon->code }}</span>
                            @endif
                        </div>

                        <p class="tech mt-1.5 text-xs text-dim">
                            {{ $k->ulid }}
                            @if ($k->user)
                                · {{ $k->user->email }}
                            @endif
                            @if ($k->session_id)
                                · sesi {{ mb_substr($k->session_id, 0, 10) }}
                            @endif
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="part-no">{{ $k->jumlahItem() }} barang</p>
                        <p class="tech text-lg font-medium">{{ rupiah($k->subtotal()) }}</p>
                    </div>
                </div>

                {{-- Isi keranjang --}}
                <ul class="mt-3 divide-y divide-line-soft border-t border-line-soft">
                    @foreach ($k->items as $item)
                        @php
                            $varian = $item->variant;
                            $tersedia = $varian?->stokTersedia() ?? 0;
                        @endphp
                        <li class="flex items-center gap-3 py-2.5">
                            <div class="h-10 w-10 flex-none overflow-hidden rounded-[var(--r-kecil)] bg-sunken">
                                @php $media = $item->product?->gambarUtama(); @endphp
                                @if ($media)
                                    <img src="{{ $media->url('thumb') }}" alt=""
                                         class="h-full w-full object-cover" loading="lazy" />
                                @else
                                    <span class="flex h-full w-full items-center justify-center text-dim">
                                        <x-ikon name="image" :size="16" />
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm">{{ $item->product?->judul() ?? '(produk dihapus)' }}</p>
                                <p class="tech text-xs text-dim">
                                    {{ $varian?->nama() ?? '(varian dihapus)' }}
                                    · stok tersedia {{ $tersedia }}
                                </p>
                            </div>

                            <div class="flex-none text-right">
                                <p class="tech text-sm">{{ $item->quantity }}×</p>
                                <p class="tech text-xs text-dim">
                                    {{ $varian ? rupiah($varian->hargaAkhir()) : '—' }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </article>
        @empty
            <div class="plate p-10 text-center">
                <x-ikon name="cart" :size="30" class="mx-auto text-dim" />
                <p class="mt-3 font-semibold">Tidak ada keranjang terbuka.</p>
                <p class="mt-1 text-sm text-muted">
                    @if (filled($cari) || $umur !== 'semua')
                        Tidak ada yang cocok dengan saringan ini. Coba lebarkan saringan.
                    @else
                        Halaman ini terisi sendiri begitu pengunjung memasukkan produk ke keranjang.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    @if ($keranjang->hasPages())
        <div>{{ $keranjang->links() }}</div>
    @endif

    {{-- ── Produk yang paling sering tertinggal ────────────────────── --}}
    @if ($ringkas['terpopuler']->isNotEmpty())
        <div class="plate p-4">
            <p class="part-no">Paling sering tertinggal di keranjang</p>

            <div class="mt-3 space-y-2">
                @foreach ($ringkas['terpopuler'] as $p)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="truncate">{{ $p->nama }}</span>
                        <span class="tech flex-none text-muted">
                            {{ $p->jumlah }} pcs · {{ $p->keranjang }} keranjang
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
