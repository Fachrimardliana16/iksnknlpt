<?php

namespace App\Livewire\Admin;

use App\Enums\TipeKejadianKeamanan;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SecurityLog;
use App\Support\CatatanAktivitas;
use App\Support\Keamanan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Riwayat kejadian keamanan (checklist F10-12).
 *
 * Alamat IP tidak pernah disimpan dalam bentuk asli, hanya sidik jarinya
 * (HMAC dengan kunci aplikasi). Konsekuensinya harus disampaikan terus terang
 * di halaman ini: admin tidak dapat melihat "192.168.1.5", yang ada hanyalah
 * sidik jari yang dapat dicocokkan dengan kejadian lain. Sidik jari itu tetap
 * berguna untuk membuka blokir, karena pembukaan blokir juga bekerja pada
 * sidik jari yang sama.
 *
 * Halaman ini juga menyediakan satu-satunya jalan membuka blokir IP dari dalam
 * aplikasi. Tanpa itu, IP pelanggan yang diblokir otomatis hanya dapat
 * dipulihkan lewat basis data langsung.
 */
#[Layout('layouts.admin')]
class KeamananPanel extends KomponenAdmin
{
    use WithPagination;

    #[Url(as: 'tipe', history: true)]
    public string $tipe = 'semua';

    #[Url(as: 'hari', history: true)]
    public string $hari = '7';

    public ?string $pesan = null;

    public ?string $galatUmum = null;

    /**
     * Kolom isian blokir manual.
     *
     * Dipakai lewat wire.set dari atribut Alpine, bukan wire:model, karena
     * nilainya berasal dari tempel-an (paste) dan tombol blokir membaca elemen
     * di sebelahnya. Menyimpannya sebagai properti berarti nilai yang sudah
     * ditempel tetap ada setelah halaman dirender ulang.
     */
    public string $hashBlokir = '';

    public int $jamBlokir = 24;

    public function updatedTipe(): void
    {
        $this->resetPage();
    }

    public function aturTipe(string $nilai): void
    {
        $sah = ['semua', ...array_column(TipeKejadianKeamanan::cases(), 'value')];
        $this->tipe = in_array($nilai, $sah, true) ? $nilai : 'semua';
        $this->resetPage();
    }

    public function aturHari(string $nilai): void
    {
        $this->hari = in_array($nilai, ['1', '7', '30', '90'], true) ? $nilai : '7';
        $this->resetPage();
    }

    /**
     * Membuka blokir satu alamat.
     *
     * Boleh dilakukan admin, bukan hanya super admin: blokir dipasang otomatis
     * oleh sistem setelah percobaan masuk berulang, dan pelanggan yang salah
     * diblokir perlu dilayani cepat. Yang tidak boleh adalah membuka blokir
     * tanpa jejak — karena itu setiap pembukaan dicatat.
     */
    public function bukaBlokir(string $hash): void
    {
        // Membuka blokir adalah kebalikan dari perlindungan brute force.
        // Menyalahgunakannya berarti mematikan perlindungan itu, jadi
        // tindakannya dibatasi pada super admin.
        $this->authorize('bukaBlokir', \App\Models\User::class);

        if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            $this->galatUmum = 'Sidik jari alamat tidak sah.';

            return;
        }

        $jumlah = Keamanan::bukaBlokir($hash);

        if ($jumlah === 0) {
            $this->galatUmum = 'Blokir untuk alamat ini sudah tidak ada.';

            return;
        }

        Keamanan::catat(
            TipeKejadianKeamanan::AksiAdmin,
            ringkasan: 'Blokir dibuka manual oleh '.auth()->user()?->name.': '.substr($hash, 0, 12),
        );

        CatatanAktivitas::catat('keamanan.blokir-dibuka', ['ip_hash' => substr($hash, 0, 16)]);

        $this->pesan = 'Blokir dibuka. Alamat itu dapat masuk kembali.';
    }

    /**
     * Memasang blokir manual.
     *
     * Masukan berupa sidik jari yang tersalin dari tabel kejadian, bukan alamat
     * IP. Alasannya: alamat asli tidak pernah disimpan, jadi menawarkan kolom
     * "masukkan IP" akan menyesatkan — nilai yang diketik tidak akan cocok
     * dengan apa pun.
     */
    public function blokirManual(string $hash, int $jam = 24): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            $this->galatUmum = 'Sidik jari alamat tidak sah.';

            return;
        }

        BlockedIp::query()->updateOrCreate(
            ['ip_hash' => $hash],
            [
                'reason' => 'Diblokir manual oleh '.auth()->user()?->name,
                'blocked_until' => now()->addHours(max(1, min($jam, 720))),
                'created_at' => now(),
            ],
        );

        Keamanan::catat(
            TipeKejadianKeamanan::IpDiblokir,
            ringkasan: 'Blokir manual '.$jam.' jam oleh '.auth()->user()?->name,
        );

        CatatanAktivitas::catat('keamanan.blokir-dipasang', [
            'ip_hash' => substr($hash, 0, 16),
            'jam' => $jam,
        ]);

        $this->pesan = 'Alamat diblokir selama '.$jam.' jam.';
    }

    public function bersihkanPercobaanGagal(): void
    {
        // Hanya percobaan yang sudah tidak terkunci. Menghapus yang masih
        // terkunci berarti membuka kesempatan menebak kata sandi lagi.
        $jumlah = LoginAttempt::query()
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->delete();

        CatatanAktivitas::catat('keamanan.percobaan-dibersihkan', ['jumlah' => $jumlah]);

        $this->pesan = $jumlah.' catatan percobaan masuk dibersihkan.';
    }

    /** Jumlah kejadian per jam, untuk grafik batang sederhana. */
    private function grafikPerJam(): Collection
    {
        $sejak = now()->subHours(24);

        $baris = SecurityLog::query()
            ->where('created_at', '>=', $sejak)
            ->selectRaw("strftime('%Y-%m-%d %H', created_at) as jam, count(*) as jumlah")
            ->groupBy('jam')
            ->pluck('jumlah', 'jam');

        $seri = collect();

        for ($i = 23; $i >= 0; $i--) {
            $waktu = now()->subHours($i);
            $kunci = $waktu->format('Y-m-d H');

            $seri->push([
                'jam' => $waktu->format('H'),
                'jumlah' => (int) ($baris[$kunci] ?? 0),
            ]);
        }

        return $seri;
    }

    public function render(): View
    {
        $sejak = now()->subDays((int) $this->hari);

        $daftar = SecurityLog::query()
            ->with('user')
            ->when($this->tipe !== 'semua', fn ($q) => $q->where('event_type', $this->tipe))
            ->where('created_at', '>=', $sejak)
            ->orderByDesc('id')
            ->paginate(50);

        $blokir = BlockedIp::query()
            ->orderByDesc('blocked_until')
            ->get()
            ->map(fn (BlockedIp $b) => [
                'hash' => $b->ip_hash,
                'alasan' => $b->reason,
                'sampai' => $b->blocked_until,
                'aktif' => $b->aktif(),
            ]);

        // Hitungan per jenis dalam rentang waktu yang sedang dilihat, supaya
        // tombol saringan menunjukkan angkanya.
        $perJenis = SecurityLog::query()
            ->where('created_at', '>=', $sejak)
            ->selectRaw('event_type, count(*) as jumlah')
            ->groupBy('event_type')
            ->pluck('jumlah', 'event_type');

        $percobaanTerkunci = LoginAttempt::query()
            ->where('locked_until', '>', now())
            ->count();

        $grafik = $this->grafikPerJam();

        return view('livewire.admin.keamanan', [
            'daftar' => $daftar,
            'blokir' => $blokir,
            'perJenis' => $perJenis,
            'grafik' => $grafik,
            'percobaanTerkunci' => $percobaanTerkunci,
            'puncakGrafik' => max(1, (int) $grafik->max('jumlah')),
            'jenis' => TipeKejadianKeamanan::cases(),
            'ringkas' => [
                'total' => SecurityLog::query()->where('created_at', '>=', $sejak)->count(),
                'gagalMasuk' => (int) ($perJenis[TipeKejadianKeamanan::LoginGagal->value] ?? 0),
                'blokirAktif' => $blokir->where('aktif', true)->count(),
                'aksesTidakSah' => (int) ($perJenis[TipeKejadianKeamanan::AksesTidakSah->value] ?? 0),
            ],
        ])->layout('layouts.admin', ['judul' => 'Keamanan', 'bagian' => 'Situs']);
    }
}
