{{-- Halaman sunting satu slide hero.

     Dipisah dari daftar (livewire/admin/hero.blade.php) karena tuntutannya
     berbeda: daftar harus cepat dibuka dan sering dilihat, form ini penuh
     isian dan jarang dipakai. Menyatukannya membuat setiap kunjungan ke Hero
     ikut memuat dua bahasa, titik fokus, pratinjau, dan dua kolom unggahan.

     TIGA HAL YANG BERUBAH DARI VERSI LAMA:

     1. Alamat sendiri, /admin/hero/{ulid}/sunting — dapat disalin dan dibuka
        ulang, dan menyimpan tidak melempar admin kembali ke daftar.

     2. URL tombol TIDAK WAJIB. Slide yang hanya menampilkan gambar adalah
        keadaan yang sah. Label tombol yang diisi tanpa URL tetap disimpan
        sebagai label saja; tombolnya tidak dirender di sisi depan.

     3. Gambar ponsel benar-benar OPSIONAL. Dulu, mengunggah gambar desktop
        tanpa gambar ponsel menghapus gambar ponsel yang sudah ada — padahal
        bila kosong slide memang memakai gambar desktop. Sekarang ada tombol
        eksplisit untuk membuangnya. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="part-no">Beranda · hero · urutan {{ $slide->display_order + 1 }}</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">
                {{ $slide->title_id ?: '(tanpa judul)' }}
            </h1>
            <p class="mt-1 text-sm text-muted">
                {{ $slide->media_type === 'video' ? 'Video' : 'Gambar' }} ·
                {{ $slide->is_active ? 'aktif' : 'nonaktif' }}
                @if ($slide->starts_at || $slide->ends_at)
                    · berjadwal
                @endif
            </p>
        </div>

        <div class="flex flex-none items-center gap-1">
            <a href="{{ route('admin.hero.index') }}" wire:navigate class="btn-icon"
               title="Kembali ke daftar slide" aria-label="Kembali ke daftar slide">
                <x-ikon name="chevron-left" :size="18" />
            </a>
        </div>
    </div>

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

    <form wire:submit="simpan" class="grid gap-5 lg:grid-cols-[1fr_400px] lg:items-start">

        {{-- ══ Kolom kiri: isian ══════════════════════════════════════ --}}
        <div class="space-y-5">

            {{-- Dua bahasa --}}
            <div class="plate space-y-4 p-4">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <p class="part-no">Bahasa Indonesia</p>

                        <label class="block">
                            <span class="text-sm">Judul <span class="text-danger">*</span></span>
                            <input type="text" wire:model="form.title_id" class="field mt-1" maxlength="120" />
                            @error('form.title_id') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm">Subjudul</span>
                            <textarea wire:model="form.subtitle_id" rows="2" class="field mt-1" maxlength="300"></textarea>
                            @error('form.subtitle_id') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="space-y-3">
                        <p class="part-no">English</p>

                        <label class="block">
                            <span class="text-sm">Title <span class="text-danger">*</span></span>
                            <input type="text" wire:model="form.title_en" class="field mt-1" maxlength="120" />
                            @error('form.title_en') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="text-sm">Subtitle</span>
                            <textarea wire:model="form.subtitle_en" rows="2" class="field mt-1" maxlength="300"></textarea>
                            @error('form.subtitle_en') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>
            </div>

            {{-- Tombol: URL opsional --}}
            <div class="plate space-y-4 p-4">
                <div>
                    <h2 class="font-display text-lg font-semibold">Tombol</h2>
                    <p class="mt-0.5 text-xs text-dim">
                        Keduanya opsional. Slide tanpa tombol tetap tampil — isi label saja bila belum tahu tujuannya,
                        dan tombolnya tidak dirender sampai URL-nya diisi.
                    </p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="space-y-3">
                        <label class="block">
                            <span class="part-no">Label utama (ID)</span>
                            <input type="text" wire:model="form.primary_button_label_id" class="field mt-1" maxlength="40" />
                            @error('form.primary_button_label_id') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="part-no">URL utama</span>
                            <input type="text" wire:model="form.primary_button_url" class="field tech mt-1 text-xs"
                                   placeholder="https://… atau /produk" />
                            <span class="mt-1 block text-xs text-dim">Kosongkan bila tombol belum perlu.</span>
                            @error('form.primary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="part-no">Label kedua (ID)</span>
                            <input type="text" wire:model="form.secondary_button_label_id" class="field mt-1" maxlength="40" />
                            @error('form.secondary_button_label_id') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <label class="block">
                            <span class="part-no">URL kedua</span>
                            <input type="text" wire:model="form.secondary_button_url" class="field tech mt-1 text-xs"
                                   placeholder="https://… atau /kontak" />
                            <span class="mt-1 block text-xs text-dim">Kosongkan bila tombol belum perlu.</span>
                            @error('form.secondary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <label class="block">
                        <span class="part-no">Label utama (EN)</span>
                        <input type="text" wire:model="form.primary_button_label_en" class="field mt-1" maxlength="40" />
                    </label>

                    <label class="block">
                        <span class="part-no">Label kedua (EN)</span>
                        <input type="text" wire:model="form.secondary_button_label_en" class="field mt-1" maxlength="40" />
                    </label>
                </div>
            </div>

            {{-- Berkas --}}
            <div class="plate space-y-4 p-4">
                <h2 class="font-display text-lg font-semibold">Berkas</h2>

                <label class="block">
                    <span class="part-no">Jenis media</span>
                    <select wire:model.live="form.media_type" class="field mt-2">
                        <option value="image">Gambar</option>
                        <option value="video">Video</option>
                    </select>
                </label>

                @if ($form['media_type'] === 'image')
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="block">
                            <span class="part-no">Gambar desktop</span>
                            <input type="file" wire:model="berkasGambar"
                                   accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 w-full text-sm" />
                            <span class="mt-1 block text-xs text-dim">
                                @if (filled($slide->media_path))
                                    Sudah ada. Unggah hanya bila ingin mengganti.
                                @else
                                    Wajib untuk slide baru.
                                @endif
                            </span>
                            @error('berkasGambar') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        <div class="block">
                            <span class="part-no">Gambar ponsel (opsional)</span>
                            <input type="file" wire:model="berkasPonsel"
                                   accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 w-full text-sm" />
                            <span class="mt-1 block text-xs text-dim">
                                @if (filled($slide->media_path_mobile))
                                    Sudah ada gambar ponsel sendiri. Bila dihapus, slide memakai gambar desktop.
                                @else
                                    Kosongkan bila tidak perlu — slide memakai gambar desktop.
                                @endif
                            </span>
                            @error('berkasPonsel') <span class="field-error">{{ $message }}</span> @enderror

                            @if (filled($slide->media_path_mobile))
                                <label class="mt-2 flex items-center gap-2 text-xs text-danger">
                                    <input type="checkbox" wire:model="hapusPonsel" class="h-3.5 w-3.5" />
                                    Buang gambar ponsel, pakai gambar desktop
                                </label>
                            @endif
                        </div>
                    </div>
                @else
                    <label class="block">
                        <span class="part-no">Berkas video</span>
                        <input type="file" wire:model="berkasVideo" accept="video/mp4" class="mt-2 w-full text-sm" />
                        <span class="mt-1 block text-xs text-dim">
                            MP4, maksimum {{ round($videoMaksKb / 1024, 1) }} MB dan {{ $videoMaksDetik }} detik.
                        </span>
                        @error('berkasVideo') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                @endif
            </div>

            {{-- Titik fokus + jadwal --}}
            <div class="plate space-y-4 p-4">
                <div>
                    <h2 class="font-display text-lg font-semibold">Tampilan</h2>
                    <p class="mt-0.5 text-xs text-dim">Titik fokus menentukan bagian gambar yang tetap terlihat saat dipotong.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="part-no">Titik fokus X</span>
                        <input type="range" min="0" max="1" step="0.05" wire:model.live="form.focal_point_x" class="mt-2 w-full" />
                        <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_x'] * 100) }}%</span>
                    </label>

                    <label class="block">
                        <span class="part-no">Titik fokus Y</span>
                        <input type="range" min="0" max="1" step="0.05" wire:model.live="form.focal_point_y" class="mt-2 w-full" />
                        <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_y'] * 100) }}%</span>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="part-no">Mulai tayang</span>
                        <input type="datetime-local" wire:model="form.starts_at" class="field tech mt-2 text-xs" />
                        <span class="mt-1 block text-xs text-dim">Kosongkan untuk tayang segera.</span>
                        @error('form.starts_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="part-no">Berakhir</span>
                        <input type="datetime-local" wire:model="form.ends_at" class="field tech mt-2 text-xs" />
                        <span class="mt-1 block text-xs text-dim">Kosongkan untuk tayang tanpa batas.</span>
                        @error('form.ends_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpan">
                    <x-ikon name="check" :size="17" />
                    <span wire:loading.remove wire:target="simpan">Simpan slide</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>

                <button type="button" wire:click="batal" class="btn btn-outline">Batal</button>

                <p class="text-xs text-dim">
                    Menyimpan tidak langsung menayangkan slide. Aktifkan lewat ikon mata di daftar.
                </p>
            </div>
        </div>

        {{-- ══ Kolom kanan: pratinjau ═════════════════════════════════ --}}
        <div class="space-y-3 lg:sticky lg:top-20 lg:h-fit">
            <div class="plate plate-lux space-y-3 p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="part-no">Pratinjau</p>

                    <div class="flex gap-1">
                        <button type="button"
                                class="btn {{ $pratinjau === 'ponsel' ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                wire:click="ubahPratinjau('ponsel')">
                            <x-ikon name="phone" :size="15" /> Ponsel
                        </button>
                        <button type="button"
                                class="btn {{ $pratinjau === 'desktop' ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                wire:click="ubahPratinjau('desktop')">
                            <x-ikon name="grid" :size="15" /> Desktop
                        </button>
                    </div>
                </div>

                @php
                    // Gambar ponsel hanya dipakai bila ADA. Kalau kosong, slide
                    // memakai gambar desktop — dan pratinjau menampilkan hal
                    // yang sama dengan yang dilihat pengunjung.
                    $berkasPratinjau = $slide->media_type === 'video'
                        ? ($slide->poster_path ?: $slide->media_path)
                        : ($pratinjau === 'ponsel' ? ($slide->media_path_mobile ?: $slide->media_path) : $slide->media_path);
                    $urlPratinjau = $this->urlMedia($berkasPratinjau);
                    $pakaiPonsel = $pratinjau === 'ponsel' && filled($slide->media_path_mobile);
                @endphp

                <div class="mx-auto overflow-hidden border border-line-soft bg-sunken
                            {{ $pratinjau === 'ponsel' ? 'w-[280px] rounded-[24px]' : 'w-full rounded-[var(--r-kecil)]' }}">
                    <div class="relative {{ $pratinjau === 'ponsel' ? 'aspect-[9/16]' : 'aspect-[21/9]' }} bg-carbon">
                        @if ($urlPratinjau)
                            <img src="{{ $urlPratinjau }}" alt=""
                                 class="absolute inset-0 h-full w-full object-cover"
                                 style="object-position: {{ round($form['focal_point_x'] * 100, 1) }}% {{ round($form['focal_point_y'] * 100, 1) }}%" />
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-dim">
                                <x-ikon name="image" :size="28" />
                            </div>
                        @endif

                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                        <div class="absolute inset-x-0 bottom-0 p-4">
                            <p class="font-display text-lg font-semibold text-white drop-shadow">
                                {{ $form['title_id'] ?: '(judul Indonesia)' }}
                            </p>
                            <p class="mt-1 line-clamp-2 text-xs text-white/80">
                                {{ $form['subtitle_id'] ?: '(subjudul)' }}
                            </p>

                            {{-- Tombol hanya tampil bila URL-nya terisi — sama
                                 dengan perilaku di sisi depan. --}}
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($form['primary_button_label_id'] && trim((string) $form['primary_button_url']) !== '')
                                    <span class="badge" style="color:#fff">{{ $form['primary_button_label_id'] }}</span>
                                @endif
                                @if ($form['secondary_button_label_id'] && trim((string) $form['secondary_button_url']) !== '')
                                    <span class="badge" style="color:#fff">{{ $form['secondary_button_label_id'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <p class="text-center text-xs text-dim">
                    @if (! $urlPratinjau)
                        Belum ada berkas — tampilan bawaan dipakai.
                    @elseif ($pakaiPonsel)
                        Memakai gambar ponsel khusus.
                    @elseif ($pratinjau === 'ponsel')
                        Tidak ada gambar ponsel — memakai gambar desktop.
                    @else
                        Memakai gambar desktop.
                    @endif
                </p>
            </div>
        </div>
    </form>
</div>
