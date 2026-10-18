{{-- Pelanggan dan akun panel.

     Peran dan blokir diubah langsung dari baris tabel: keduanya tindakan yang
     sering dipakai saat menanggapi keluhan, dan membuka formulir untuk satu
     perubahan kecil memperlambat pekerjaan. --}}

<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="part-no">Penjualan · akun</p>
            <h1 class="mt-1 font-display text-2xl font-semibold">Pelanggan</h1>
            <p class="mt-1 text-sm text-muted">
                {{ number_format($jumlah['semua'], 0, ',', '.') }} akun ·
                {{ number_format($jumlah['pelanggan'], 0, ',', '.') }} pelanggan ·
                {{ number_format($jumlah['panel'], 0, ',', '.') }} pengguna panel
                @if ($jumlah['diblokir'] > 0)
                    · <span class="text-danger">{{ $jumlah['diblokir'] }} diblokir</span>
                @endif
            </p>
        </div>
    </div>

    @if ($pesan)
        <div class="plate border-l-2 border-ok p-3 text-sm" role="status">{{ $pesan }}</div>
    @endif

    @error('umum')
        <div class="plate border-l-2 border-danger p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    @if ($superAdminTerakhir <= 1)
        <div class="plate border-l-2 border-warn p-3 text-sm" role="status">
            Hanya ada satu super admin. Akun itu tidak dapat diblokir, diturunkan perannya, atau dihapus —
            tanpa super admin, tidak ada yang bisa mengelola admin dan kredensial pembayaran.
        </div>
    @endif

    <div class="plate flex flex-wrap items-center gap-2 p-3">
        <div class="relative min-w-[200px] flex-1">
            <x-ikon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-dim" />
            <input type="search" wire:model.live.debounce.400ms="cari" class="field pl-9"
                   placeholder="Cari nama, surel, atau telepon…" aria-label="Cari pelanggan">
        </div>

        <select wire:model.live="peran" class="field w-auto" aria-label="Saring peran">
            <option value="semua">Semua peran</option>
            <option value="customer">Pelanggan</option>
            <option value="admin">Admin</option>
            <option value="super_admin">Super Admin</option>
        </select>

        <select wire:model.live="status" class="field w-auto" aria-label="Saring status">
            <option value="semua">Semua status</option>
            <option value="aktif">Aktif</option>
            <option value="diblokir">Diblokir</option>
        </select>

        @if ($cari !== '' || $peran !== 'semua' || $status !== 'semua')
            <a href="{{ route('admin.customers.index') }}" class="btn btn-ghost btn-sm">
                <x-ikon name="close" :size="14" />
                Bersihkan
            </a>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <div class="plate overflow-hidden">
            @if ($daftar->total() === 0)
                <div class="p-8 text-center">
                    <x-ikon name="users" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">Tidak ada akun yang cocok.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line-soft text-left">
                                <th class="part-no p-3 font-medium">Nama</th>
                                <th class="part-no p-3 font-medium">Peran</th>
                                <th class="part-no p-3 text-right font-medium">Pesanan</th>
                                <th class="part-no p-3 font-medium">Terakhir masuk</th>
                                <th class="p-3"><span class="visually-hidden">Tindakan</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $u)
                                <tr wire:key="user-{{ $u->id }}"
                                    class="border-b border-line-soft last:border-0 hover:bg-sunken {{ $dipilih === $u->id ? 'bg-sunken' : '' }}">
                                    <td class="p-3">
                                        <button type="button" wire:click="buka({{ $u->id }})" class="text-left">
                                            <span class="flex items-center gap-2">
                                                <span class="truncate font-medium">{{ $u->name }}</span>
                                                @if ($u->is_blocked)
                                                    <span class="rounded-full bg-danger/12 px-1.5 py-0.5 text-xs text-danger">diblokir</span>
                                                @endif
                                                @if (auth()->id() === $u->id)
                                                    <span class="rounded-full bg-accent/12 px-1.5 py-0.5 text-xs text-accent">Anda</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block truncate text-xs text-dim">{{ $u->email }}</span>
                                        </button>
                                    </td>

                                    <td class="p-3">
                                        @if (auth()->user()?->role->bisaUbahKredensial() && auth()->id() !== $u->id)
                                            <select wire:change="ubahPeran({{ $u->id }}, $event.target.value)"
                                                    class="field w-auto py-1 text-xs" aria-label="Peran {{ $u->name }}">
                                                <option value="customer" @selected($u->role->value === 'customer')>Pelanggan</option>
                                                <option value="admin" @selected($u->role->value === 'admin')>Admin</option>
                                                <option value="super_admin" @selected($u->role->value === 'super_admin')>Super Admin</option>
                                            </select>
                                        @else
                                            <span class="text-xs text-muted">{{ $u->role->label() }}</span>
                                        @endif
                                    </td>

                                    <td class="p-3 text-right">
                                        @if ($u->orders_count > 0)
                                            <span class="tech">{{ $u->orders_count }}</span>
                                            <span class="block text-xs text-dim">{{ rupiah($u->total_belanja ?? 0) }}</span>
                                        @else
                                            <span class="tech text-dim">—</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        @if ($u->last_login_at)
                                            <span class="text-xs text-muted">{{ $u->last_login_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-xs text-dim">belum pernah</span>
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @unless (auth()->id() === $u->id)
                                                <button type="button" wire:click="ubahBlokir({{ $u->id }})"
                                                        class="btn-icon h-8 w-8 {{ $u->is_blocked ? 'text-danger' : '' }}"
                                                        title="{{ $u->is_blocked ? 'Buka blokir' : 'Blokir' }}">
                                                    <x-ikon name="{{ $u->is_blocked ? 'lock' : 'shield' }}" :size="15" />
                                                    <span class="visually-hidden">{{ $u->is_blocked ? 'Buka blokir' : 'Blokir' }}</span>
                                                </button>
                                                <button type="button" wire:click="hapus({{ $u->id }})"
                                                        wire:confirm="Hapus akun {{ $u->name }}?"
                                                        class="btn-icon h-8 w-8 hover:text-danger" title="Hapus">
                                                    <x-ikon name="trash" :size="15" />
                                                    <span class="visually-hidden">Hapus</span>
                                                </button>
                                            @endunless
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

        {{-- Rincian --}}
        <div class="plate h-fit p-4 lg:sticky lg:top-20">
            @if ($rinci === null)
                <div class="py-10 text-center">
                    <x-ikon name="users" :size="28" class="mx-auto text-dim" />
                    <p class="mt-3 text-sm text-muted">Pilih satu akun untuk melihat riwayatnya.</p>
                </div>
            @else
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="part-no">Akun #{{ $rinci->id }}</p>
                        <h2 class="mt-1 font-display text-lg font-semibold">{{ $rinci->name }}</h2>
                        <p class="mt-0.5 text-xs text-dim">{{ $rinci->email }}</p>
                    </div>
                    <button type="button" wire:click="tutup" class="btn-icon h-8 w-8" title="Tutup">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">Tutup</span>
                    </button>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">Peran</dt>
                        <dd>{{ $rinci->role->label() }}</dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">Status</dt>
                        <dd class="{{ $rinci->is_blocked ? 'text-danger' : 'text-ok' }}">
                            {{ $rinci->is_blocked ? 'Diblokir' : 'Aktif' }}
                        </dd>
                    </div>
                    @if ($rinci->phone_number)
                        <div class="flex gap-3">
                            <dt class="part-no w-24 flex-none pt-0.5">Telepon</dt>
                            <dd>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $rinci->phone_number) }}"
                                   target="_blank" rel="noopener" class="text-accent hover:underline">
                                    {{ $rinci->phone_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">Dua faktor</dt>
                        <dd class="{{ $rinci->punyaDuaFaktor() ? 'text-ok' : 'text-dim' }}">
                            {{ $rinci->punyaDuaFaktor() ? 'Aktif' : 'Belum aktif' }}
                        </dd>
                    </div>
                    <div class="flex gap-3">
                        <dt class="part-no w-24 flex-none pt-0.5">Bergabung</dt>
                        <dd class="text-muted">{{ $rinci->created_at?->translatedFormat('d F Y') }}</dd>
                    </div>
                </dl>

                <div class="mt-4 border-t border-line-soft pt-4">
                    <p class="part-no">Pesanan terakhir</p>

                    @if ($pesananRinci->isEmpty())
                        <p class="mt-2 text-sm text-dim">Belum pernah memesan.</p>
                    @else
                        <ul class="mt-2 space-y-2">
                            @foreach ($pesananRinci as $p)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <a href="{{ route('admin.orders.show', $p->ulid) }}"
                                       class="tech text-accent hover:underline">{{ $p->order_number }}</a>
                                    <span class="text-xs text-dim">{{ $p->created_at?->format('d/m/Y') }}</span>
                                    <span class="tech">{{ rupiah($p->grand_total) }}</span>
                                    <span class="rounded-full px-1.5 py-0.5 text-xs
                                                 {{ $p->status->value === 'completed' ? 'bg-ok/12 text-ok' : 'bg-sunken text-muted' }}">
                                        {{ $p->status->label() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
