<div class="space-y-5">

    {{-- ── Pesan ───────────────────────────────────────────────────── --}}
    @if ($pesanSukses)
        <div class="plate flex items-start gap-3 border-l-2 border-ok p-3 text-sm" role="status">
            <x-ikon name="check" :size="18" class="mt-0.5 flex-none text-ok" />
            <p class="flex-1">{{ $pesanSukses }}</p>
        </div>
    @endif

    @if ($galat)
        <div class="plate flex items-start gap-3 border-l-2 border-danger p-3 text-sm" role="alert">
            <x-ikon name="alert" :size="18" class="mt-0.5 flex-none text-danger" />
            <p class="flex-1">{{ $galat }}</p>
        </div>
    @endif

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Beranda · hero</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Hero Beranda</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $slides->count() }} dari maksimum {{ $maksSlide }} slide ·
                {{ $slides->where('is_active', true)->count() }} tayang
            </p>
        </div>

        <button type="button" class="btn btn-primary" wire:click="tambah"
                @disabled($slides->count() >= $maksSlide)>
            <x-ikon name="plus" :size="17" />
            Tambah slide
        </button>
    </div>

    {{-- ── Daftar slide ────────────────────────────────────────────

         Grid kartu, bukan tumpukan baris.

         Versi lama adalah daftar satu kolom berisi baris tipis: thumbnail
         80x56 di kiri, judul di tengah, lalu LIMA tombol ikon 44px berjejer di
         bawah. Tiga akibat yang membuat halaman ini tidak nyaman:

           1. Grid dua kolom sisa panel sunting yang sudah pindah ke halaman
              sendiri, sehingga daftar hanya memakai dua pertiga lebar dan
              menyisakan empat ratus piksel kosong di kanan.
           2. Thumbnail 80x56 terlalu kecil untuk menilai gambar hero — yang
              justru satu-satunya hal yang perlu diperiksa di sini.
           3. Lima tombol 44px menuntut 220px lebar; di ponsel itu memaksa
              judul terpotong sampai tersisa beberapa huruf.

         Sekarang: satu kolom penuh, pratinjau 16:9 yang cukup besar untuk
         dilihat, keterangan ringkas di bawahnya, dan tombol dikumpulkan pada
         satu bilah yang ukurannya turun menjadi 36px — masih di atas ambang
         sentuh nyaman, tetapi tidak lagi mendesak judul.

         Urutan slide tetap bisa diubah dengan seret-lepas ATAU tombol naik/
         turun. Keduanya dipertahankan: seret-lepas tidak bekerja dengan
         pembaca layar maupun papan tunjuk, dan tombol itu satu-satunya jalan
         bagi keduanya. --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
         x-data="heroUrutan($wire, {{ json_encode($slides->pluck('id')->all()) }})">

        @forelse ($slides as $i => $s)
            @php
                $url      = filled($s->media_path) ? \App\Models\ProductMedia::urlMedia($s->media_path) : null;
                $urlHp    = filled($s->media_path_mobile) ? \App\Models\ProductMedia::urlMedia($s->media_path_mobile) : null;
                $berjadwal = $s->starts_at || $s->ends_at;
            @endphp

            <article class="group plate relative flex flex-col overflow-hidden"
                     wire:key="slide-{{ $s->id }}"
                     draggable="true"
                     @dragstart="menyeret = {{ $i }}"
                     @dragover.prevent="atas = {{ $i }}"
                     @drop.prevent="taruh({{ $i }})"
                     @dragend="menyeret = null; atas = null"
                     :class="atas === {{ $i }} && menyeret !== null ? 'ring-2 ring-accent' : ''">

                {{-- Pratinjau 16:9 — ukuran yang cukup untuk menilai gambar. --}}
                <a href="{{ route('admin.hero.edit', $s->ulid) }}" wire:navigate
                   class="relative block aspect-video overflow-hidden bg-sunken"
                   aria-label="Sunting slide {{ $s->title_id ?: 'tanpa judul' }}">

                    @if ($url && $s->media_type === 'image')
                        <img src="{{ $url }}" alt=""
                             class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                             style="object-position: {{ $s->posisiFokus() }}"
                             loading="lazy" decoding="async" />
                    @elseif ($s->media_type === 'video')
                        <div class="flex h-full w-full items-center justify-center gap-2 text-dim">
                            <x-ikon name="activity" :size="26" />
                            <span class="tech text-xs">video</span>
                        </div>
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-dim">
                            <x-ikon name="image" :size="26" />
                            <span class="text-xs">belum ada berkas</span>
                        </div>
                    @endif

                    {{-- Lapisan gradasi: judul di atas gambar harus terbaca
                         pada gambar terang sekalipun, sama seperti di beranda. --}}
                    @if ($url && $s->media_type === 'image')
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                    @endif

                    {{-- Lencana status di atas gambar: yang paling sering
                         dicari mata saat membuka halaman ini. --}}
                    <div class="pointer-events-none absolute inset-x-2.5 top-2.5 flex items-start justify-between gap-2">
                        <span class="badge {{ $s->is_active ? 'badge-ok' : 'badge-neutral' }} bg-surface/90 backdrop-blur">
                            {{ $s->is_active ? 'tayang' : 'draf' }}
                        </span>

                        <div class="flex flex-col items-end gap-1">
                            <span class="badge badge-neutral bg-surface/90 backdrop-blur">
                                urutan {{ $s->display_order + 1 }}
                            </span>

                            @if ($berjadwal)
                                <span class="badge badge-steel bg-surface/90 backdrop-blur">jadwal</span>
                            @endif
                        </div>
                    </div>

                    {{-- Pegangan seret, di sudut yang tidak bertabrakan dengan
                         lencana. --}}
                    <span class="absolute bottom-2.5 right-2.5 flex h-7 w-7 cursor-grab items-center justify-center
                                 rounded-[var(--r-kecil)] bg-surface/85 text-muted backdrop-blur"
                          title="Seret untuk mengubah urutan">
                        <x-ikon name="menu" :size="15" />
                    </span>

                    {{-- Judul ditumpuk di atas gambar: menghemat tinggi kartu dan
                         memperlihatkan potongan hero seperti yang dilihat
                         pengunjung. --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 p-3">
                        <p class="line-clamp-1 text-sm font-semibold text-white drop-shadow">
                            {{ $s->title_id ?: '(tanpa judul)' }}
                        </p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-white/70">
                            {{ $s->title_en ?: '(no title)' }}
                        </p>
                    </div>
                </a>

                {{-- Keterangan ringkas + tindakan --}}
                <div class="flex flex-1 flex-col gap-2 p-3">

                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                        <span class="tech">{{ $s->media_type === 'video' ? 'video' : 'gambar' }}</span>

                        @if ($urlHp)
                            <span class="text-dim">·</span>
                            <span title="Punya gambar khusus ponsel">ponsel terpisah</span>
                        @endif

                        @if ($s->starts_at || $s->ends_at)
                            <span class="text-dim">·</span>
                            <span class="truncate">
                                @if ($s->starts_at && $s->ends_at)
                                    {{ $s->starts_at->format('d M') }} – {{ $s->ends_at->format('d M Y') }}
                                @elseif ($s->starts_at)
                                    mulai {{ $s->starts_at->format('d M Y') }}
                                @else
                                    sampai {{ $s->ends_at->format('d M Y') }}
                                @endif
                            </span>
                        @endif
                    </div>

                    @if (blank($s->media_path))
                        <p class="text-xs text-warn">
                            Belum bisa ditayangkan — unggah gambar atau video dulu.
                        </p>
                    @endif

                    {{-- Bilah tindakan.

                         Ukuran 36px (btn-sm .btn-icon !h-9 !w-9), bukan 44px:
                         lima tombol 44px menuntut 220px dan mendesak judul.
                         36px masih di atas ambang sentuh yang nyaman untuk
                         tindakan sekunder.

                         mt-auto menempelkan bilah ini ke dasar kartu, sehingga
                         pada grid yang kartunya berbeda tinggi, tombolnya tetap
                         sejajar. --}}
                    <div class="mt-auto flex items-center gap-1 border-t border-line-soft pt-2">

                        <button type="button" class="btn-icon !h-9 !w-9" wire:click="naik({{ $s->id }})"
                                @disabled($i === 0) title="Naikkan urutan">
                            <x-ikon name="chevron-down" :size="15" class="rotate-180" />
                            <span class="visually-hidden">Naikkan</span>
                        </button>

                        <button type="button" class="btn-icon !h-9 !w-9" wire:click="turun({{ $s->id }})"
                                @disabled($i === $slides->count() - 1) title="Turunkan urutan">
                            <x-ikon name="chevron-down" :size="15" />
                            <span class="visually-hidden">Turunkan</span>
                        </button>

                        <button type="button" class="btn-icon !h-9 !w-9" wire:click="ubahAktif({{ $s->id }})"
                                title="{{ $s->is_active ? 'Nonaktifkan' : 'Tayangkan' }}"
                                aria-label="{{ $s->is_active ? 'Nonaktifkan slide' : 'Tayangkan slide' }}">
                            {{-- Ikon BERBEDA untuk dua keadaan. Versi lama memakai
                                 'eye' untuk keduanya, sehingga status tidak dapat
                                 dibaca dari ikonnya sama sekali. --}}
                            <x-ikon :name="$s->is_active ? 'eye' : 'eye-off'" :size="16"
                                    class="{{ $s->is_active ? 'text-ok' : '' }}" />
                            <span class="visually-hidden">
                                {{ $s->is_active ? 'Nonaktifkan' : 'Tayangkan' }}
                            </span>
                        </button>

                        <div class="ml-auto flex items-center gap-1">
                            <a href="{{ route('admin.hero.edit', $s->ulid) }}" wire:navigate
                               class="btn btn-outline btn-sm" title="Sunting slide">
                                <x-ikon name="sliders" :size="14" />
                                <span class="hidden sm:inline">Sunting</span>
                                <span class="sr-only sm:hidden">Sunting</span>
                            </a>

                            <button type="button" class="btn-icon !h-9 !w-9 text-danger"
                                    wire:click="hapus({{ $s->id }})"
                                    wire:confirm="Hapus slide &quot;{{ $s->title_id }}&quot; beserta berkasnya?"
                                    title="Hapus slide" aria-label="Hapus slide">
                                <x-ikon name="trash" :size="15" />
                                <span class="visually-hidden">Hapus</span>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="plate col-span-full p-10 text-center">
                <x-ikon name="image" :size="30" class="mx-auto text-dim" />
                <p class="mt-3 font-semibold">Belum ada slide.</p>
                <p class="mt-1 text-sm text-muted">
                    Hero halaman depan memakai tampilan bawaan sampai slide pertama ditambahkan.
                </p>
                <button type="button" class="btn btn-primary mt-4" wire:click="tambah">
                    <x-ikon name="plus" :size="16" />
                    Tambah slide pertama
                </button>
            </div>
        @endforelse
    </div>

    @if ($slides->isNotEmpty())
        <p class="text-xs text-dim">
            Urutan di sini menentukan urutan tayang di beranda. Slide pertama yang aktif tampil paling awal;
            seret kartu atau pakai tombol panah untuk menggeser.
        </p>
    @endif
</div>
