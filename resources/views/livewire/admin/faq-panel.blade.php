{{-- CRUD FAQ.

     Bentuknya meniru halaman Kategori: tabel di kiri, panel form di kanan.
     Pekerjaannya sama — daftar pendek yang urutannya berarti — dan admin tidak
     perlu mempelajari dua cara kerja untuk dua halaman yang mirip. --}}

<div class="space-y-5">

    {{-- ── Kepala ──────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Situs · FAQ</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Pertanyaan yang sering diajukan</h1>
            <p class="mt-1 text-sm text-muted">
                {{ $jumlahAktif }} aktif, {{ $jumlahBeranda }} tampil di beranda.
                Urutan di sini menentukan urutan tampil di situs.
            </p>
        </div>

        <button type="button" wire:click="bukaForm" class="btn btn-primary">
            <x-ikon name="plus" :size="16" />
            FAQ baru
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
                   class="field pl-9" placeholder="Cari pertanyaan atau jawaban…"
                   aria-label="Cari FAQ">
        </div>

        @if ($cari !== '')
            <button type="button" wire:click="$set('cari', '')" class="btn btn-ghost">
                <x-ikon name="close" :size="15" />
                Bersihkan
            </button>
        @endif
    </div>

    <div class="grid gap-5 {{ $formTerbuka ? 'lg:grid-cols-[1fr_400px]' : '' }}">

        {{-- ── Tabel ───────────────────────────────────────────────── --}}
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="info" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">
                        {{ $cari !== '' ? 'Tidak ada FAQ yang cocok dengan pencarian.' : 'Belum ada FAQ. Tambahkan yang pertama.' }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Urutan</th>
                                <th class="part-no p-3 font-medium">Pertanyaan</th>
                                <th class="part-no p-3 font-medium">Kelompok</th>
                                <th class="part-no p-3 font-medium">Tampil</th>
                                <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $faq)
                                <tr class="border-b border-line-soft last:border-0 hover:bg-sunken">
                                    <td class="p-3">
                                        <div class="flex items-center gap-1">
                                            <span class="tech text-xs text-dim">{{ $faq->display_order }}</span>
                                            <button type="button" wire:click="geser({{ $faq->id }}, 'naik')"
                                                    class="btn-icon h-6 w-6" title="Naikkan">
                                                <x-ikon name="chevron-left" :size="13" class="rotate-90" />
                                                <span class="visually-hidden">Naikkan</span>
                                            </button>
                                            <button type="button" wire:click="geser({{ $faq->id }}, 'turun')"
                                                    class="btn-icon h-6 w-6" title="Turunkan">
                                                <x-ikon name="chevron-right" :size="13" class="rotate-90" />
                                                <span class="visually-hidden">Turunkan</span>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="max-w-[26rem] p-3">
                                        <p class="font-medium">{{ $faq->question_id }}</p>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-muted">{{ $faq->answer_id }}</p>
                                        @if (blank($faq->question_en))
                                            <p class="mt-1 text-[0.6875rem] text-warn">Belum ada versi Inggris</p>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <span class="badge">{{ $faq->labelKelompok() }}</span>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex flex-col items-start gap-1">
                                            <button type="button" wire:click="ubahAktif({{ $faq->id }})"
                                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                                           {{ $faq->is_active ? 'bg-ok/12 text-ok' : 'bg-danger/12 text-danger' }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $faq->is_active ? 'bg-ok' : 'bg-danger' }}"></span>
                                                {{ $faq->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </button>

                                            <button type="button" wire:click="ubahBeranda({{ $faq->id }})"
                                                    class="text-[0.6875rem] {{ $faq->tampil_di_beranda ? 'text-steel' : 'text-dim' }} hover:underline">
                                                {{ $faq->tampil_di_beranda ? 'Tampil di beranda' : 'Hanya di halaman FAQ' }}
                                            </button>
                                        </div>
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button" wire:click="sunting({{ $faq->id }})"
                                                    class="btn-icon h-8 w-8" title="Sunting">
                                                <x-ikon name="sliders" :size="15" />
                                                <span class="visually-hidden">Sunting</span>
                                            </button>
                                            <button type="button" wire:click="hapus({{ $faq->id }})"
                                                    wire:confirm="Hapus FAQ &quot;{{ \Illuminate\Support\Str::limit($faq->question_id, 60) }}&quot;?"
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
                            {{ $suntingId ? 'Ubah FAQ' : 'Tambah FAQ' }}
                        </h2>
                    </div>
                    <button type="button" wire:click="tutupForm" class="btn-icon h-8 w-8" title="Tutup">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Tutup</span>
                    </button>
                </div>

                <form wire:submit="simpan" class="mt-4 space-y-3">
                    <div>
                        <label for="kelompok" class="part-no">Kelompok</label>
                        <select id="kelompok" wire:model="kelompok" class="field mt-1">
                            @foreach ($kelompokTersedia as $kunci => $label)
                                <option value="{{ $kunci }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('kelompok') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="pertanyaan_id" class="part-no">Pertanyaan (Indonesia)</label>
                        <input id="pertanyaan_id" type="text" wire:model="pertanyaan_id"
                               class="field mt-1" aria-invalid="{{ $errors->has('pertanyaan_id') ? 'true' : 'false' }}">
                        @error('pertanyaan_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="pertanyaan_en" class="part-no">Pertanyaan (Inggris) — opsional</label>
                        <input id="pertanyaan_en" type="text" wire:model="pertanyaan_en" class="field mt-1">
                        @error('pertanyaan_en') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="jawaban_id" class="part-no">Jawaban (Indonesia)</label>
                        <textarea id="jawaban_id" wire:model="jawaban_id" class="field mt-1" rows="5"
                                  aria-invalid="{{ $errors->has('jawaban_id') ? 'true' : 'false' }}"></textarea>
                        @error('jawaban_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="jawaban_en" class="part-no">Jawaban (Inggris) — opsional</label>
                        <textarea id="jawaban_en" wire:model="jawaban_en" class="field mt-1" rows="5"></textarea>
                        <p class="mt-1 text-xs text-dim">Kosongkan bila belum ada terjemahan; yang tampil versi Indonesia.</p>
                        @error('jawaban_en') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="aktif">
                            <span></span>
                        </span>
                        <span class="text-sm">Aktif (tampil di situs)</span>
                    </label>

                    <label class="flex items-center gap-2">
                        <span class="switch">
                            <input type="checkbox" wire:model="diBeranda">
                            <span></span>
                        </span>
                        <span class="text-sm">Tampilkan di beranda (bawah CTA)</span>
                    </label>

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
