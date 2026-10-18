{{-- Kupon diskon.

     Pratinjau potongan ditampilkan memakai perhitungan yang sama dengan
     checkout, sehingga batas atas dan jenis kupon dapat langsung dilihat
     akibatnya sebelum disimpan. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · kupon</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Kupon</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} kupon ·
                {{ number_format($jumlah['berjalan'], 0, ',', '.') }} sedang berlaku
                @if ($jumlah['habis'] > 0) · <span class="text-warn">{{ $jumlah['habis'] }} kuota habis</span> @endif
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            Kupon baru
        </button>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[180px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field pl-9"
                   placeholder="Cari kode kupon…" aria-label="Cari kupon">
        </div>

        @foreach (['semua' => 'Semua', 'berjalan' => 'Berjalan', 'habis' => 'Kuota habis', 'kedaluwarsa' => 'Kedaluwarsa'] as $nilai => $label)
            <button type="button" wire:click="aturKeadaan('{{ $nilai }}')"
                    class="btn {{ $keadaan === $nilai ? 'btn-primary' : 'btn-outline' }} btn-sm">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">

        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="tag" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">Belum ada kupon.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Kode</th>
                                <th class="part-no p-3 font-medium">Potongan</th>
                                <th class="part-no p-3 text-right font-medium">Min. belanja</th>
                                <th class="part-no p-3 text-right font-medium">Terpakai</th>
                                <th class="part-no p-3 font-medium">Masa berlaku</th>
                                <th class="part-no p-3 font-medium">Status</th>
                                <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $k)
                                @php
                                    $kadaluarsa = $k->ends_at !== null && $k->ends_at->isPast();
                                    $habis = $k->quota !== null && $k->used_count >= $k->quota;
                                    $belumMulai = $k->starts_at !== null && $k->starts_at->isFuture();
                                @endphp

                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <code class="tech font-medium">{{ $k->code }}</code>
                                        @if ($k->type->value === 'free_shipping')
                                            <span class="ml-1 rounded-full bg-brass/12 px-1.5 py-0.5 text-xs text-brass">ongkir</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($k->type->value === 'percent')
                                            <span class="tech">{{ rtrim(rtrim(number_format((float) $k->value, 2, ',', '.'), '0'), ',') }}%</span>
                                            @if ($k->max_discount)
                                                <span class="block text-xs text-dim">maks {{ rupiah($k->max_discount) }}</span>
                                            @endif
                                        @elseif ($k->type->value === 'fixed')
                                            <span class="tech">{{ rupiah($k->value) }}</span>
                                        @else
                                            <span class="text-muted">Gratis ongkir</span>
                                        @endif
                                    </td>

                                    <td class="tech p-3 text-right">
                                        {{ $k->min_spend ? rupiah($k->min_spend) : '—' }}
                                    </td>

                                    <td class="p-3 text-right">
                                        <span class="tech {{ $habis ? 'text-warn' : '' }}">{{ $k->used_count }}</span>
                                        <span class="text-xs text-dim">/ {{ $k->quota ?? '∞' }}</span>
                                        @if ($k->orders_count > 0)
                                            <span class="block text-xs text-dim">{{ $k->orders_count }} pesanan</span>
                                        @endif
                                    </td>

                                    <td class="p-3 text-xs text-muted">
                                        @if ($k->starts_at || $k->ends_at)
                                            {{ $k->starts_at?->format('d/m/y') ?? '—' }}
                                            <span class="text-dim">sampai</span>
                                            {{ $k->ends_at?->format('d/m/y') ?? '—' }}
                                        @else
                                            <span class="text-dim">tanpa batas</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($kadaluarsa)
                                            <span class="rounded-full bg-danger/12 px-2 py-0.5 text-xs text-danger">kedaluwarsa</span>
                                        @elseif ($habis)
                                            <span class="rounded-full bg-warn/12 px-2 py-0.5 text-xs text-warn">kuota habis</span>
                                        @elseif ($belumMulai)
                                            <span class="rounded-full bg-sunken px-2 py-0.5 text-xs text-muted">belum mulai</span>
                                        @elseif ($k->is_active)
                                            <span class="rounded-full bg-ok/12 px-2 py-0.5 text-xs text-ok">berjalan</span>
                                        @else
                                            <span class="rounded-full bg-sunken px-2 py-0.5 text-xs text-muted">nonaktif</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="ubahAktif({{ $k->id }})"
                                                    class="btn-icon h-8 w-8" title="{{ $k->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                <x-ikon name="{{ $k->is_active ? 'check' : 'close' }}" :size="15" />
                                                <span class="visually-hidden">{{ $k->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                                            </button>
                                            <button type="button" wire:click="sunting({{ $k->id }})"
                                                    class="btn-icon h-8 w-8" title="Sunting">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">Sunting</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $k->id }})"
                                                    wire:confirm="Hapus kupon {{ $k->code }}?"
                                                    class="btn-icon h-8 w-8 hover:text-danger" title="Hapus">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">Hapus</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($daftar->hasPages())
                    <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
                @endif
            @endif
        </div>

        @if ($formTerbuka)
            <div class="plate h-fit p-4 lg:sticky lg:top-20">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="part-no">{{ $suntingId ? 'Sunting' : 'Baru' }}</p>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $suntingId ? 'Ubah kupon' : 'Tambah kupon' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon h-8 w-8" title="Tutup">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Tutup</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="code" class="part-no">Kode <span class="text-danger">*</span></label>
                        <input id="code" type="text" wire:model="code" class="field tech mt-1 uppercase"
                               placeholder="KNALPOT10" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                        <p class="mt-1 text-xs text-dim">Yang diketik pembeli saat checkout. Huruf besar.</p>
                        @error('code') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="type" class="part-no">Jenis potongan</label>
                        <select id="type" wire:model.live="type" class="field mt-1">
                            <option value="percent">Persentase</option>
                            <option value="fixed">Potongan nominal</option>
                            <option value="free_shipping">Gratis ongkir</option>
                        </select>
                    </div>

                    @if ($type !== 'free_shipping')
                        <div>
                            <label for="value" class="part-no">
                                {{ $type === 'percent' ? 'Persen (%)' : 'Nominal (Rp)' }} <span class="text-danger">*</span>
                            </label>
                            <input id="value" type="text" inputmode="numeric" wire:model.live.debounce.400ms="value"
                                   class="field tech mt-1" aria-invalid="{{ $errors->has('value') ? 'true' : 'false' }}">
                            @error('value') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if ($type === 'percent')
                        <div>
                            <label for="max_discount" class="part-no">Batas potongan (Rp)</label>
                            <input id="max_discount" type="text" inputmode="numeric"
                                   wire:model.live.debounce.400ms="max_discount" class="field tech mt-1">
                            <p class="mt-1 text-xs text-dim">
                                Persentase tanpa batas dapat memotong seluruh nilai belanja. Isi batasnya.
                            </p>
                            @error('max_discount') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label for="min_spend" class="part-no">Minimum belanja (Rp)</label>
                        <input id="min_spend" type="text" inputmode="numeric" wire:model="min_spend" class="field tech mt-1">
                        <p class="mt-1 text-xs text-dim">Kosongkan bila tidak ada syarat minimum.</p>
                        @error('min_spend') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="quota" class="part-no">Kuota pemakaian</label>
                        <input id="quota" type="text" inputmode="numeric" wire:model="quota" class="field tech mt-1">
                        <p class="mt-1 text-xs text-dim">Kosongkan bila tanpa batas.</p>
                        @error('quota') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="starts_at" class="part-no">Mulai</label>
                            <input id="starts_at" type="datetime-local" wire:model="starts_at" class="field mt-1 text-xs">
                            @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ends_at" class="part-no">Berakhir</label>
                            <input id="ends_at" type="datetime-local" wire:model="ends_at" class="field mt-1 text-xs">
                            @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="is_active">
                            <span></span>
                        </span>
                        <span class="text-sm">Aktif</span>
                    </label>

                    {{-- Pratinjau memakai perhitungan yang sama dengan checkout. --}}
                    <div class="rounded-lg bg-sunken p-3 text-sm">
                        <p class="part-no">Pratinjau</p>
                        <p class="mt-1 text-muted">
                            Belanja {{ rupiah($pratinjau['belanja']) }} + ongkir {{ rupiah($pratinjau['ongkir']) }}
                        </p>
                        <p class="tech mt-1">
                            Potongan: <span class="text-accent">{{ rupiah($pratinjau['potongan']) }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpan">{{ $suntingId ? 'Simpan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="simpan">Menyimpan…</span>
                        </button>
                        <button type="button" wire:click="tutupForm" class="btn btn-outline">Batal</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
