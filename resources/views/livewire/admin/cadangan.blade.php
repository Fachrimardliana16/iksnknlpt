{{-- Cadangan basis data.

     Dua hal dinyatakan terus terang di halaman ini:

     1. Cadangan yang tidak sah ditandai "tidak dapat dipulihkan", bukan
        disembunyikan. Salinan rusak yang tampak sehat lebih berbahaya
        daripada tidak ada salinan — baru ketahuan saat dibutuhkan.
     2. Penjadwal hanya berjalan bila ada cron atau schedule:work yang
        menyala. Kalau tidak ada, backup harian 02.00 tidak pernah terjadi,
        dan tombol di halaman ini adalah satu-satunya jalur. --}}

<div class="space-y-5">

    <div>
        <p class="part-no">Situs · cadangan</p>
        <h1 class="mt-1 font-display text-2xl font-semibold">Cadangan Basis Data</h1>
        <p class="mt-1 text-sm text-muted">
            Salinan penuh berkas basis data. Menyimpan {{ $simpan }} salinan terbaru, dibuat otomatis setiap hari pukul 02.00.
        </p>
    </div>

    @if ($pesan)
        <div class="plate flex items-start gap-3 border-l-2 border-l-ok p-3 text-sm" role="status">
            <x-ikon name="check" :size="18" class="mt-0.5 flex-none text-ok" />
            <p class="flex-1">{{ $pesan }}</p>
        </div>
    @endif

    @if ($galat)
        <div class="plate flex items-start gap-3 border-l-2 border-l-danger p-3 text-sm" role="alert">
            <x-ikon name="alert" :size="18" class="mt-0.5 flex-none text-danger" />
            <p class="flex-1">{{ $galat }}</p>
        </div>
    @endif

    {{-- ── Angka ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @php
            $sah = collect($cadangan)->where('sah', true)->count();
            $terbaru = $cadangan[0] ?? null;
        @endphp

        <div class="plate p-4">
            <div class="flex items-center justify-between">
                <p class="part-no">Salinan sah</p>
                <x-ikon name="check" :size="17" class="text-dim" />
            </div>
            <p class="tech mt-3 text-xl font-medium lg:text-2xl">{{ $sah }}</p>
        </div>

        <div class="plate p-4">
            <div class="flex items-center justify-between">
                <p class="part-no">Cadangan terbaru</p>
                <x-ikon name="clock" :size="17" class="text-dim" />
            </div>
            <p class="tech mt-3 text-base font-medium lg:text-lg">
                @if ($terbaru)
                    {{ \Illuminate\Support\Carbon::createFromTimestamp($terbaru['waktu'])->diffForHumans() }}
                @else
                    belum ada
                @endif
            </p>
        </div>

        <div class="plate p-4">
            <div class="flex items-center justify-between">
                <p class="part-no">Ukuran basis data</p>
                <x-ikon name="box" :size="17" class="text-dim" />
            </div>
            <p class="tech mt-3 text-base font-medium lg:text-lg">
                {{ $ukuranDb > 0 ? number_format($ukuranDb / 1024 / 1024, 2) : '0,00' }} MB
            </p>
        </div>

        <div class="plate p-4">
            <div class="flex items-center justify-between">
                <p class="part-no">Ruang disk tersisa</p>
                <x-ikon name="activity" :size="17" class="text-dim" />
            </div>
            <p class="tech mt-3 text-base font-medium lg:text-lg">{{ number_format($sisaDiskMb, 0, ',', '.') }} MB</p>
        </div>
    </div>

    {{-- ── Tindakan ──────────────────────────────────────────────── --}}
    <div class="plate p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium">Buat cadangan sekarang</p>
                <p class="mt-0.5 text-sm text-muted">
                    Salinan penuh berkas basis data saat ini. Beberapa detik sampai satu menit, tergantung ukuran.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label for="f-simpan" class="text-sm text-muted">Simpan</label>
                <input id="f-simpan" type="number" min="1" max="60" wire:model="simpan" class="field tech w-20" />

                <button type="button" class="btn btn-primary" wire:click="buat"
                        wire:loading.attr="disabled" wire:target="buat">
                    <x-ikon name="download" :size="17" />
                    <span wire:loading.remove wire:target="buat">Cadangkan sekarang</span>
                    <span wire:loading wire:target="buat">Menyimpan…</span>
                </button>

                <button type="button" class="btn btn-outline" wire:click="segarkan"
                        wire:loading.attr="disabled" wire:target="segarkan">
                    <x-ikon name="refresh" :size="17" />
                    <span wire:loading.remove wire:target="segarkan">Muat ulang</span>
                    <span wire:loading wire:target="segarkan">Memuat…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Peringatan penjadwal ──────────────────────────────────── --}}
    <div class="plate border-l-2 border-l-warn p-4 text-sm">
        <p class="font-medium">Cadangan harian 02.00 hanya berjalan bila penjadwal menyala</p>
        <p class="mt-1 text-muted">
            Laravel tidak menjalankan tugas terjadwal sendiri. Dibutuhkan satu entri cron di server
            (<code class="tech">* * * * * php artisan schedule:run</code>) atau proses
            <code class="tech">php artisan schedule:work</code> yang berjalan terus.
            Kalau tidak ada, yang berlaku hanya tombol di atas — cadangan otomatis tidak pernah terjadi.
        </p>
    </div>

    {{-- ── Daftar cadangan ───────────────────────────────────────── --}}
    <div class="plate">
        <div class="border-b border-line-soft p-4">
            <p class="font-medium">Salinan di server</p>
            <p class="mt-0.5 text-sm text-muted tech">{{ $folder }}</p>
        </div>

        @if ($cadangan === [])
            <div class="p-6 text-center text-sm text-muted">
                Belum ada cadangan. Tekan <span class="text-ink">Cadangkan sekarang</span> untuk membuat yang pertama.
            </div>
        @else
            <div class="divide-y divide-line-soft">
                @foreach ($cadangan as $c)
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="tech truncate text-sm">{{ $c['nama'] }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($c['waktu'])->format('d M Y, H:i:s') }}
                                · {{ number_format($c['ukuran'] / 1024 / 1024, 2) }} MB
                            </p>
                        </div>

                        @if ($c['sah'])
                            <span class="badge badge-ok flex-none">dapat dipulihkan</span>
                        @else
                            <span class="badge badge-neutral flex-none">tidak dapat dipulihkan</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
