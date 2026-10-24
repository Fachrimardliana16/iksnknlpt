<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · pesanan</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Pesanan</h1>
            <p class="mt-1 text-sm text-muted">
                Dari menunggu bayar sampai dikirim. Cetak resi dari halaman ini.
            </p>
        </div>

        <a href="{{ route('admin.exports.orders') }}" class="btn btn-outline">
            <x-ikon name="download" :size="16" />
            Report Penjualan
        </a>
    </div>

    @if (session('pesan-admin'))
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ session('pesan-admin') }}</div>
    @endif

    @if (session('galat-admin'))
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ session('galat-admin') }}</div>
    @endif

    {{-- ── Angka kerja hari ini ────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Perlu dikirim', 'nilai' => number_format($ringkas['perluDikirim'], 0, ',', '.'), 'ikon' => 'box', 'rute' => route('admin.orders.index', ['status' => 'paid'])],
            ['label' => 'Belum dibayar', 'nilai' => number_format($ringkas['belumBayar'], 0, ',', '.'), 'ikon' => 'clock', 'rute' => route('admin.orders.index', ['status' => 'pending'])],
            ['label' => 'Uang belum masuk', 'nilai' => rupiah($ringkas['nilaiBelumMasuk']), 'ikon' => 'chart', 'rute' => route('admin.orders.index', ['bayar' => 'unpaid'])],
            ['label' => 'Selesai bulan ini', 'nilai' => number_format($ringkas['selesaiBulanIni'], 0, ',', '.'), 'ikon' => 'check', 'rute' => route('admin.orders.index', ['status' => 'completed'])],
        ] as $k)
            <a href="{{ $k['rute'] }}" class="plate plate-interaktif p-4">
                <div class="flex items-center justify-between">
                    <p class="part-no">{{ $k['label'] }}</p>
                    <x-ikon :name="$k['ikon']" :size="17" class="text-dim" />
                </div>
                <p class="tech mt-3 text-xl font-medium lg:text-2xl">{{ $k['nilai'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- ── Saringan status ─────────────────────────────────────────── --}}
    <div class="-mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
        <div class="flex min-w-max gap-1 border-b border-line-soft">
            @php
                $tabStatus = ['semua' => 'Semua'] + collect(\App\Enums\StatusPesanan::cases())
                    ->mapWithKeys(fn ($s) => [$s->value => $s->label()])
                    ->all();
            @endphp

            @foreach ($tabStatus as $nilai => $label)
                @php $jumlah = $nilai === 'semua' ? $ringkas['perStatus']->sum() : ($ringkas['perStatus'][$nilai] ?? 0); @endphp
                <button type="button" wire:click="aturStatus('{{ $nilai }}')"
                        class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-sm whitespace-nowrap
                               {{ $status === $nilai ? 'border-accent font-medium text-accent' : 'border-transparent text-muted hover:text-ink' }}">
                    {{ $label }}
                    @if ($jumlah > 0)
                        <span class="tech text-xs {{ $status === $nilai ? 'text-accent' : 'text-dim' }}">{{ $jumlah }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ── Saringan tambahan ─────────────────────────────────────────

         Satu baris, tiga hal: cari, cara bayar, urutan. Dulu dua <select>
         memakan lebar penuh sebelum pencarian — padahal pencarian yang paling
         sering dipakai. Sekarang input yang paling lebar, dan "semua
         pembayaran" serta "terbaru" jadi tombol chip yang menampilkan pilihan
         sedang aktif; pilihan lain dibuka dari dropdown Alpine kecil.

         Alpine sengaja dipakai HANYA untuk membuka panel pilihan. Nilai yang
         terpilih tetap dibaca dari properti Livewire ($bayar, $urut), bukan
         dari keadaan Alpine — supaya menyaring tetap bekerja walau Alpine
         gagal dimuat. --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3" x-data="{bukaBayar: false, bukaUrut: false}">
        <label class="flex min-w-[12rem] flex-1 items-center gap-2 lg:max-w-md">
            <x-ikon name="search" :size="17" class="flex-none text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field"
                   placeholder="Cari nomor pesanan, nama, atau email" />
        </label>

        {{-- Chip cara bayar --}}
        <div class="relative">
            <button type="button" @click="bukaBayar = !bukaBayar; bukaUrut = false"
                    class="btn btn-outline" aria-haspopup="true" :aria-expanded="bukaBayar">
                <x-ikon name="card" :size="16" />
                {{ \App\Enums\StatusPembayaran::tryFrom($bayar)?->label() ?? 'Semua pembayaran' }}
                <x-ikon name="chevron-down" :size="14" class="text-dim" />
            </button>

            <div x-show="bukaBayar" x-cloak @click.outside="bukaBayar = false"
                 class="plate absolute right-0 z-20 mt-1 w-52 overflow-hidden p-1 shadow-lg">
                <button type="button" wire:click="$set('bayar', 'semua')" @click="bukaBayar = false"
                        class="flex w-full items-center justify-between gap-2 rounded-[var(--r-kecil)] px-3 py-2 text-left text-sm hover:bg-sunken">
                    Semua pembayaran
                    @if ($bayar === 'semua') <x-ikon name="check" :size="15" class="text-accent" /> @endif
                </button>
                @foreach (\App\Enums\StatusPembayaran::cases() as $s)
                    <button type="button" wire:click="$set('bayar', '{{ $s->value }}')" @click="bukaBayar = false"
                            class="flex w-full items-center justify-between gap-2 rounded-[var(--r-kecil)] px-3 py-2 text-left text-sm hover:bg-sunken">
                        {{ $s->label() }}
                        @if ($bayar === $s->value) <x-ikon name="check" :size="15" class="text-accent" /> @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Chip urutan --}}
        <div class="relative">
            <button type="button" @click="bukaUrut = !bukaUrut; bukaBayar = false"
                    class="btn btn-outline" aria-haspopup="true" :aria-expanded="bukaUrut">
                <x-ikon name="sliders" :size="16" />
                {{ match ($urut) {
                    'terlama' => 'Terlama',
                    'terbesar' => 'Nilai terbesar',
                    'kedaluwarsa' => 'Hampir batal',
                    default => 'Terbaru',
                } }}
                <x-ikon name="chevron-down" :size="14" class="text-dim" />
            </button>

            <div x-show="bukaUrut" x-cloak @click.outside="bukaUrut = false"
                 class="plate absolute right-0 z-20 mt-1 w-52 overflow-hidden p-1 shadow-lg">
                @foreach (['terbaru' => 'Terbaru', 'terlama' => 'Terlama', 'terbesar' => 'Nilai terbesar', 'kedaluwarsa' => 'Hampir batal'] as $nilai => $label)
                    <button type="button" wire:click="$set('urut', '{{ $nilai }}')" @click="bukaUrut = false"
                            class="flex w-full items-center justify-between gap-2 rounded-[var(--r-kecil)] px-3 py-2 text-left text-sm hover:bg-sunken">
                        {{ $label }}
                        @if ($urut === $nilai) <x-ikon name="check" :size="15" class="text-accent" /> @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Bilah cetak banyak ────────────────────────────────────────
         Tombol ini hidup dari peramban, bukan dari komponen Livewire: yang
         diperlukan cuma membaca kotak centang yang sudah dicentang lalu
         membangun alamatnya. Membuatnya milik Livewire berarti setiap centang
         memicu permintaan ke server untuk sesuatu yang belum perlu.

         Alpine TIDAK dipakai di sini dengan sengaja. Blade admin memuat
         Alpine hanya untuk drawer, dan menggantungkan fungsinya padanya
         berarti tombol ini mati bila penarikan Alpine gagal — sedangkan
         mencetak resi dua puluh paket bukan pekerjaan yang boleh gagal
         karena peramban. --}}
    <div id="bilah-cetak" class="plate flex flex-wrap items-center justify-between gap-3 p-3" hidden>
        <p class="text-sm text-muted">
            Centang pesanan di bawah, lalu cetak semua label sekaligus dalam satu berkas PDF.
        </p>

        <div class="flex items-center gap-2">
            <span id="jumlah-terpilih" class="tech text-sm text-dim">0 dipilih</span>

            <button type="button" id="tombol-cetak-banyak" class="btn btn-primary" disabled>
                <x-ikon name="printer" :size="16" />
                Cetak resi terpilih
            </button>
        </div>
    </div>

    {{-- ── Daftar pesanan ──────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse ($pesanan as $o)
            @php $sisa = $this->sisaJam($o); @endphp

            <article class="plate plate-interaktif relative p-4" wire:key="pesanan-{{ $o->id }}">

                {{-- Lapisan tautan: seluruh kartu membuka detail. --}}
                <a href="{{ route('admin.orders.show', $o->ulid) }}" wire:navigate
                   class="absolute inset-0 z-0" aria-label="Buka pesanan {{ $o->order_number }}"></a>

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="tech font-semibold">{{ $o->order_number }}</p>

                            <span class="badge badge-{{ $o->status->nada() }}">{{ $o->status->label() }}</span>

                            @if ($o->payment_status === \App\Enums\StatusPembayaran::Paid)
                                <span class="badge badge-ok">lunas</span>
                            @else
                                <span class="badge badge-warn">{{ $o->payment_status->label() }}</span>
                            @endif

                            @if ($sisa !== null)
                                <span class="badge {{ $sisa <= 6 ? 'badge-danger' : 'badge-neutral' }}">
                                    {{ $sisa <= 0 ? 'lewat batas' : $sisa.' jam lagi' }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-1.5 truncate text-sm font-medium">{{ $this->pembeli($o) }}</p>
                        <p class="truncate text-xs text-muted">
                            {{ $this->kontak($o) ?? 'tanpa kontak' }}
                            · {{ $o->jumlahItem() }} barang
                            · {{ $o->created_at->format('d M Y H:i') }}
                        </p>
                    </div>

                    <div class="flex-none text-right">
                        <p class="tech text-lg font-medium">{{ rupiah($o->grand_total) }}</p>
                        <p class="part-no">{{ $o->payment_method?->label() ?? 'belum dipilih' }}</p>
                    </div>
                </div>

                {{-- Barang ringkas --}}
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-line-soft pt-2.5 text-xs text-muted">
                    @foreach ($o->items->take(3) as $item)
                        <li class="truncate">
                            {{ $item->quantity }}× {{ $item->product_name }}
                            @if ($item->variant_name) ({{ $item->variant_name }}) @endif
                        </li>
                    @endforeach

                    @if ($o->items->count() > 3)
                        <li class="text-dim">+{{ $o->items->count() - 3 }} lainnya</li>
                    @endif
                </ul>

                {{-- Tindakan cepat, di atas lapisan tautan --}}
                <div class="relative z-10 mt-3 flex flex-wrap items-center gap-2 border-t border-line-soft pt-3">

                    {{-- Kotak centang untuk cetak resi banyak.
                         Tanpa wire:model: pilihannya milik peramban, bukan state
                         Livewire, sehingga tidak memicu permintaan ke server tiap
                         kali dicentang — dan yang penting, tidak hilang saat daftar
                         disegarkan. --}}
                    <label class="flex items-center gap-1.5 text-xs text-muted"
                           title="Pilih untuk dicetak bersama yang lain">
                        <input type="checkbox" class="h-4 w-4 accent-[var(--c-accent)]"
                               data-cetak-resi value="{{ $o->ulid }}">
                        <span>Pilih cetak</span>
                    </label>
                    @if ($o->status === \App\Enums\StatusPesanan::Pending)
                        <button type="button" class="btn btn-primary"
                                wire:click="tandaiDibayar({{ $o->id }})"
                                wire:loading.attr="disabled" wire:target="tandaiDibayar({{ $o->id }})">
                            <x-ikon name="check" :size="16" />
                            Tandai lunas
                        </button>
                    @endif

                    <a href="{{ route('admin.orders.show', $o->ulid) }}" wire:navigate class="btn btn-outline">
                        <x-ikon name="eye" :size="16" />
                        Detail
                    </a>

                    <a href="{{ route('admin.orders.receipt', $o->ulid) }}" target="_blank" rel="noopener"
                       class="btn btn-outline">
                        <x-ikon name="printer" :size="16" />
                        Cetak resi
                    </a>
                </div>
            </article>
        @empty
            <div class="plate p-10 text-center">
                <x-ikon name="receipt" :size="30" class="mx-auto text-dim" />
                <p class="mt-3 font-semibold">Tidak ada pesanan pada saringan ini.</p>
                <p class="mt-1 text-sm text-muted">Coba ubah status atau kosongkan kata kunci pencarian.</p>
            </div>
        @endforelse
    </div>

    @if ($pesanan->hasPages())
        <div>{{ $pesanan->links() }}</div>
    @endif
</div>
