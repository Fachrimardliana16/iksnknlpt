<?php

namespace App\Livewire\Admin;

use App\Enums\PeranPengguna;
use App\Models\Order;
use App\Models\User;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pelanggan dan akun panel.
 *
 * Nama dan surel terenkripsi di tingkat aplikasi, jadi pencarian dilakukan
 * lewat sidik jari email (`email_bidx`) untuk surel, dan lewat penyaringan di
 * PHP untuk nama. Cara itu dipilih karena sidik jari bersifat tepat-cocok:
 * HMAC tidak dapat di-LIKE sebagian. Menyaring nama di PHP membatasi jumlah
 * baris yang diproses dengan memakai halaman yang sedang dilihat.
 *
 * Larangan yang ditegakkan di server, bukan hanya disembunyikan di antarmuka:
 *
 *   - Akun sendiri tidak dapat diblokir atau diturunkan perannya. Mengunci
 *     diri sendiri dari panel tidak dapat dibatalkan dari dalam panel.
 *   - Admin tidak dapat mengubah peran super admin. Tanpa aturan ini, seorang
 *     admin dapat menaikkan dirinya sendiri menjadi super admin.
 *   - Super admin terakhir tidak dapat diturunkan. Tanpa itu, tidak ada lagi
 *     yang boleh mengubah kredensial pembayaran dan mengelola admin.
 */
#[Layout('layouts.admin')]
class Pelanggan extends Component
{
    use WithPagination;

    #[Url(as: 'cari', history: true)]
    public string $cari = '';

    #[Url(as: 'peran', history: true)]
    public string $peran = 'semua';

    #[Url(as: 'status', history: true)]
    public string $status = 'semua';

    public ?int $dipilih = null;

    public ?string $pesan = null;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function aturPeran(string $nilai): void
    {
        $this->peran = in_array($nilai, ['semua', ...PeranPengguna::values()], true) ? $nilai : 'semua';
        $this->resetPage();
    }

    public function aturStatus(string $nilai): void
    {
        $this->status = in_array($nilai, ['semua', 'aktif', 'diblokir'], true) ? $nilai : 'semua';
        $this->resetPage();
    }

    public function buka(int $id): void
    {
        $this->dipilih = $id;
    }

    public function tutup(): void
    {
        $this->dipilih = null;
    }

    /**
     * Memblokir atau membuka blokir akun.
     *
     * Akun sendiri ditolak. Memblokir diri sendiri berarti kehilangan akses
     * panel, dan pemulihannya harus dilakukan dari luar aplikasi.
     */
    public function ubahBlokir(int $id): void
    {
        $pengguna = User::query()->findOrFail($id);
        $saya = auth()->user();

        if ($saya !== null && $saya->id === $pengguna->id) {
            $this->addError('umum', 'Akun yang sedang dipakai tidak dapat diblokir.');

            return;
        }

        // Admin tidak boleh memblokir super admin.
        if ($saya !== null && ! $saya->role->bisaUbahKredensial() && $pengguna->role->bisaUbahKredensial()) {
            $this->addError('umum', 'Hanya super admin yang dapat memblokir super admin lain.');

            return;
        }

        // Super admin terakhir tidak dapat diblokir, sama seperti tidak dapat
        // diturunkan: tanpa satu pun super admin, tidak ada yang bisa mengelola
        // admin dan kredensial pembayaran.
        if (! $pengguna->is_blocked && $pengguna->role->bisaUbahKredensial() && $this->superAdminTersisa() <= 1) {
            $this->addError('umum', 'Ini super admin terakhir. Memblokirnya membuat panel tidak dapat dikelola.');

            return;
        }

        $pengguna->update(['is_blocked' => ! $pengguna->is_blocked]);

        CatatanAktivitas::catat(
            $pengguna->is_blocked ? 'pengguna.diblokir' : 'pengguna.dibuka',
            ['email_hash' => $pengguna->email_bidx],
            auth()->id(),
            $pengguna,
        );

        $this->pesan = $pengguna->is_blocked ? 'Akun diblokir.' : 'Blokir akun dibuka.';
    }

    /**
     * Mengubah peran akun.
     *
     * Batasannya diperiksa di server: admin tidak boleh menyentuh akun super
     * admin, tidak boleh menaikkan siapa pun menjadi super admin, dan tidak
     * ada yang boleh menurunkan super admin terakhir.
     */
    public function ubahPeran(int $id, string $peranBaru): void
    {
        $saya = auth()->user();

        if ($saya === null) {
            return;
        }

        $tujuan = PeranPengguna::tryFrom($peranBaru);

        if ($tujuan === null) {
            $this->addError('umum', 'Peran tidak dikenal.');

            return;
        }

        $pengguna = User::query()->findOrFail($id);

        if ($saya->id === $pengguna->id) {
            $this->addError('umum', 'Peran akun sendiri tidak dapat diubah.');

            return;
        }

        if (! $saya->role->bisaUbahKredensial()) {
            $this->addError('umum', 'Hanya super admin yang dapat mengubah peran.');

            return;
        }

        if ($pengguna->role === $tujuan) {
            return;
        }

        // Menurunkan super admin terakhir akan membuat panel kehilangan satu-
        // satunya akun yang boleh mengelola admin dan kredensial pembayaran.
        if ($pengguna->role->bisaUbahKredensial() && ! $tujuan->bisaUbahKredensial() && $this->superAdminTersisa() <= 1) {
            $this->addError('umum', 'Ini super admin terakhir. Perannya tidak dapat diturunkan.');

            return;
        }

        $lama = $pengguna->role;

        $pengguna->update(['role' => $tujuan]);

        CatatanAktivitas::catat('pengguna.peran', [
            'dari' => $lama->value,
            'ke' => $tujuan->value,
        ], auth()->id(), $pengguna);

        $this->pesan = 'Peran diubah menjadi '.$tujuan->label().'.';
    }

    public function hapus(int $id): void
    {
        $saya = auth()->user();
        $pengguna = User::query()->withCount('orders')->findOrFail($id);

        if ($saya !== null && $saya->id === $pengguna->id) {
            $this->addError('umum', 'Akun yang sedang dipakai tidak dapat dihapus.');

            return;
        }

        // Akun yang pernah memesan tidak dihapus: riwayat pesanan menunjuk ke
        // sana, dan menghapusnya membuat laporan penjualan kehilangan pemilik.
        if ($pengguna->orders_count > 0) {
            $this->addError(
                'umum',
                'Pelanggan ini punya '.$pengguna->orders_count.' pesanan. Blokir saja, jangan dihapus.',
            );

            return;
        }

        if ($pengguna->role->bisaUbahKredensial() && $this->superAdminTersisa() <= 1) {
            $this->addError('umum', 'Ini super admin terakhir dan tidak dapat dihapus.');

            return;
        }

        CatatanAktivitas::catat('pengguna.dihapus', [
            'peran' => $pengguna->role->value,
        ], $saya?->id, $pengguna);

        $pengguna->delete();

        $this->dipilih = null;
        $this->pesan = 'Akun dihapus.';
    }

    private function superAdminTersisa(): int
    {
        return User::query()->where('role', PeranPengguna::SuperAdmin->value)->count();
    }

    public function render(): View
    {
        $daftar = User::query()
            ->when($this->peran !== 'semua', fn ($q) => $q->where('role', $this->peran))
            ->when($this->status === 'aktif', fn ($q) => $q->where('is_blocked', false))
            ->when($this->status === 'diblokir', fn ($q) => $q->where('is_blocked', true))
            ->withCount('orders')
            ->withSum('orders as total_belanja', 'grand_total')
            ->orderByDesc('id')
            ->paginate(20);

        // Pencarian nama dilakukan setelah halaman diambil, karena kolomnya
        // terenkripsi dan tidak dapat di-LIKE. Pencarian surel memakai sidik
        // jari, yang bersifat tepat-cocok.
        if ($this->cari !== '') {
            $kata = mb_strtolower($this->cari);

            $cocok = User::query()
                ->denganEmail($this->cari)
                ->pluck('id')
                ->all();

            $daftar->setCollection(
                $daftar->getCollection()
                    ->filter(fn (User $u) => in_array($u->id, $cocok, true)
                        || str_contains(mb_strtolower((string) $u->name), $kata)
                        || str_contains(mb_strtolower((string) $u->phone_number), $kata))
                    ->values()
            );
        }

        return view('livewire.admin.pelanggan', [
            'daftar' => $daftar,
            'rinci' => $this->dipilih === null
                ? null
                : User::query()->withCount('orders')->find($this->dipilih),
            'pesananRinci' => $this->dipilih === null
                ? collect()
                : Order::query()
                    ->where('user_id', $this->dipilih)
                    ->orderByDesc('id')
                    ->take(10)
                    ->get(),
            'jumlah' => [
                'semua' => User::query()->count(),
                'pelanggan' => User::query()->where('role', PeranPengguna::Customer->value)->count(),
                'panel' => User::query()->whereIn('role', [PeranPengguna::Admin->value, PeranPengguna::SuperAdmin->value])->count(),
                'diblokir' => User::query()->where('is_blocked', true)->count(),
            ],
            'superAdminTerakhir' => $this->superAdminTersisa(),
        ])->layout('layouts.admin', ['judul' => 'Pelanggan', 'bagian' => 'Penjualan']);
    }
}
