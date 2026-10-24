<?php

namespace App\Livewire\Admin;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pengguna & Peran — halaman khusus akun panel.
 *
 * Berbeda dari halaman Pelanggan: di sana yang ditampilkan pembeli, di sini
 * yang ditampilkan orang yang boleh masuk panel. Keduanya sengaja dipisah
 * karena pertanyaannya berbeda — "siapa yang belanja" dan "siapa yang boleh
 * mengubah toko" tidak sebaiknya dijawab dari satu daftar panjang.
 *
 * Perubahan peran dan blokir memakai aturan yang SAMA dengan halaman
 * Pelanggan, bukan salinan yang bisa menyimpang: tidak boleh menaikkan
 * peran sendiri, admin biasa tidak boleh menyentuh super admin, dan super
 * admin terakhir tidak boleh diturunkan. Aturan itu ditegakkan di server
 * lewat KebijakanPanel, bukan disembunyikan dari antarmuka — permintaan
 * Livewire dapat dikirim langsung tanpa melewati tombol.
 */
#[Layout('layouts.admin')]
class PenggunaPeran extends Component
{
    /** Akun yang sedang dibuka di panel rincian. */
    public ?int $dipilih = null;

    public ?string $pesan = null;

    /** Akun panel: peran selain Pelanggan. */
    public function akunPanel(): \Illuminate\Contracts\Pagination\Paginator
    {
        return User::query()
            ->whereIn('role', [
                PeranPengguna::Admin->value,
                PeranPengguna::SuperAdmin->value,
            ])
            ->withCount('orders')
            ->orderBy('role')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /** Jumlah akun per peran, untuk ringkasan di kepala halaman. */
    public function hitungan(): array
    {
        return [
            PeranPengguna::Admin->value => User::query()->where('role', PeranPengguna::Admin->value)->count(),
            PeranPengguna::SuperAdmin->value => User::query()->where('role', PeranPengguna::SuperAdmin->value)->count(),
            PeranPengguna::Customer->value => User::query()->where('role', PeranPengguna::Customer->value)->count(),
        ];
    }

    /**
     * Matriks kewenangan: apa yang boleh dilakukan tiap peran.
     *
     * Diambil dari KebijakanPanel supaya tampilan tidak pernah berbeda dari
     * yang benar-benar ditegakkan di server. Menulis daftar ini dua kali —
     * sekali di kebijakan, sekali di tampilan — berarti keduanya akan
     * menyimpang pada perubahan berikutnya.
     *
     * Peran dihitung lewat Gate dengan pengguna sungguhan dari basis data.
     * Membuat User dari larik tidak cukup: kolom `role` ber-cast enum, dan
     * cast hanya berjalan saat model dibaca dari basis data — bukan saat
     * dibuat dengan `new User([...])`. Akhirnya setiap pemeriksaan mengembalikan
     * false dan matriks tampil kosong tanpa gejala kesalahan.
     *
     * @return array<int, array{tindakan: string, admin: bool, super: bool}>
     */
    public function matriks(): array
    {
        $ambil = fn (string $peran): ?User => User::query()->where('role', $peran)->first();

        $admin = $ambil(PeranPengguna::Admin->value);
        $super = $ambil(PeranPengguna::SuperAdmin->value);

        $izin = fn (?User $u, string $kemampuan): bool => $u !== null
            && Gate::forUser($u)->allows($kemampuan, User::class);

        return [
            ['tindakan' => 'Kelola produk, kategori, hero, FAQ', 'admin' => $izin($admin, 'kelola'), 'super' => $izin($super, 'kelola')],
            ['tindakan' => 'Proses pesanan, cetak resi', 'admin' => $izin($admin, 'kelola'), 'super' => $izin($super, 'kelola')],
            ['tindakan' => 'Jawab pesan pelanggan', 'admin' => $izin($admin, 'kelola'), 'super' => $izin($super, 'kelola')],
            ['tindakan' => 'Ubah pengaturan situs & kredensial pembayaran', 'admin' => $izin($admin, 'aturSitus'), 'super' => $izin($super, 'aturSitus')],
            ['tindakan' => 'Buka blokir alamat IP', 'admin' => $izin($admin, 'bukaBlokir'), 'super' => $izin($super, 'bukaBlokir')],
            ['tindakan' => 'Ubah peran & kelola akun panel', 'admin' => $izin($admin, 'kelolaAkun'), 'super' => $izin($super, 'kelolaAkun')],
        ];
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
     * Mengubah peran akun panel.
     *
     * Aturan lengkap ada di halaman Pelanggan; di sini yang diperiksa adalah
     * hal yang khusus untuk halaman ini: akun yang sedang dipakai tidak boleh
     * diubah perannya. Bila aturan di Pelanggan berubah, ubah di sana — bukan
     * di sini — supaya hanya ada satu tempat yang perlu diperiksa.
     */
    public function ubahPeran(int $id, string $peranBaru): void
    {
        $this->authorize('kelolaAkun', User::class);

        $saya = auth()->user();
        $tujuan = PeranPengguna::tryFrom($peranBaru);

        if ($saya === null || $tujuan === null) {
            return;
        }

        // Halaman ini hanya mengelola akun panel. Menjadikan seseorang
        // Pelanggan dari sini berarti menurunkannya keluar dari panel — itu
        // tindakan halaman Pelanggan, bukan halaman ini.
        if ($tujuan === PeranPengguna::Customer) {
            $this->addError('umum', 'Menurunkan ke Pelanggan dilakukan dari halaman Pelanggan.');

            return;
        }

        if ($saya->id === $id) {
            $this->addError('umum', 'Peran akun sendiri tidak dapat diubah.');

            return;
        }

        $pengguna = User::query()->findOrFail($id);

        if ($pengguna->role === PeranPengguna::Customer) {
            $this->addError('umum', 'Akun pelanggan dinaikkan dari halaman Pelanggan.');

            return;
        }

        if (! $saya->role->bisaUbahKredensial()) {
            $this->addError('umum', 'Hanya super admin yang dapat mengubah peran.');

            return;
        }

        if ($pengguna->role->bisaUbahKredensial() && ! $tujuan->bisaUbahKredensial()
            && User::query()->where('role', PeranPengguna::SuperAdmin->value)->count() <= 1) {
            $this->addError('umum', 'Ini super admin terakhir. Perannya tidak dapat diturunkan.');

            return;
        }

        $lama = $pengguna->role;
        $pengguna->update(['role' => $tujuan]);

        CatatanAktivitas::catat('pengguna.peran', [
            'dari' => $lama->value,
            'ke' => $tujuan->value,
        ], $saya->id, $pengguna);

        $this->pesan = 'Peran diubah menjadi '.$tujuan->label().'.';
    }

    public function ubahBlokir(int $id): void
    {
        $this->authorize('kelolaAkun', User::class);

        $saya = auth()->user();

        if ($saya === null) {
            return;
        }

        $pengguna = User::query()->findOrFail($id);

        if ($saya->id === $pengguna->id) {
            $this->addError('umum', 'Akun yang sedang dipakai tidak dapat diblokir.');

            return;
        }

        if (! $saya->role->bisaUbahKredensial() && $pengguna->role->bisaUbahKredensial()) {
            $this->addError('umum', 'Hanya super admin yang dapat memblokir super admin lain.');

            return;
        }

        if (! $pengguna->is_blocked && $pengguna->role->bisaUbahKredensial()
            && User::query()->where('role', PeranPengguna::SuperAdmin->value)->count() <= 1) {
            $this->addError('umum', 'Ini super admin terakhir. Memblokirnya membuat panel tidak dapat dikelola.');

            return;
        }

        $pengguna->update(['is_blocked' => ! $pengguna->is_blocked]);

        CatatanAktivitas::catat(
            $pengguna->is_blocked ? 'pengguna.diblokir' : 'pengguna.dibuka',
            ['email_hash' => $pengguna->email_bidx],
            $saya->id,
            $pengguna,
        );

        $this->pesan = $pengguna->is_blocked ? 'Akun diblokir.' : 'Blokir akun dibuka.';
    }

    public function render(): View
    {
        return view('livewire.admin.pengguna-peran', [
            'judul' => 'Pengguna & Peran',
            'bagian' => 'Akses',
            'akun' => $this->akunPanel(),
            'hitungan' => $this->hitungan(),
            'matriks' => $this->matriks(),
            'dipilihDetail' => $this->dipilih === null
                ? null
                : User::query()->withCount('orders')->find($this->dipilih),
        ]);
    }
}
