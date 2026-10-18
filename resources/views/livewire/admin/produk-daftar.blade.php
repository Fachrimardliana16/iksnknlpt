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

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line-soft text-left">
                            <th class="p-3"><span class="visually-hidden">Pilih</span></th>
                            <th class="part-no p-3 font-medium">Produk</th>
                            <th class="part-no p-3 font-medium">Kategori</th>
                            <th class="part-no p-3 text-right font-medium">Harga</th>
                            <th class="part-no p-3 text-right font-medium">Stok</th>
                            <th class="part-no p-3 font-medium">Status</th>
                            <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftar as $produk)
                            @php
                                $stokBebas = (int) ($produk->stok_fisik ?? 0) - (int) ($produk->stok_ditahan ?? 0);
                                $menipis = $stokBebas > 0 && $stokBebas <= (int) config('iksan.low_stock_threshold', 3);
                                $gmbr = $produk->primaryMedia;
                            @endphp

                            <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                <td class="p-3">
                                    <input type="checkbox" value="{{ $produk->id }}" wire:model.live="terpilih"
                                           class="h-4 w-4" aria-label="Pilih {{ $produk->title_id }}">
                                </td>

                                <td class="p-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-11 w-11 flex-none overflow-hidden rounded-md bg-sunken">
                                            @if ($gmbr)
                                                <img src="{{ $gmbr->url('thumb') }}" alt=""
                                                     width="44" height="44" loading="lazy" decoding="async"
                                                     class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-dim">
                                                    <x-ikon name="image" :size="16" />
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <a href="{{ route('admin.products.edit', $produk->ulid) }}"
                                               class="block truncate font-medium hover:text-accent">
                                                {{ $produk->title_id }}
                                            </a>
                                            <p class="tech text-xs text-dim">
                                                {{ $produk->sku }} · {{ $produk->variants_count }} varian
                                                @if ($produk->is_featured) · <span class="text-brass">unggulan</span> @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-3 text-muted">{{ $produk->category?->name_id ?? '—' }}</td>

                                <td class="tech p-3 text-right">{{ rupiah($produk->base_price) }}</td>

                                <td class="p-3 text-right">
                                    @if ($stokBebas <= 0)
                                        <span class="tech text-danger">habis</span>
                                    @elseif ($menipis)
                                        <span class="tech text-warn">{{ $stokBebas }}</span>
                                    @else
                                        <span class="tech">{{ $stokBebas }}</span>
                                    @endif

                                    @if ((int) ($produk->stok_ditahan ?? 0) > 0)
                                        <span class="block text-xs text-dim" title="Ditahan pesanan berjalan">
                                            {{ $produk->stok_ditahan }} ditahan
                                        </span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    <button type="button" wire:click="ubahTerbitSatu({{ $produk->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                                   {{ $produk->is_published ? 'bg-ok/12 text-ok' : 'bg-sunken text-muted' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $produk->is_published ? 'bg-ok' : 'bg-line' }}"></span>
                                        {{ $produk->is_published ? 'Terbit' : 'Draf' }}
                                    </button>
                                </td>

                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-1">
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
