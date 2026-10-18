{{-- Form produk tiga tab (F3-03, F3-04, F3-05).

     Tabnya bukan halaman terpisah: perpindahan tab hanya menyembunyikan panel
     dengan CSS, sehingga isian yang belum disimpan tidak hilang saat pengguna
     menengok tab lain. Penyimpanan tetap per tab di server. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="part-no">Katalog · produk {{ $produk ? '· '.$produk->sku : '· baru' }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                {{ $produk ? $produk->title_id : 'Produk Baru' }}
            </h1>
            @if ($produk)
                <p class="mt-1 text-sm text-muted">
                    {{ $produk->is_published ? 'Terbit' : 'Draf' }} ·
                    {{ $produk->variants->count() }} varian ·
                    {{ $media->count() }} gambar
                </p>
            @else
                <p class="mt-1 text-sm text-muted">
                    Isi informasi dasar dulu. Varian dan gambar menyusul setelah produk tersimpan.
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($produk)
                @if ($produk->is_published)
                    <a href="{{ route('front.products.show', $produk->slug) }}" target="_blank" rel="noopener"
                       class="btn btn-outline">
                        <x-ikon name="eye" :size="16" />
                        Lihat
                    </a>
                @endif
                <button type="button" wire:click="ubahTerbit" class="btn {{ $produk->is_published ? 'btn-outline' : 'btn-primary' }}">
                    <x-ikon name="{{ $produk->is_published ? 'close' : 'check' }}" :size="16" />
                    {{ $produk->is_published ? 'Tarik dari katalog' : 'Terbitkan' }}
                </button>
            @endif
            <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">Kembali</a>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Tab ─────────────────────────────────────────────────────── --}}
    <div class="-mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
        <div class="flex min-w-max gap-1 border-b border-line-soft" role="tablist">
            @foreach ([
                'info' => ['label' => 'Informasi Dasar', 'ikon' => 'card', 'siap' => $produk !== null],
                'varian' => ['label' => 'Varian', 'ikon' => 'sliders', 'siap' => $produk !== null],
                'media' => ['label' => 'Gambar', 'ikon' => 'image', 'siap' => $produk !== null],
            ] as $kunci => $t)
                <button type="button" role="tab" wire:click="aturTab('{{ $kunci }}')"
                        @disabled(! $t['siap'])
                        aria-selected="{{ $tab === $kunci ? 'true' : 'false' }}"
                        class="flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-sm whitespace-nowrap
                               {{ $tab === $kunci ? 'border-accent font-medium text-accent' : 'border-transparent text-muted hover:text-ink' }}
                               disabled:cursor-not-allowed disabled:opacity-40">
                    <x-ikon :name="$t['ikon']" :size="15" />
                    {{ $t['label'] }}
                    @if ($kunci === 'varian' && count($varian) > 0)
                        <span class="tech text-xs text-dim">{{ count($varian) }}</span>
                    @endif
                    @if ($kunci === 'media' && $media->count() > 0)
                        <span class="tech text-xs text-dim">{{ $media->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══ TAB 1: INFORMASI DASAR ═══════════════════════════════════ --}}
    @if ($tab === 'info')
        <form wire:submit="simpanInfo" class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-[1fr_320px]">

                <div class="space-y-5">
                    {{-- Nama dan slug --}}
                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">Nama produk</h2>

                        <div>
                            <label for="title_id" class="part-no">Nama (Indonesia) <span class="text-danger">*</span></label>
                            <input id="title_id" type="text" wire:model.live.debounce.500ms="title_id"
                                   class="field mt-1" placeholder="Knalpot Racing Jupiter MX"
                                   aria-invalid="{{ $errors->has('title_id') ? 'true' : 'false' }}">
                            @error('title_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="title_en" class="part-no">Nama (Inggris) — opsional</label>
                            <input id="title_en" type="text" wire:model="title_en" class="field mt-1">
                            <p class="mt-1 text-xs text-dim">Kosongkan bila belum ada terjemahan; versi Indonesia yang dipakai.</p>
                            @error('title_en') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="slug" class="part-no">Slug <span class="text-danger">*</span></label>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="tech hidden text-xs text-dim sm:inline">/produk/</span>
                                <input id="slug" type="text" wire:model.live="slug"
                                       class="field tech" aria-invalid="{{ $errors->has('slug') ? 'true' : 'false' }}">
                            </div>
                            <p class="mt-1 text-xs text-dim">Terisi otomatis dari nama. Ubah hanya bila perlu.</p>
                            @error('slug') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Deskripsi --}}
                    <div class="plate space-y-4 p-4">
                        <div>
                            <h2 class="font-display text-lg font-semibold">Ringkasan</h2>
                            <p class="mt-0.5 text-xs text-dim">Muncul di kartu katalog dan pratinjau saat dibagikan.</p>
                        </div>

                        <div>
                            <label for="excerpt_id" class="part-no">Ringkasan (Indonesia)</label>
                            <textarea id="excerpt_id" wire:model="excerpt_id" class="field mt-1" rows="2"
                                      maxlength="300"></textarea>
                            @error('excerpt_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="excerpt_en" class="part-no">Ringkasan (Inggris)</label>
                            <textarea id="excerpt_en" wire:model="excerpt_en" class="field mt-1" rows="2"
                                      maxlength="300"></textarea>
                            @error('excerpt_en') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">Deskripsi lengkap</h2>

                        <div>
                            <label for="description_id" class="part-no">Deskripsi (Indonesia)</label>
                            <textarea id="description_id" wire:model="description_id" class="field mt-1" rows="8"></textarea>
                            @error('description_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description_en" class="part-no">Deskripsi (Inggris)</label>
                            <textarea id="description_en" wire:model="description_en" class="field mt-1" rows="8"></textarea>
                            @error('description_en') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Kolom samping --}}
                <div class="space-y-5 lg:sticky lg:top-20 lg:h-fit">
                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">Harga dan berat</h2>

                        <div>
                            <label for="base_price" class="part-no">Harga dasar <span class="text-danger">*</span></label>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="tech text-sm text-dim">Rp</span>
                                <input id="base_price" type="text" inputmode="numeric" wire:model="base_price"
                                       class="field tech" aria-invalid="{{ $errors->has('base_price') ? 'true' : 'false' }}">
                            </div>
                            <p class="mt-1 text-xs text-dim">Hanya angka, tanpa titik. Varian menambah atau mengurangi dari sini.</p>
                            @error('base_price') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="weight_gram" class="part-no">Berat (gram) <span class="text-danger">*</span></label>
                            <input id="weight_gram" type="text" inputmode="numeric" wire:model="weight_gram"
                                   class="field tech mt-1" aria-invalid="{{ $errors->has('weight_gram') ? 'true' : 'false' }}">
                            <p class="mt-1 text-xs text-dim">Dipakai menghitung ongkir. Varian boleh menimpa.</p>
                            @error('weight_gram') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="plate space-y-4 p-4">
                        <h2 class="font-display text-lg font-semibold">Pengelompokan</h2>

                        <div>
                            <label for="category_id" class="part-no">Kategori <span class="text-danger">*</span></label>
                            <select id="category_id" wire:model="category_id" class="field mt-1"
                                    aria-invalid="{{ $errors->has('category_id') ? 'true' : 'false' }}">
                                <option value="">Pilih kategori…</option>
                                @foreach ($kategori as $k)
                                    <option value="{{ $k->id }}">{{ $k->name_id }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="sku" class="part-no">SKU <span class="text-danger">*</span></label>
                            <input id="sku" type="text" wire:model="sku" class="field tech mt-1"
                                   aria-invalid="{{ $errors->has('sku') ? 'true' : 'false' }}">
                            @error('sku') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="plate space-y-3 p-4">
                        <h2 class="font-display text-lg font-semibold">Tampilan</h2>

                        <label class="flex items-center gap-2">
                            <span class="switch">
                                <input type="checkbox" wire:model="is_published">
                                <span></span>
                            </span>
                            <span class="text-sm">Terbitkan sekarang</span>
                        </label>
                        <p class="text-xs text-dim">
                            Produk tanpa varian tidak dapat diterbitkan — pembeli akan sampai di halaman yang tidak bisa dibeli.
                        </p>

                        <label class="flex items-center gap-2">
                            <span class="switch">
                                <input type="checkbox" wire:model="is_featured">
                                <span></span>
                            </span>
                            <span class="text-sm">Tandai unggulan</span>
                        </label>
                        <p class="text-xs text-dim">Tampil lebih dulu di beranda.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanInfo">Simpan dan lanjut ke varian</span>
                    <span wire:loading wire:target="simpanInfo">Menyimpan…</span>
                </button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline">Batal</a>
            </div>
        </form>
    @endif

    {{-- ══ TAB 2: VARIAN ════════════════════════════════════════════ --}}
    @if ($tab === 'varian')
        <div class="space-y-4">
            <div class="plate flex flex-wrap items-center justify-between gap-3 p-3">
                <div>
                    <p class="text-sm font-medium">Varian menentukan apa yang dibeli pembeli.</p>
                    <p class="text-xs text-dim">
                        Harga akhir = harga dasar {{ rupiah($base_price ?: 0) }} ditambah penyesuaian varian.
                        Kolom yang ditahan diisi otomatis oleh pesanan berjalan.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="tambahVarian" class="btn btn-outline btn-sm">
                        <x-ikon name="plus" :size="14" />
                        Tambah varian
                    </button>
                    <button type="button" wire:click="simpanVarian" class="btn btn-primary btn-sm"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simpanVarian">Simpan varian</span>
                        <span wire:loading wire:target="simpanVarian">Menyimpan…</span>
                    </button>
                </div>
            </div>

            @error('varian')
                <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
            @enderror

            <div class="plate overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Urutan</th>
                                <th class="part-no p-3 font-medium">Nama varian <span class="text-danger">*</span></th>
                                <th class="part-no p-3 font-medium">Selisih harga</th>
                                <th class="part-no p-3 font-medium">Stok</th>
                                <th class="part-no p-3 font-medium">Ditahan</th>
                                <th class="part-no p-3 font-medium">Berat (g)</th>
                                <th class="part-no p-3 font-medium">Aktif</th>
                                <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($varian as $i => $v)
                                @php
                                    $hargaAkhir = (float) ($base_price ?: 0) + (float) ($v['selisih'] ?: 0);
                                @endphp

                                <tr class="border-b border-line-soft last:border-0" wire:key="varian-{{ $i }}-{{ $v['id'] ?? 'baru' }}">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $i + 1 }}</span>
                                            <button type="button" wire:click="geserVarian({{ $i }}, 'naik')"
                                                    class="btn-icon h-7 w-7" title="Naikkan"
                                                    {{ $loop->first ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" class="rotate-180" />
                                                <span class="visually-hidden">Naikkan</span>
                                            </button>
                                            <button type="button" wire:click="geserVarian({{ $i }}, 'turun')"
                                                    class="btn-icon h-7 w-7" title="Turunkan"
                                                    {{ $loop->last ? 'disabled' : '' }}>
                                                <x-ikon name="chevron-down" :size="13" />
                                                <span class="visually-hidden">Turunkan</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <input type="text" wire:model="varian.{{ $i }}.nama_id"
                                               class="field py-2" placeholder="Misalnya: Standar"
                                               aria-label="Nama varian baris {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.nama_id') ? 'true' : 'false' }}">
                                        @error("varian.$i.nama_id") <p class="field-error">{{ $message }}</p> @enderror

                                        <input type="text" wire:model="varian.{{ $i }}.nama_en"
                                               class="field mt-1 py-1.5 text-xs"
                                               placeholder="English name (opsional)"
                                               aria-label="Nama varian Inggris baris {{ $i + 1 }}">
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="numeric" wire:model.live.debounce.400ms="varian.{{ $i }}.selisih"
                                               class="field tech w-28 py-2"
                                               aria-label="Selisih harga baris {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.selisih') ? 'true' : 'false' }}">
                                        <p class="tech mt-1 text-xs text-dim">= {{ rupiah($hargaAkhir) }}</p>
                                        @error("varian.$i.selisih") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="numeric" wire:model="varian.{{ $i }}.stok"
                                               class="field tech w-20 py-2"
                                               aria-label="Stok baris {{ $i + 1 }}"
                                               aria-invalid="{{ $errors->has('varian.'.$i.'.stok') ? 'true' : 'false' }}">
                                        @error("varian.$i.stok") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>

                                    <td class="p-3">
                                        <span class="tech text-xs {{ ($v['ditahan'] ?? 0) > 0 ? 'text-warn' : 'text-dim' }}">
                                            {{ $v['ditahan'] ?? 0 }}
                                        </span>
                                    </td>

                                    <td class="p-3">
                                        <input type="text" inputmode="numeric" wire:model="varian.{{ $i }}.berat"
                                               class="field tech w-24 py-2" placeholder="{{ $weight_gram }}"
                                               aria-label="Berat baris {{ $i + 1 }}">
                                    </td>

                                    <td class="p-3">
                                        <span class="switch">
                                            <input type="checkbox" wire:model="varian.{{ $i }}.aktif"
                                                   aria-label="Varian baris {{ $i + 1 }} aktif">
                                            <span></span>
                                        </span>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="salinVarian({{ $i }})"
                                                    class="btn-icon h-8 w-8" title="Salin varian">
                                                <x-ikon name="copy" :size="15" />
                                                <span class="visually-hidden">Salin varian</span>
                                            </button>
                                            <button type="button" wire:click="hapusVarian({{ $i }})"
                                                    wire:confirm="Hapus varian baris {{ $i + 1 }}?"
                                                    class="btn-icon h-8 w-8 hover:text-danger" title="Hapus varian">
                                                <x-ikon name="trash" :size="15" />
                                                <span class="visually-hidden">Hapus varian</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-8 text-center">
                                        <x-ikon name="sliders" :size="28" class="mx-auto text-dim" />
                                        <p class="mt-3 text-sm text-muted">
                                            Belum ada varian. Produk harus punya minimal satu agar bisa dibeli.
                                        </p>
                                        <button type="button" wire:click="tambahVarian" class="btn btn-primary mt-4">
                                            <x-ikon name="plus" :size="16" />
                                            Tambah varian pertama
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="simpanVarian" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="simpanVarian">Simpan dan lanjut ke gambar</span>
                    <span wire:loading wire:target="simpanVarian">Menyimpan…</span>
                </button>
                <button type="button" wire:click="aturTab('info')" class="btn btn-outline">Kembali ke informasi</button>
            </div>
        </div>
    @endif

    {{-- ══ TAB 3: GAMBAR ════════════════════════════════════════════ --}}
    @if ($tab === 'media')
        <div class="space-y-4">

            {{-- Unggah --}}
            <div class="plate p-4">
                <h2 class="font-display text-lg font-semibold">Unggah gambar</h2>
                <p class="mt-0.5 text-xs text-dim">
                    JPG, PNG, WebP, atau AVIF. Maksimum 5 MB per berkas, 200 berkas dan 50 MB per produk.
                    Berkas diproses di latar belakang; gambar yang baru diunggah muncul beberapa saat kemudian.
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <input type="file" wire:model="berkasBaru" multiple accept="image/jpeg,image/png,image/webp,image/avif"
                           class="field w-auto py-2 file:mr-3 file:rounded-md file:border-0 file:bg-accent file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-fg"
                           aria-label="Pilih gambar untuk diunggah">

                    <button type="button" wire:click="unggah" class="btn btn-primary"
                            wire:loading.attr="disabled" @disabled($berkasBaru === [])>
                        <x-ikon name="upload" :size="16" />
                        <span wire:loading.remove wire:target="unggah">Unggah</span>
                        <span wire:loading wire:target="unggah">Mengunggah…</span>
                    </button>

                    <span wire:loading wire:target="berkasBaru" class="tech text-xs text-accent">
                        Membaca berkas…
                    </span>
                </div>

                @error('berkasBaru') <p class="field-error mt-2">{{ $message }}</p> @enderror
                @error('berkasBaru.*') <p class="field-error mt-2">{{ $message }}</p> @enderror

                @if (count($berkasBaru) > 0)
                    <p class="tech mt-2 text-xs text-muted">{{ count($berkasBaru) }} berkas siap diunggah</p>
                @endif
            </div>

            {{-- Yang ditolak --}}
            @if ($ditolak !== [])
                <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">
                    <p class="font-medium">{{ count($ditolak) }} berkas ditolak:</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5 text-muted">
                        @foreach ($ditolak as $alasan)
                            <li>{{ $alasan }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-dim">
                        Alasan lengkap tersimpan di catatan unggahan, termasuk berkas yang berasal dari titik akhir langsung.
                    </p>
                </div>
            @endif

            {{-- Sedang diproses --}}
            @if ($sedangDiproses > 0)
                <div class="plate border-l-2 border-accent p-3 text-sm" role="status">
                    <p>
                        <span class="tech text-accent">{{ $sedangDiproses }}</span> berkas masih dalam antrean pemrosesan.
                        <button type="button" wire:click="periksaAntrean" class="ml-1 text-accent underline">Periksa lagi</button>
                    </p>
                    <p class="mt-1 text-xs text-dim">
                        Pemrosesan berjalan di latar belakang. Bila angka ini tidak berubah, pastikan pekerja antrean hidup:
                        <code class="tech">php artisan queue:work</code>
                    </p>
                </div>
            @endif

            {{-- Daftar gambar --}}
            @if ($media->isEmpty())
                <div class="plate p-8 text-center">
                    <x-ikon name="image" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">Belum ada gambar. Produk tanpa gambar tampil dengan kotak kosong di katalog.</p>
                </div>
            @else
                <div class="plate p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold">
                            {{ $media->count() }} gambar
                        </h2>
                        <p class="text-xs text-dim">Urutan di sini menentukan urutan di halaman produk. Gambar utama tampil di katalog.</p>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($media as $g)
                            <div class="plate overflow-hidden" wire:key="media-{{ $g->id }}">
                                <div class="relative aspect-[4/3] bg-sunken">
                                    <img src="{{ $g->url('card') }}" alt="{{ $g->alt() }}"
                                         width="{{ $g->width }}" height="{{ $g->height }}"
                                         loading="lazy" decoding="async"
                                         class="h-full w-full object-cover">

                                    @if ($g->is_primary)
                                        <span class="absolute left-1.5 top-1.5 rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-fg">
                                            Utama
                                        </span>
                                    @endif

                                    <span class="tech absolute right-1.5 top-1.5 rounded-md bg-black/60 px-1.5 py-0.5 text-xs text-white">
                                        {{ $loop->iteration }}
                                    </span>
                                </div>

                                <div class="space-y-2 p-2">
                                    <p class="truncate text-xs text-muted" title="{{ $g->original_filename }}">
                                        {{ $g->original_filename ?: 'tanpa nama' }}
                                    </p>
                                    <p class="tech text-xs text-dim">
                                        {{ $g->width }}×{{ $g->height }} · {{ ukuran_berkas($g->byte_size) }}
                                    </p>

                                    <div>
                                        <label for="alt-{{ $g->id }}" class="part-no">Teks alternatif</label>
                                        <input id="alt-{{ $g->id }}" type="text"
                                               class="field mt-1 py-1.5 text-xs"
                                               placeholder="{{ $produk->title_id }}"
                                               value="{{ $g->alt_text_id }}"
                                               wire:blur="simpanAlt({{ $g->id }}, $event.target.value, 'id')">
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button type="button" wire:click="geserMedia({{ $g->id }}, 'naik')"
                                                class="btn-icon h-7 w-7" title="Geser ke kiri"
                                                {{ $loop->first ? 'disabled' : '' }}>
                                            <x-ikon name="chevron-left" :size="13" />
                                            <span class="visually-hidden">Geser ke kiri</span>
                                        </button>
                                        <button type="button" wire:click="geserMedia({{ $g->id }}, 'turun')"
                                                class="btn-icon h-7 w-7" title="Geser ke kanan"
                                                {{ $loop->last ? 'disabled' : '' }}>
                                            <x-ikon name="chevron-right" :size="13" />
                                            <span class="visually-hidden">Geser ke kanan</span>
                                        </button>

                                        @unless ($g->is_primary)
                                            <button type="button" wire:click="jadikanUtama({{ $g->id }})"
                                                    class="btn-icon h-7 w-7" title="Jadikan gambar utama">
                                                <x-ikon name="star" :size="14" />
                                                <span class="visually-hidden">Jadikan gambar utama</span>
                                            </button>
                                        @endunless

                                        <button type="button" wire:click="hapusMedia({{ $g->id }})"
                                                wire:confirm="Hapus gambar ini?"
                                                class="btn-icon ml-auto h-7 w-7 hover:text-danger" title="Hapus gambar">
                                            <x-ikon name="trash" :size="14" />
                                            <span class="visually-hidden">Hapus gambar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" wire:click="aturTab('varian')" class="btn btn-outline">Kembali ke varian</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-primary">Selesai</a>
            </div>
        </div>
    @endif
</div>
