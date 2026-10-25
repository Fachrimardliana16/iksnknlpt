{{-- Performa KOL.

     Angka di sini berasal dari baris pesanan yang sudah memakai kupon, bukan
     dari hitungan klik. Bedanya penting: klik menunjukkan minat, pesanan
     menunjukkan uang yang benar-benar masuk — dan KOL dibayar dari yang kedua.

     Hanya pesanan berstatus paid atau completed yang dihitung. Pesanan yang
     dibatalkan atau kedaluwarsa ikut dihitung akan membuat KOL tampak
     menghasilkan padahal uangnya tidak pernah masuk. --}}
<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · referral</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Performa KOL</h1>
            <p class="mt-1 text-sm text-muted">
                Penjualan yang dihasilkan tiap KOL lewat kupon berbaginya.
            </p>
        </div>

        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline">
            <x-ikon name="tag" :size="16" />
            Halaman Kupon
        </a>
    </div>

    @if ($ringkas['kol'] === 0)
        <div class="plate p-8 text-center">
            <x-ikon name="share" :size="24" class="mx-auto text-dim" />
            <h2 class="mt-3 font-medium">Belum ada KOL</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">
                Tambahkan nama KOL pada sebuah kupon di halaman Kupon. Kupon itu
                lalu punya tautan berbagi sendiri dan penjualannya terhitung di sini.
            </p>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-primary mt-4">
                Buka halaman Kupon
            </a>
        </div>
    @else
        {{-- ── Ringkasan ──────────────────────────────────────────── --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="plate p-4">
                <p class="part-no">KOL aktif</p>
                <p class="tech mt-3 text-xl font-medium">{{ $ringkas['kol'] }}</p>
            </div>
            <div class="plate p-4">
                <p class="part-no">Pesanan masuk</p>
                <p class="tech mt-3 text-xl font-medium">{{ number_format($ringkas['pesanan'], 0, ',', '.') }}</p>
            </div>
            <div class="plate p-4">
                <p class="part-no">Penjualan</p>
                <p class="tech mt-3 text-xl font-medium">{{ rupiah($ringkas['penjualan']) }}</p>
            </div>
        </div>

        {{-- ── Saringan ───────────────────────────────────────────── --}}
        <div class="plate flex flex-wrap items-center gap-2 p-3" x-data="{bukaUrut: false}">
            <label class="flex min-w-[12rem] flex-1 items-center gap-2 lg:max-w-md">
                <x-ikon name="search" :size="17" class="flex-none text-dim" />
                <input type="search" wire:model.live.debounce.400ms="cari" class="field"
                       placeholder="Cari nama KOL atau kode kupon" />
            </label>

            <div class="relative">
                <button type="button" @click="bukaUrut = !bukaUrut"
                        class="btn btn-outline" aria-haspopup="true" :aria-expanded="bukaUrut">
                    <x-ikon name="sliders" :size="16" />
                    {{ match ($urut) {
                        'pesanan' => 'Terbanyak pesanan',
                        'nama' => 'Nama A-Z',
                        default => 'Penjualan terbesar',
                    } }}
                    <x-ikon name="chevron-down" :size="14" class="text-dim" />
                </button>

                <div x-show="bukaUrut" x-cloak @click.outside="bukaUrut = false"
                     class="plate absolute right-0 z-20 mt-1 w-52 overflow-hidden p-1 shadow-lg">
                    @foreach (['penjualan' => 'Penjualan terbesar', 'pesanan' => 'Terbanyak pesanan', 'nama' => 'Nama A-Z'] as $nilai => $label)
                        <button type="button" wire:click="aturUrut('{{ $nilai }}')" @click="bukaUrut = false"
                                class="flex w-full items-center justify-between gap-2 rounded-[var(--r-kecil)] px-3 py-2 text-left text-sm hover:bg-sunken">
                            {{ $label }}
                            @if ($urut === $nilai) <x-ikon name="check" :size="15" class="text-accent" /> @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Daftar KOL ─────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line-soft text-left">
                            <th class="part-no p-3 font-medium">KOL</th>
                            <th class="part-no p-3 font-medium">Kupon</th>
                            <th class="part-no p-3 text-right font-medium">Pesanan sah</th>
                            <th class="part-no p-3 text-right font-medium">Penjualan</th>
                            <th class="part-no p-3 text-right font-medium">Potongan</th>
                            <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftar as $kupon)
                            <tr class="border-b border-line-soft last:border-0 hover:bg-sunken" wire:key="kol-{{ $kupon->id }}">
                                <td class="p-3">
                                    <p class="font-medium">{{ $kupon->kol_nama ?? $kupon->kol_slug }}</p>
                                    <p class="tech text-xs text-dim">{{ $kupon->kol_slug }}</p>
                                </td>

                                <td class="p-3">
                                    <span class="tech text-xs">{{ $kupon->code }}</span>
                                    @if ($kupon->quota !== null)
                                        <p class="tech text-xs text-dim">
                                            kuota {{ $kupon->used_count }}/{{ $kupon->quota }}
                                        </p>
                                    @endif
                                </td>

                                <td class="tech p-3 text-right">
                                    {{ number_format((int) $kupon->pesanan_sah, 0, ',', '.') }}
                                    @if ((int) $kupon->pesanan_total > (int) $kupon->pesanan_sah)
                                        <span class="block text-xs text-dim" title="Termasuk yang dibatalkan atau kedaluwarsa">
                                            {{ (int) $kupon->pesanan_total }} dipakai
                                        </span>
                                    @endif
                                </td>

                                <td class="tech p-3 text-right font-medium">{{ rupiah((float) $kupon->nilai_penjualan) }}</td>

                                <td class="tech p-3 text-right text-muted">{{ rupiah((float) $kupon->potongan_diberikan) }}</td>

                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button"
                                                x-data
                                                x-on:click="navigator.clipboard.writeText(@js($kupon->tautanKol())); \$el.setAttribute('data-salin', 'tersalin'); window.setTimeout(() => \$el.removeAttribute('data-salin'), 1200)"
                                                class="btn-icon h-8 w-8"
                                                title="Salin tautan berbagi"
                                                aria-label="Salin tautan berbagi {{ $kupon->kol_nama }}">
                                            <x-ikon name="copy" :size="15" />
                                            <span class="visually-hidden">Salin tautan berbagi</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-sm text-muted">
                                    Tidak ada KOL yang cocok dengan pencarian.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
            @endif
        </div>

        <p class="text-xs text-dim">
            Hanya pesanan berstatus <em>dibayar</em> atau <em>selesai</em> yang dihitung.
            Pesanan yang dibatalkan atau kedaluwarsa tidak masuk, supaya angka
            penjualan berarti uang yang benar-benar masuk.
        </p>
    @endif
</div>
