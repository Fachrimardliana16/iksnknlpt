{{-- Pesan dari halaman kontak.

     Panel kiri daftar, panel kanan isi pesan. Isi pesan hanya dapat dibaca
     satu per satu dan tidak pernah dimuat sekaligus — kolomnya terenkripsi,
     jadi membacanya berarti mendekripsi, dan itu tidak perlu dilakukan untuk
     pesan yang tidak sedang dibuka. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · pesan masuk</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Pesan Kontak</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} pesan
                @if ($jumlah['belum'] > 0)
                    · <span class="text-warn">{{ number_format($jumlah['belum'], 0, ',', '.') }} belum dibaca</span>
                @endif
            </p>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- Saringan --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[200px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field pl-9"
                   placeholder="Cari subjek atau surel…" aria-label="Cari pesan">
        </div>

        <button type="button" wire:click="aturBaca('semua')"
                class="btn {{ $baca === 'semua' ? 'btn-primary' : 'btn-outline' }} btn-sm">Semua</button>
        <button type="button" wire:click="aturBaca('belum')"
                class="btn {{ $baca === 'belum' ? 'btn-primary' : 'btn-outline' }} btn-sm">
            Belum dibaca
            @if ($jumlah['belum'] > 0) <span class="tech">{{ $jumlah['belum'] }}</span> @endif
        </button>
        <button type="button" wire:click="aturBaca('sudah')"
                class="btn {{ $baca === 'sudah' ? 'btn-primary' : 'btn-outline' }} btn-sm">Sudah dibaca</button>

        @if (count($terpilih) > 0)
            <span class="tech text-xs text-accent">{{ count($terpilih) }} terpilih</span>
            <button type="button" wire:click="tandaiDibacaMassal" class="btn btn-outline btn-sm">Tandai dibaca</button>
            <button type="button" wire:click="hapusMassal"
                    wire:confirm="Hapus {{ count($terpilih) }} pesan terpilih?"
                    class="btn btn-danger btn-sm">Hapus</button>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_1.1fr]">

        {{-- Daftar --}}
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="mail" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">
                        {{ $cari !== '' || $baca !== 'semua' ? 'Tidak ada pesan yang cocok.' : 'Belum ada pesan masuk.' }}
                    </p>
                </div>
            @else
                <ul>
                    @foreach ($daftar as $m)
                        <li wire:key="pesan-{{ $m->id }}"
                            class="border-b border-line-soft last:border-0">
                            <div class="flex items-start gap-3 p-3 hover:bg-sunken {{ $dipilih === $m->id ? 'bg-sunken' : '' }}">
                                <input type="checkbox" value="{{ $m->id }}" wire:model.live="terpilih"
                                       class="mt-1 h-4 w-4" aria-label="Pilih pesan dari {{ $m->name }}">

                                <button type="button" wire:click="buka({{ $m->id }})" class="min-w-0 flex-1 text-left">
                                    <div class="flex items-center gap-2">
                                        @unless ($m->is_read)
                                            <span class="h-2 w-2 flex-none rounded-full bg-accent" title="Belum dibaca"></span>
                                        @endunless
                                        <p class="truncate text-sm {{ $m->is_read ? 'text-muted' : 'font-medium' }}">
                                            {{ $m->name }}
                                        </p>
                                        @if ($m->replied_at)
                                            <span class="rounded-full bg-ok/12 px-1.5 py-0.5 text-xs text-ok">dibalas</span>
                                        @endif
                                    </div>

                                    <p class="mt-0.5 truncate text-sm">{{ $m->subject ?: '(tanpa subjek)' }}</p>

                                    <p class="tech mt-1 text-xs text-dim">
                                        {{ $m->created_at?->diffForHumans() }}
                                        @if ($m->phone) · {{ $m->phone }} @endif
                                    </p>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($daftar->hasPages())
                    <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
                @endif
            @endif
        </div>

        {{-- Rincian --}}
        <div class="plate h-fit p-4 lg:sticky lg:top-20">
            @if ($rinci === null)
                <div class="py-10 text-center">
                    <x-ikon name="mail" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">Pilih satu pesan untuk membacanya.</p>
                </div>
            @else
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="part-no">Pesan #{{ $rinci->id }}</p>
                        <h2 class="mt-1 font-display text-lg font-semibold">
                            {{ $rinci->subject ?: '(tanpa subjek)' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutup" class="btn-icon h-8 w-8" title="Tutup">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Tutup</span>
                    </button>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-3">
                        <dt class="part-no w-20 flex-none pt-0.5">Nama</dt>
                        <dd>{{ $rinci->name }}</dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="part-no w-20 flex-none pt-0.5">Surel</dt>
                        <dd class="min-w-0 break-all">
                            <a href="mailto:{{ $rinci->email }}" class="text-accent hover:underline">{{ $rinci->email }}</a>
                        </dd>
                    </div>
                    @if ($rinci->phone)
                        <div class="flex gap-3">
                            <dt class="part-no w-20 flex-none pt-0.5">Telepon</dt>
                            <dd>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $rinci->phone) }}"
                                   target="_blank" rel="noopener" class="text-accent hover:underline">
                                    {{ $rinci->phone }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex gap-3">
                        <dt class="part-no w-20 flex-none pt-0.5">Waktu</dt>
                        <dd class="text-muted">{{ $rinci->created_at?->translatedFormat('d F Y, H:i') }}</dd>
                    </div>
                    @if ($rinci->replied_at)
                        <div class="flex gap-3">
                            <dt class="part-no w-20 flex-none pt-0.5">Dibalas</dt>
                            <dd class="text-muted">
                                {{ $rinci->replied_at->translatedFormat('d F Y, H:i') }}
                                @if ($rinci->pembalas) · {{ $rinci->pembalas->name }} @endif
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 border-t border-line-soft pt-4">
                    <p class="part-no">Isi pesan</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $rinci->message }}</p>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <a href="mailto:{{ $rinci->email }}?subject={{ rawurlencode('Re: '.($rinci->subject ?: 'Pesan Anda')) }}"
                       class="btn btn-primary btn-sm">
                        <x-ikon name="mail" :size="14" />
                        Balas lewat surel
                    </a>

                    @if ($rinci->phone)
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $rinci->phone) }}"
                           target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                            <x-ikon name="whatsapp" :size="14" />
                            WhatsApp
                        </a>
                    @endif

                    <button type="button" wire:click="tandaiBalas({{ $rinci->id }})" class="btn btn-outline btn-sm">
                        {{ $rinci->replied_at ? 'Lepas tanda dibalas' : 'Tandai sudah dibalas' }}
                    </button>

                    <button type="button" wire:click="tandaiDibaca({{ $rinci->id }})" class="btn btn-ghost btn-sm">
                        {{ $rinci->is_read ? 'Tandai belum dibaca' : 'Tandai sudah dibaca' }}
                    </button>

                    <button type="button" wire:click="hapus({{ $rinci->id }})"
                            wire:confirm="Hapus pesan dari {{ $rinci->name }}?"
                            class="btn btn-ghost btn-sm ml-auto hover:text-danger">
                        <x-ikon name="trash" :size="14" />
                        Hapus
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
