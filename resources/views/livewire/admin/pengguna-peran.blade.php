{{-- Pengguna & Peran — akun yang boleh masuk panel.

     Dipisahkan dari halaman Pelanggan karena pertanyaannya berbeda: halaman
     itu menjawab "siapa yang belanja", halaman ini "siapa yang boleh mengubah
     toko". Matriks kewenangan di bawah dibaca dari KebijakanPanel, bukan
     ditulis ulang di sini, supaya tampilan tidak pernah berbeda dari yang
     benar-benar ditegakkan di server. --}}
<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Akses · akun panel</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Pengguna &amp; Peran</h1>
            <p class="mt-1 text-sm text-muted">
                Akun yang boleh masuk panel. Pelanggan dikelola terpisah di halaman Pelanggan.
            </p>
        </div>

        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline">
            <x-ikon name="users" :size="16" />
            Halaman Pelanggan
        </a>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    {{-- ── Ringkasan peran ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-3 gap-3">
        @php
            $kartu = [
                ['label' => 'Super Admin', 'nilai' => $hitungan[\App\Enums\PeranPengguna::SuperAdmin->value], 'catatan' => 'Kelola akun & kredensial', 'ikon' => 'shield'],
                ['label' => 'Admin', 'nilai' => $hitungan[\App\Enums\PeranPengguna::Admin->value], 'catatan' => 'Kelola katalog & pesanan', 'ikon' => 'users'],
                ['label' => 'Pelanggan', 'nilai' => $hitungan[\App\Enums\PeranPengguna::Customer->value], 'catatan' => 'Tidak bisa masuk panel', 'ikon' => 'shopee'],
            ];
        @endphp

        @foreach ($kartu as $k)
            <div class="plate p-4">
                <div class="flex items-center justify-between">
                    <p class="part-no">{{ $k['label'] }}</p>
                    <x-ikon :name="$k['ikon']" :size="17" class="text-dim" />
                </div>
                <p class="tech mt-3 text-xl font-medium">{{ $k['nilai'] }}</p>
                <p class="mt-1 text-xs text-dim">{{ $k['catatan'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Matriks kewenangan ────────────────────────────────────────
         Barisnya tindakan, kolomnya peran. Centang berarti peran itu boleh.
         Sumbernya KebijakanPanel: apa yang tertulis di sini adalah apa yang
         benar-benar diperiksa server saat tindakan dijalankan. --}}
    <div class="plate overflow-hidden">
        <div class="border-b border-line-soft p-3">
            <h2 class="font-medium">Kewenangan tiap peran</h2>
            <p class="mt-0.5 text-xs text-dim">
                Yang tampil di sini dibaca dari kebijakan panel, bukan ditulis ulang.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line-soft text-left">
                        <th class="part-no p-3 font-medium">Tindakan</th>
                        <th class="part-no p-3 text-center font-medium">Admin</th>
                        <th class="part-no p-3 text-center font-medium">Super Admin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($matriks as $baris)
                        <tr class="border-b border-line-soft last:border-0">
                            <td class="p-3">{{ $baris['tindakan'] }}</td>
                            <td class="p-3 text-center">
                                @if ($baris['admin'])
                                    <x-ikon name="check" :size="16" class="mx-auto text-ok" />
                                    <span class="visually-hidden">Boleh</span>
                                @else
                                    <span class="text-dim" aria-label="Tidak boleh">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if ($baris['super'])
                                    <x-ikon name="check" :size="16" class="mx-auto text-ok" />
                                    <span class="visually-hidden">Boleh</span>
                                @else
                                    <span class="text-dim" aria-label="Tidak boleh">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Daftar akun panel ───────────────────────────────────────── --}}
    <div class="plate overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line-soft text-left">
                        <th class="part-no p-3 font-medium">Nama</th>
                        <th class="part-no p-3 font-medium">Peran</th>
                        <th class="part-no p-3 font-medium">Status</th>
                        <th class="part-no p-3 text-right font-medium">Pesanan</th>
                        <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($akun as $u)
                        <tr class="border-b border-line-soft last:border-0 hover:bg-sunken" wire:key="pengguna-{{ $u->id }}">
                            <td class="p-3">
                                <p class="font-medium">{{ $u->name }}</p>
                                <p class="tech text-xs text-dim">{{ $u->email }}</p>
                            </td>

                            <td class="p-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs
                                             {{ $u->role === \App\Enums\PeranPengguna::SuperAdmin ? 'bg-brass/12 text-brass' : 'bg-sunken text-muted' }}">
                                    <x-ikon name="shield" :size="12" />
                                    {{ $u->role->label() }}
                                </span>
                            </td>

                            <td class="p-3">
                                @if ($u->is_blocked)
                                    <span class="text-xs text-danger">Diblokir</span>
                                @else
                                    <span class="text-xs text-muted">Aktif</span>
                                @endif
                            </td>

                            <td class="tech p-3 text-right">{{ $u->orders_count }}</td>

                            <td class="p-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="buka({{ $u->id }})"
                                            class="btn-icon h-8 w-8" title="Rincian">
                                        <x-ikon name="eye" :size="15" />
                                        <span class="visually-hidden">Rincian {{ $u->name }}</span>
                                    </button>

                                    <button type="button" wire:click="ubahBlokir({{ $u->id }})"
                                            class="btn-icon h-8 w-8 hover:text-danger"
                                            title="{{ $u->is_blocked ? 'Buka blokir' : 'Blokir' }}">
                                        <x-ikon :name="$u->is_blocked ? 'refresh' : 'lock'" :size="15" />
                                        <span class="visually-hidden">{{ $u->is_blocked ? 'Buka blokir' : 'Blokir' }} {{ $u->name }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-sm text-muted">
                                Belum ada akun panel selain akun ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($akun->hasPages())
            <div class="border-t border-line-soft p-3">{{ $akun->links() }}</div>
        @endif
    </div>

    {{-- ── Panel rincian ────────────────────────────────────────────── --}}
    @if ($dipilihDetail)
        <div class="plate space-y-3 p-4" wire:key="rincian-{{ $dipilihDetail->id }}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="part-no">Rincian akun</p>
                    <h2 class="mt-1 font-display text-lg font-semibold">{{ $dipilihDetail->name }}</h2>
                    <p class="tech text-xs text-dim">{{ $dipilihDetail->email }}</p>
                </div>

                <button type="button" wire:click="tutup" class="btn-icon h-8 w-8" title="Tutup">
                    <x-ikon name="close" :size="16" />
                    <span class="visually-hidden">Tutup rincian</span>
                </button>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="part-no">Peran sekarang</dt>
                    <dd class="mt-1">{{ $dipilihDetail->role->label() }}</dd>
                </div>
                <div>
                    <dt class="part-no">Pesanan</dt>
                    <dd class="mt-1">{{ $dipilihDetail->orders_count }}</dd>
                </div>
                <div>
                    <dt class="part-no">Status</dt>
                    <dd class="mt-1">{{ $dipilihDetail->is_blocked ? 'Diblokir' : 'Aktif' }}</dd>
                </div>
            </dl>

            {{-- Ubah peran.

                 Hanya tersedia bagi yang berwenang (super admin), dan akun
                 sendiri dikecualikan — tombol disembunyikan sekaligus ditolak
                 di server. Menyembunyikan tombol saja tidak cukup: permintaan
                 Livewire bisa dikirim langsung tanpa melewati antarmuka. --}}
            @can('kelolaAkun', \App\Models\User::class)
                <div class="border-t border-line-soft pt-3">
                    <p class="part-no">Ubah peran</p>

                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ([\App\Enums\PeranPengguna::Admin, \App\Enums\PeranPengguna::SuperAdmin] as $peran)
                            <button type="button"
                                    wire:click="ubahPeran({{ $dipilihDetail->id }}, '{{ $peran->value }}')"
                                    @disabled(auth()->id() === $dipilihDetail->id || $dipilihDetail->role === $peran)
                                    class="btn btn-outline btn-sm disabled:opacity-40">
                                Jadikan {{ $peran->label() }}
                            </button>
                        @endforeach
                    </div>

                    @if (auth()->id() === $dipilihDetail->id)
                        <p class="mt-2 text-xs text-dim">
                            Ini akun yang sedang dipakai — perannya tidak dapat diubah dari sini.
                        </p>
                    @endif
                </div>
            @endcan
        </div>
    @endif
</div>
