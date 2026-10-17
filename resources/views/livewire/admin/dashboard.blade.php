<div class="space-y-6">

    {{-- Peringatan yang bersumber dari data nyata. --}}
    @if (count($peringatan) > 0)
        <div class="plate divide-y divide-line-soft">
            @foreach ($peringatan as $p)
                <a href="{{ $p['rute'] }}" class="flex min-h-[48px] items-center gap-3 px-4 py-2.5 text-sm hover:bg-sunken">
                    <span class="badge badge-{{ $p['nada'] }}">!</span>
                    <span class="flex-1">{{ $p['teks'] }}</span>
                    <x-ikon name="chevron-right" :size="16" class="flex-none text-dim" />
                </a>
            @endforeach
        </div>
    @endif

    {{-- Kartu angka. Di mobile dua kolom, bukan satu kolom panjang. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        @foreach ($kartu as $k)
            <div class="plate p-4">
                <div class="flex items-center justify-between">
                    <p class="part-no">{{ $k['label'] }}</p>
                    <x-ikon :name="$k['ikon']" :size="18" class="text-dim" />
                </div>
                <p class="tech mt-3 text-2xl font-medium lg:text-3xl">{{ $k['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Grafik 30 hari. SVG sederhana, tanpa pustaka grafik. --}}
    <div class="plate p-4 lg:p-6">
        <div class="flex items-baseline justify-between">
            <p class="part-no">Penjualan 30 hari terakhir</p>
            <p class="tech text-xs text-dim">Nilai per hari</p>
        </div>

        @if ($seri->sum('jumlah') === 0)
            <div class="py-10 text-center">
                <p class="font-semibold">Belum ada pesanan pada 30 hari terakhir.</p>
                <p class="mt-1 text-sm text-muted">Grafik akan terisi setelah pesanan pertama masuk.</p>
            </div>
        @else
            <div class="mt-6 flex h-40 items-end gap-[3px]">
                @foreach ($seri as $titik)
                    <div class="group relative flex-1" title="{{ $titik['label'] }} · {{ $titik['jumlah'] }} pesanan · {{ rupiah($titik['nilai']) }}">
                        <div class="w-full bg-accent"
                             style="height: {{ max(2, (int) round(($titik['nilai'] / $maksNilai) * 150)) }}px"
                             aria-hidden="true"></div>
                        <span class="visually-hidden">{{ $titik['label'] }}: {{ $titik['jumlah'] }} pesanan</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex justify-between text-xs text-dim">
                <span class="tech">{{ $seri->first()['label'] }}</span>
                <span class="tech">{{ $seri->last()['label'] }}</span>
            </div>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_1fr]">

        {{-- Pesanan terbaru. Di bawah 640px menjadi daftar kartu, bukan tabel. --}}
        <div class="plate overflow-hidden">
            <div class="flex items-center justify-between border-b border-line-soft px-4 py-3">
                <p class="part-no">Pesanan terbaru</p>
                <a href="{{ route('admin.orders.index') }}" class="text-sm text-accent hover:underline" wire:navigate>
                    Semua
                </a>
            </div>

            @if ($terbaru->isEmpty())
                <div class="p-8 text-center">
                    <p class="font-semibold">Belum ada pesanan.</p>
                    <p class="mt-1 text-sm text-muted">Pesanan dari situs akan muncul di sini.</p>
                </div>
            @else
                {{-- Desktop: tabel. --}}
                <div class="hidden sm:block">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nomor</th>
                                <th>Penerima</th>
                                <th>Status</th>
                                <th class="!text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($terbaru as $o)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $o) }}" class="tech text-accent hover:underline" wire:navigate>
                                            {{ $o->order_number }}
                                        </a>
                                        <span class="block text-xs text-dim">{{ $o->created_at?->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td class="max-w-[12rem] truncate">{{ $o->namaPenerima() }}</td>
                                    <td><x-lencana :nada="$o->status->nada()" :teks="$o->status->label()" /></td>
                                    <td class="tech !text-right">{{ rupiah($o->grand_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: satu kartu per pesanan. --}}
                <ul class="divide-y divide-line-soft sm:hidden">
                    @foreach ($terbaru as $o)
                        <li>
                            <a href="{{ route('admin.orders.show', $o) }}" wire:navigate
                               class="flex min-h-[64px] flex-col justify-center gap-1 px-4 py-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="tech text-sm">{{ $o->order_number }}</span>
                                    <x-lencana :nada="$o->status->nada()" :teks="$o->status->label()" />
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm text-muted">{{ $o->namaPenerima() }}</span>
                                    <span class="tech flex-none text-sm">{{ rupiah($o->grand_total) }}</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Produk terlaris bulan berjalan. --}}
        <div class="plate overflow-hidden">
            <div class="border-b border-line-soft px-4 py-3">
                <p class="part-no">Terlaris bulan ini</p>
            </div>

            @if ($terlaris->isEmpty())
                <div class="p-8 text-center">
                    <p class="font-semibold">Belum ada penjualan bulan ini.</p>
                </div>
            @else
                <ul class="divide-y divide-line-soft">
                    @foreach ($terlaris as $i => $t)
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="tech flex-none text-dim">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $t->nama }}</span>
                                <span class="tech block text-xs text-dim">{{ $t->terjual }} terjual</span>
                            </span>
                            <span class="tech flex-none text-sm">{{ rupiah($t->nilai) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
