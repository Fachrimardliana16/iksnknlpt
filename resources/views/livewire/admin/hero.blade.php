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

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="part-no">Beranda · hero</p>
            <p class="mt-1 text-sm text-muted">
                {{ $slides->count() }} dari maksimum {{ $maksSlide }} slide ·
                {{ $slides->where('is_active', true)->count() }} aktif
            </p>
        </div>

        <button type="button" class="btn btn-primary" wire:click="tambah"
                @disabled($slides->count() >= $maksSlide)>
            <x-ikon name="plus" :size="17" />
            Tambah slide
        </button>
    </div>

    {{-- ── Daftar slide ────────────────────────────────────────────── --}}
    <div class="plate divide-y divide-line-soft"
         x-data="{
            menyeret: null,
            atas: null,
            taruh(posisi) {
                if (this.menyeret === null || this.menyeret === posisi) return;
                const ids = @js($slides->pluck('id')->all());
                const dipindah = ids.splice(this.menyeret, 1)[0];
                ids.splice(posisi, 0, dipindah);
                $wire.terapkanUrutan(ids);
                this.menyeret = null;
                this.atas = null;
            }
         }">

        @forelse ($slides as $i => $s)
            <div class="flex items-start gap-3 p-3 {{ $sunting === $s->id ? 'bg-sunken' : '' }}"
                 wire:key="slide-{{ $s->id }}"
                 draggable="true"
                 @dragstart="menyeret = {{ $i }}"
                 @dragover.prevent="atas = {{ $i }}"
                 @drop.prevent="taruh({{ $i }})"
                 @dragend="menyeret = null; atas = null"
                 :class="atas === {{ $i }} && menyeret !== null ? 'ring-1 ring-accent' : ''">

                <span class="mt-1 flex-none cursor-grab text-dim" title="Seret untuk mengubah urutan">
                    <x-ikon name="menu" :size="18" />
                </span>

                <div class="h-14 w-20 flex-none overflow-hidden rounded border border-line-soft bg-sunken">
                    @php $url = filled($s->media_path) ? \App\Models\ProductMedia::urlMedia($s->media_path) : null; @endphp

                    @if ($url && $s->media_type === 'image')
                        <img src="{{ $url }}" alt="" class="h-full w-full object-cover" />
                    @elseif ($s->media_type === 'video')
                        <span class="flex h-full w-full items-center justify-center text-dim">
                            <x-ikon name="activity" :size="20" />
                        </span>
                    @else
                        <span class="flex h-full w-full items-center justify-center text-dim">
                            <x-ikon name="image" :size="20" />
                        </span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-medium">{{ $s->title_id ?: '(tanpa judul)' }}</p>

                        <span class="badge badge-neutral">{{ $s->media_type }}</span>
                        <span class="badge {{ $s->is_active ? 'badge-ok' : 'badge-neutral' }}">
                            {{ $s->is_active ? 'aktif' : 'nonaktif' }}
                        </span>

                        @if ($s->starts_at || $s->ends_at)
                            <span class="badge badge-steel">jadwal</span>
                        @endif

                        @if (blank($s->media_path))
                            <span class="badge badge-warn">tanpa berkas</span>
                        @endif
                    </div>

                    <p class="mt-1 truncate text-xs text-muted">{{ $s->title_en ?: '(no title)' }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-1">
                        <button type="button" class="btn-icon" wire:click="naik({{ $s->id }})"
                                @disabled($i === 0) title="Naikkan">
                            <x-ikon name="chevron-down" :size="17" class="rotate-180" />
                            <span class="visually-hidden">Naikkan</span>
                        </button>

                        <button type="button" class="btn-icon" wire:click="turun({{ $s->id }})"
                                @disabled($i === $slides->count() - 1) title="Turunkan">
                            <x-ikon name="chevron-down" :size="17" />
                            <span class="visually-hidden">Turunkan</span>
                        </button>

                        <button type="button" class="btn-icon" wire:click="sunting({{ $s->id }})" title="Sunting">
                            <x-ikon name="sliders" :size="17" />
                            <span class="visually-hidden">Sunting</span>
                        </button>

                        <button type="button" class="btn-icon" wire:click="ubahAktif({{ $s->id }})"
                                title="{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                            <x-ikon :name="$s->is_active ? 'eye' : 'eye'" :size="17"
                                    class="{{ $s->is_active ? 'text-ok' : '' }}" />
                            <span class="visually-hidden">
                                {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </span>
                        </button>

                        <button type="button" class="btn-icon text-danger"
                                wire:click="hapus({{ $s->id }})"
                                wire:confirm="Hapus slide &quot;{{ $s->title_id }}&quot; beserta berkasnya?"
                                title="Hapus">
                            <x-ikon name="trash" :size="17" />
                            <span class="visually-hidden">Hapus</span>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-8 text-center">
                <p class="font-semibold">Belum ada slide.</p>
                <p class="mt-1 text-sm text-muted">Hero halaman depan memakai tampilan bawaan sampai slide pertama ditambahkan.</p>
            </div>
        @endforelse
    </div>

    {{-- ── Panel penyuntingan ──────────────────────────────────────── --}}
    @if ($sedang !== null)
        <form wire:submit="simpan" class="plate plate-lux space-y-5 p-4 lg:p-6">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="part-no">Sunting slide</p>
                    <p class="mt-1 font-display text-lg font-semibold">{{ $sedang->title_id ?: '(tanpa judul)' }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="badge badge-neutral">urutan {{ $sedang->display_order + 1 }}</span>

                    <button type="button" class="btn btn-outline" wire:click="batalSunting">
                        <x-ikon name="close" :size="17" />
                        Tutup
                    </button>
                </div>
            </div>

            {{-- Pratinjau dua bingkai (F4-10) --}}
            <div>
                <div class="flex items-center justify-between gap-3">
                    <p class="part-no">Pratinjau</p>

                    <div class="flex gap-1">
                        <button type="button"
                                class="btn {{ $pratinjau === 'ponsel' ? 'btn-primary' : 'btn-ghost' }}"
                                wire:click="ubahPratinjau('ponsel')">
                            <x-ikon name="phone" :size="16" /> Ponsel
                        </button>
                        <button type="button"
                                class="btn {{ $pratinjau === 'desktop' ? 'btn-primary' : 'btn-ghost' }}"
                                wire:click="ubahPratinjau('desktop')">
                            <x-ikon name="grid" :size="16" /> Desktop
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex justify-center">
                    @php
                        $berkasPratinjau = $sedang->media_type === 'video'
                            ? ($sedang->poster_path ?: $sedang->media_path)
                            : ($pratinjau === 'ponsel' ? ($sedang->media_path_mobile ?: $sedang->media_path) : $sedang->media_path);
                        $urlPratinjau = filled($berkasPratinjau) ? \App\Models\ProductMedia::urlMedia($berkasPratinjau) : null;
                    @endphp

                    <div class="overflow-hidden border border-line-soft bg-sunken transition-all
                                {{ $pratinjau === 'ponsel' ? 'w-[320px] rounded-[24px]' : 'w-full rounded-md' }}">
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

                            {{-- Titik fokus dapat digeser: klik pada pratinjau. --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <p class="font-display text-xl font-semibold text-white drop-shadow">
                                    {{ $form['title_id'] ?: '(judul Indonesia)' }}
                                </p>
                                <p class="mt-1 line-clamp-2 text-xs text-white/80">
                                    {{ $form['subtitle_id'] ?: '(subjudul)' }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($form['primary_button_label_id'])
                                        <span class="badge" style="color:#fff">{{ $form['primary_button_label_id'] }}</span>
                                    @endif
                                    @if ($form['secondary_button_label_id'])
                                        <span class="badge" style="color:#fff">{{ $form['secondary_button_label_id'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="mt-2 text-center text-xs text-dim">
                    Jalur tampil: {{ $urlPratinjau ? 'berkas milik slide ini' : 'tampilan bawaan' }}
                </p>
            </div>

            {{-- Titik fokus --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="part-no">Titik fokus X</span>
                    <input type="range" min="0" max="1" step="0.05"
                           wire:model.live="form.focal_point_x"
                           wire:change="ubahFokus({{ $form['focal_point_x'] }}, {{ $form['focal_point_y'] }})"
                           class="mt-2 w-full" />
                    <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_x'] * 100) }}%</span>
                </label>

                <label class="block">
                    <span class="part-no">Titik fokus Y</span>
                    <input type="range" min="0" max="1" step="0.05"
                           wire:model.live="form.focal_point_y"
                           wire:change="ubahFokus({{ $form['focal_point_x'] }}, {{ $form['focal_point_y'] }})"
                           class="mt-2 w-full" />
                    <span class="tech mt-1 block text-xs text-muted">{{ round($form['focal_point_y'] * 100) }}%</span>
                </label>
            </div>

            {{-- Berkas --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="part-no">Jenis media</span>
                    <select wire:model.live="form.media_type" class="field mt-2">
                        <option value="image">Gambar</option>
                        <option value="video">Video</option>
                    </select>
                </label>

                @if ($form['media_type'] === 'image')
                    <label class="block">
                        <span class="part-no">Gambar desktop</span>
                        <input type="file" wire:model="berkasGambar"
                               accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 w-full text-sm" />
                        @error('berkasGambar') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="part-no">Gambar ponsel (opsional)</span>
                        <input type="file" wire:model="berkasPonsel"
                               accept="image/jpeg,image/png,image/webp,image/avif" class="mt-2 w-full text-sm" />
                        @error('berkasPonsel') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
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

            {{-- Dua bahasa --}}
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-3">
                    <p class="part-no">Bahasa Indonesia</p>

                    <label class="block">
                        <span class="text-sm">Judul</span>
                        <input type="text" wire:model="form.title_id" class="field mt-1" maxlength="120" />
                        @error('form.title_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Subjudul</span>
                        <textarea wire:model="form.subtitle_id" rows="2" class="field mt-1" maxlength="300"></textarea>
                        @error('form.subtitle_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Label tombol utama</span>
                        <input type="text" wire:model="form.primary_button_label_id" class="field mt-1" maxlength="40" />
                        @error('form.primary_button_label_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Label tombol kedua</span>
                        <input type="text" wire:model="form.secondary_button_label_id" class="field mt-1" maxlength="40" />
                        @error('form.secondary_button_label_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="space-y-3">
                    <p class="part-no">English</p>

                    <label class="block">
                        <span class="text-sm">Title</span>
                        <input type="text" wire:model="form.title_en" class="field mt-1" maxlength="120" />
                        @error('form.title_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Subtitle</span>
                        <textarea wire:model="form.subtitle_en" rows="2" class="field mt-1" maxlength="300"></textarea>
                        @error('form.subtitle_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Primary button label</span>
                        <input type="text" wire:model="form.primary_button_label_en" class="field mt-1" maxlength="40" />
                        @error('form.primary_button_label_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Secondary button label</span>
                        <input type="text" wire:model="form.secondary_button_label_en" class="field mt-1" maxlength="40" />
                        @error('form.secondary_button_label_en') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </div>

            {{-- Tautan tombol --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="part-no">URL tombol utama</span>
                    <input type="url" wire:model="form.primary_button_url" class="field tech mt-2 text-xs"
                           placeholder="https://" />
                    @error('form.primary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="part-no">URL tombol kedua</span>
                    <input type="url" wire:model="form.secondary_button_url" class="field tech mt-2 text-xs"
                           placeholder="https://" />
                    @error('form.secondary_button_url') <span class="field-error">{{ $message }}</span> @enderror
                </label>
            </div>

            {{-- Jadwal tayang --}}
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

            <div class="flex flex-wrap items-center gap-3 border-t border-line-soft pt-4">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpan">
                    <x-ikon name="check" :size="17" />
                    <span wire:loading.remove wire:target="simpan">Simpan slide</span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>

                <p class="text-xs text-dim">
                    Menyimpan tidak langsung menayangkan slide. Aktifkan lewat ikon mata di daftar.
                </p>
            </div>
        </form>
    @endif
</div>
