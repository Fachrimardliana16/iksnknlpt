{{-- Daftar produk (F3-02).

     Aksi massal memakai bilah yang muncul saat ada yang terpilih. Bilah itu
     menempel di bawah layar, sehingga tindakannya selalu terjangkau tanpa
     menggulir kembali ke atas setelah memilih produk di baris terakhir. --}}

<div class="space-y-5" x-data="{ bilah: false }"
     x-init="$watch('$wire.terpilih', v => bilah = v.length > 0)">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Katalog · produk</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Produk</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} produk ·
                {{ number_format($jumlah['terbit'], 0, ',', '.') }} terbit ·
                {{ number_format($jumlah['draf'], 0, ',', '.') }} draf
                @if ($jumlah['habis'] > 0)
                    · <span class="text-danger">{{ number_format($jumlah['habis'], 0, ',', '.') }} stok habis</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline">
                <x-ikon name="tag" :size="16" />
                Kategori
            </a>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                <x-ikon name="plus" :size="16" />
                Produk baru
            </a>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @if ($catatan !== [])
        <div class="plate border-l-2 border-warn p-3 text-sm" role="status">
            <p class="font-medium">Sebagian produk dilewati:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-muted">
                @foreach ((array) $catatan as $satu)
                    <li>{{ $satu }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Saringan ────────────────────────────────────────────────── --}}
    <div class="plate space-y-3 p-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-[200px] flex-1">
                <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
                <input type="search" wire:model.live.debounce.400ms="cari"
                       class="field pl-9" placeholder="Cari nama atau SKU produk…" aria-label="Cari produk">
            </div>

            <a href="{{ route('admin.products.index', ['terbit' => 'terbit']) }}"
               class="btn {{ $terbit === 'terbit' ? 'btn-primary' : 'btn-outline' }}">Terbit</a>
            <a href="{{ route('admin.products.index', ['terbit' => 'draf']) }}"
               class="btn {{ $terbit === 'draf' ? 'btn-primary' : 'btn-outline' }}">Draf</a>
            <a href="{{ route('admin.products.index', ['stok' => 'habis']) }}"
               class="btn {{ $stok === 'habis' ? 'btn-primary' : 'btn-outline' }}">Stok habis</a>
            <a href="{{ route('admin.products.index', ['stok' => 'menipis']) }}"
               class="btn {{ $stok === 'menipis' ? 'btn-primary' : 'btn-outline' }}">Menipis</a>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="kategori" class="field w-auto" aria-label="Saring kategori">
                <option value="semua">Semua kategori</option>
                @foreach ($daftarKategori as $k)
                    <option value="{{ $k->id }}">{{ $k->name_id }}</option>
                @endforeach
            </select>

            <select wire:model.live="stok" class="field w-auto" aria-label="Saring stok">
                <option value="semua">Semua stok</option>
                <option value="ada">Ada stok</option>
                <option value="menipis">Menipis</option>
                <option value="habis">Habis</option>
            </select>

            <select wire:model.live="terbit" class="field w-auto" aria-label="Saring publikasi">
                <option value="semua">Semua publikasi</option>
                <option value="terbit">Terbit</option>
                <option value="draf">Draf</option>
            </select>

            <select wire:model.live="urut" class="field w-auto" aria-label="Urutkan">
                <option value="terbaru">Terbaru</option>
                <option value="terlama">Terlama</option>
                <option value="nama">Nama A–Z</option>
                <option value="termahal">Termahal</option>
                <option value="termurah">Termurah</option>
                <option value="terlaris">Terlaris</option>
                <option value="stok">Stok tersedikit</option>
            </select>

            @if ($cari !== '' || $kategori !== 'semua' || $stok !== 'semua' || $terbit !== 'semua' || $urut !== 'terbaru')
                <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">
                    <x-ikon name="close" :size="15" />
                    Bersihkan saringan
                </a>
            @endif
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────── --}}
    <div class="plate overflow-hidden">
        @if ($daftar->total() === 0)
            <div class="p-8 text-center">
                <x-ikon name="box" :size="28" class="mx-auto text-dim" />
                <p class="mt-3 text-sm text-muted">
                    @if ($cari !== '' || $kategori !== 'semua' || $stok !== 'semua' || $terbit !== 'semua')
                        Tidak ada produk yang cocok dengan saringan ini.
                    @else
                        Belum ada produk. Mulai dengan menambahkan yang pertama.
                    @endif
                </p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-4">
                    <x-ikon name="plus" :size="16" />
                    Produk baru
                </a>
            </div>
        @else
            <div class="flex items-center gap-3 border-b border-line-soft p-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox"
                           @checked(count($terpilih) > 0 && count($terpilih) === $daftar->count())
                           wire:click="{{ count($terpilih) > 0 ? 'kosongkanPilihan' : 'pilihSemua' }}"
                           class="h-4 w-4">
                    Pilih semua di halaman ini
                </label>

                @if (count($terpilih) > 0)
                    <span class="tech text-xs text-accent">{{ count($terpilih) }} terpilih</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($daftar as $produk)
                    @php
                        $stokBebas = (int) ($produk->stok_fisik ?? 0) - (int) ($produk->stok_ditahan ?? 0);
                        $menipis = $stokBebas > 0 && $stokBebas <= (int) config('iksan.low_stock_threshold', 3);
                        $gmbr = $produk->primaryMedia;
                    @endphp

                    <article class="group relative flex flex-col overflow-hidden rounded-[var(--r-sedang)] border border-line-soft bg-surface transition-colors hover:border-line"
                             wire:key="produk-{{ $produk->id }}">

                        {{-- Gambar: seluruh permukaannya membuka penyuntingan. --}}
                        <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                           class="relative block aspect-square bg-sunken" aria-label="Sunting {{ $produk->title_id }}">
                            @if ($gmbr)
                                <img src="{{ $gmbr->url('thumb') }}" alt=""
                                     width="220" height="220" loading="lazy" decoding="async"
                                     class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-dim">
                                    <x-ikon name="image" :size="22" />
                                </div>
                            @endif

                            @if ($produk->is_featured)
                                <span class="absolute left-2 top-2 rounded-full bg-brass/90 px-2 py-0.5 text-[11px] font-medium text-white">
                                    unggulan
                                </span>
                            @endif

                            <span class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-surface/90 shadow-sm">
                                <input type="checkbox" value="{{ $produk->id }}" wire:model.live="terpilih"
                                       class="h-3.5 w-3.5" aria-label="Pilih {{ $produk->title_id }}">
                            </span>
                        </a>

                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                                   class="line-clamp-2 block text-sm font-medium leading-snug hover:text-accent">
                                    {{ $produk->title_id }}
                                </a>
                                <p class="tech mt-1 truncate text-xs text-dim">
                                    {{ $produk->sku }} · {{ $produk->variants_count }} varian
                                </p>
                            </div>

                            <p class="truncate text-xs text-muted">{{ $produk->category?->name_id ?? '—' }}</p>

                            <div class="mt-auto flex items-end justify-between gap-2">
                                <div>
                                    <p class="tech text-sm font-medium">{{ rupiah($produk->base_price) }}</p>
                                    <p class="tech text-xs">
                                        @if ($stokBebas <= 0)
                                            <span class="text-danger">habis</span>
                                        @elseif ($menipis)
                                            <span class="text-warn">{{ $stokBebas }} sisa</span>
                                        @else
                                            <span class="text-muted">{{ $stokBebas }} stok</span>
                                        @endif

                                        @if ((int) ($produk->stok_ditahan ?? 0) > 0)
                                            <span class="text-dim" title="Ditahan pesanan berjalan">· {{ $produk->stok_ditahan }} ditahan</span>
                                        @endif
                                    </p>
                                </div>

                                <button type="button" wire:click="ubahTerbitSatu({{ $produk->id }})"
                                        class="inline-flex flex-none items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                               {{ $produk->is_published ? 'bg-ok/12 text-ok' : 'bg-sunken text-muted' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $produk->is_published ? 'bg-ok' : 'bg-line' }}"></span>
                                    {{ $produk->is_published ? 'Terbit' : 'Draf' }}
                                </button>
                            </div>

                            {{-- Tindakan: muncul saat kartu disentuh penunjuk atau difokuskan.
                                 Di ponsel tidak ada hover, jadi selalu tampil di layar kecil. --}}
                            <div class="flex items-center justify-end gap-1 border-t border-line-soft pt-2
                                        opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:group-focus-within:opacity-100">
                                <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                                   class="btn-icon h-8 w-8" title="Sunting">
                                    <x-ikon name="sliders" :size="15" />
                                    <span class="visually-hidden">Sunting produk</span>
                                </a>
                                <button type="button" wire:click="duplikat({{ $produk->id }})"
                                        class="btn-icon h-8 w-8" title="Duplikat">
                                    <x-ikon name="copy" :size="15" />
                                    <span class="visually-hidden">Duplikat</span>
                                </button>
                                <button type="button" wire:click="hapus({{ $produk->id }})"
                                        wire:confirm="Hapus produk &quot;{{ $produk->title_id }}&quot; beserta varian dan gambarnya?"
                                        class="btn-icon h-8 w-8 hover:text-danger" title="Hapus">
                                    <x-ikon name="trash" :size="15" />
                                    <span class="visually-hidden">Hapus</span>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-line-soft p-3">{{ $daftar->links() }}</div>
            @endif
        @endif
    </div>

    {{-- ── Bilah aksi massal ───────────────────────────────────────── --}}
    @if (count($terpilih) > 0)
        <div class="sticky bottom-3 z-20 mx-auto w-fit max-w-full">
            <div class="plate plate-up flex flex-wrap items-center gap-2 p-2 shadow-float">
                <span class="part-no px-2">{{ count($terpilih) }} dipilih</span>

                <button type="button" wire:click="ubahTerbitMassal(true)" class="btn btn-outline btn-sm">Terbitkan</button>
                <button type="button" wire:click="ubahTerbitMassal(false)" class="btn btn-outline btn-sm">Tarik</button>
                <button type="button" wire:click="ubahUnggulanMassal(true)" class="btn btn-outline btn-sm">Unggulan</button>

                <select wire:change="pindahKategoriMassal($event.target.value)" class="field w-auto py-1.5 text-sm"
                        aria-label="Pindahkan ke kategori">
                    <option value="">Pindah ke…</option>
                    @foreach ($daftarKategori as $k)
                        <option value="{{ $k->id }}">{{ $k->name_id }}</option>
                    @endforeach
                </select>

                <button type="button"
                        wire:click="hapusMassal"
                        wire:confirm="Hapus {{ count($terpilih) }} produk terpilih beserta gambarnya?"
                        class="btn btn-danger btn-sm">
                    <x-ikon name="trash" :size="14" />
                    Hapus
                </button>

                <button type="button" wire:click="kosongkanPilihan" class="btn btn-ghost btn-sm">Batal</button>
            </div>
        </div>
    @endif
</div>
