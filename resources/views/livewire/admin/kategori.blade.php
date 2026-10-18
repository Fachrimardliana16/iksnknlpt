{{-- CRUD kategori (F3-01).

     Satu halaman: tabel untuk melihat dan mengubah urutan, panel samping untuk
     menambah atau menyunting. Formnya tidak berpindah halaman, karena mengubah
     urutan dan menambah kategori biasanya dilakukan berurutan. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Katalog · kategori</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Kategori</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah, 0, ',', '.') }} kategori, {{ number_format($totalProduk, 0, ',', '.') }} produk.
                Urutan di sini menentukan urutan menu di beranda.
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            Kategori baru
        </button>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Pencarian ───────────────────────────────────────────────── --}}
    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[200px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari"
                   class="field pl-9" placeholder="Cari nama atau slug kategori…"
                   aria-label="Cari kategori">
        </div>

        @if ($cari !== '')
            <button type="button" wire:click="$set('cari', '')" class="btn btn-ghost">
                <x-ikon name="close" :size="15" />
                Bersihkan
            </button>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_380px]">

        {{-- ── Tabel ───────────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="tag" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">
                        {{ $cari !== '' ? 'Tidak ada kategori yang cocok dengan pencarian.' : 'Belum ada kategori. Tambahkan yang pertama.' }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Urutan</th>
                                <th class="part-no p-3 font-medium">Nama</th>
                                <th class="part-no p-3 font-medium">Slug</th>
                                <th class="part-no p-3 text-right font-medium">Produk</th>
                                <th class="part-no p-3 font-medium">Status</th>
                                <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $kategori)
                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $kategori->display_order }}</span>
                                            <button type="button" wire:click="geser({{ $kategori->id }}, 'naik')"
                                                    class="btn-icon h-7 w-7" title="Naikkan"
                                                    {{ $loop->first ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" class="rotate-180" />
                                                <span class="visually-hidden">Naikkan</span>
                                            </button>
                                            <button type="button" wire:click="geser({{ $kategori->id }}, 'turun')"
                                                    class="btn-icon h-7 w-7" title="Turunkan"
                                                    {{ $loop->last ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" />
                                                <span class="visually-hidden">Turunkan</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <p class="font-medium">{{ $kategori->name_id }}</p>
                                        @if (filled($kategori->name_en))
                                            <p class="text-xs text-dim">{{ $kategori->name_en }}</p>
                                        @else
                                            <p class="text-xs text-dim">— tanpa versi Inggris</p>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <code class="tech text-xs text-muted">{{ $kategori->slug }}</code>
                                    </td>

                                    <td class="p-3 text-right">
                                        @if ($kategori->products_count > 0)
                                            <a href="{{ route('admin.products.index', ['kategori' => $kategori->id]) }}"
                                               class="tech text-accent hover:underline">
                                                {{ $kategori->products_count }}
                                            </a>
                                        @else
                                            <span class="tech text-dim">0</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <button type="button" wire:click="ubahAktif({{ $kategori->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                                       {{ $kategori->is_active ? 'bg-ok/12 text-ok' : 'bg-danger/12 text-danger' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $kategori->is_active ? 'bg-ok' : 'bg-danger' }}"></span>
                                            {{ $kategori->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="sunting({{ $kategori->id }})"
                                                    class="btn-icon h-8 w-8" title="Sunting">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">Sunting</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $kategori->id }})"
                                                    wire:confirm="Hapus kategori &quot;{{ $kategori->name_id }}&quot;?"
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

        {{-- ── Panel form ──────────────────────────────────────────── --}}
        @if ($formTerbuka)
            <div class="plate h-fit p-4 lg:sticky lg:top-20">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="part-no">{{ $suntingId ? 'Sunting' : 'Baru' }}</p>
                        <h2 class="font-display text-lg font-semibold">
                            {{ $suntingId ? 'Ubah kategori' : 'Tambah kategori' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon h-8 w-8" title="Tutup">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Tutup</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="nama_id" class="part-no">Nama (Indonesia)</label>
                        <input id="nama_id" type="text" wire:model.live.debounce.500ms="nama_id"
                               class="field mt-1" aria-invalid="{{ $errors->has('nama_id') ? 'true' : 'false' }}">
                        @error('nama_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="nama_en" class="part-no">Nama (Inggris) — opsional</label>
                        <input id="nama_en" type="text" wire:model="nama_en" class="field mt-1">
                        <p class="mt-1 text-xs text-dim">Kosongkan bila belum ada terjemahan.</p>
                        @error('nama_en') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="slug" class="part-no">Slug</label>
                        <input id="slug" type="text" wire:model.live="slug"
                               class="field tech mt-1" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">
                        <p class="mt-1 text-xs text-dim">Dipakai di alamat halaman. Terisi otomatis dari nama.</p>
                        @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="deskripsi_id" class="part-no">Deskripsi (Indonesia)</label>
                        <textarea id="deskripsi_id" wire:model="deskripsi_id" class="field mt-1" rows="3"></textarea>
                        @error('deskripsi_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="deskripsi_en" class="part-no">Deskripsi (Inggris)</label>
                        <textarea id="deskripsi_en" wire:model="deskripsi_en" class="field mt-1" rows="3"></textarea>
                        @error('deskripsi_en') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="aktif">
                            <span></span>
                        </span>
                        <span class="text-sm">Tampilkan di menu</span>
                    </label>
                    @error('aktif') <p class="field-error">{{ $message }}</p> @enderror

                    <div class="flex items-center gap-2 pt-1">
                        <button type="submit" class="btn btn-primary flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="simpan">{{ $suntingId ? 'Simpan perubahan' : 'Tambah' }}</span>
                            <span wire:loading wire:target="simpan">Menyimpan…</span>
                        </button>
                        <button type="button" wire:click="tutupForm" class="btn btn-outline">Batal</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
