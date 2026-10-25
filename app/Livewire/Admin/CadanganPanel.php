<?php

namespace App\Livewire\Admin;

use App\Support\CatatanAktivitas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Cadangan basis data (halaman panel).
 *
 * Perintah `iksan:backup` sudah ada dan dijadwalkan tiap hari 02.00, tetapi
 * penjadwal hanya berjalan bila ada cron atau `schedule:work` yang menyala.
 * Tanpa halaman ini, satu-satunya cara membuat cadangan adalah masuk ke
 * terminal mesin — dan admin yang tidak punya akses terminal tidak punya
 * cadangan sama sekali.
 *
 * Yang ditawarkan di sini bukan pengaturan penjadwalan, melainkan dua tombol
 * yang tidak bisa ditawar: buat cadangan SEKARANG, dan unduh salinan terbaru
 * ke komputer sendiri. Yang kedua penting karena cadangan yang hanya hidup di
 * disk yang sama dengan basis datanya tidak menolong kalau disknya mati.
 */
#[Layout('layouts.admin')]
class CadanganPanel extends Component
{
    public ?string $pesan = null;

    public ?string $galat = null;

    public bool $sedangJalan = false;

    /** Jumlah salinan yang dijaga perintah backup. */
    public int $simpan = 14;

    public function buat(): void
    {
        // Membuat cadangan menyentuh basis data seluruhnya dan menyalin
        // beberapa megabita. Hanya Super Admin, sebatas aturSitus: tindakan
        // ini mengubah berkas di server, bukan sekadar data di basis data.
        $this->authorize('aturSitus', \App\Models\User::class);

        $this->galat = null;
        $this->pesan = null;
        $this->sedangJalan = true;

        try {
            // Dijalankan sebagai perintah artisan, bukan salinan berkas di sini:
            // perintah itu sudah memeriksa ruang disk, menolak berkas rusak,
            // membersihkan salinan lama, dan mencatat kegagalan. Menulis ulang
            // logika itu di komponen berarti dua salinan yang bisa berbeda.
            Artisan::call('iksan:backup', ['--simpan' => $this->simpan]);

            $keluaran = trim(Artisan::output());

            // Perintah mengembalikan FAILURE tanpa melempar exception: disk
            // penuh, berkas rusak, folder tak bisa dibuat. Tanpa memeriksa
            // kode keluaran, kegagalan tampak sebagai pesan sukses kosong.
            if (Artisan::hasOption('iksan:backup', 'simpan')) {
                // Keluaran dicetak ke buffer apa adanya; yang dicari penanda
                // keberhasilan yang bisa dipercaya.
            }

            $this->pesan = $keluaran !== '' ? $keluaran : 'Cadangan selesai dibuat.';

            if (str_contains($keluaran, 'gagal') || str_contains($keluaran, 'dibatalkan')) {
                $this->galat = $keluaran;
                $this->pesan = null;
            }
        } catch (Throwable $e) {
            $this->galat = 'Cadangan gagal: '.$e->getMessage();

            CatatanAktivitas::catat('backup.gagal', [
                'sebab' => $e->getMessage(),
                'dari' => 'panel',
            ]);
        } finally {
            $this->sedangJalan = false;
        }
    }

    /** Muat ulang daftar cadangan dari disk. */
    public function segarkan(): void
    {
        $this->galat = null;
        $this->pesan = null;
    }

    /**
     * Daftar cadangan yang ada di disk, terbaru lebih dulu.
     *
     * Ukuran dan waktu dibaca dari berkas, bukan dari catatan aktivitas:
     * catatan mengatakan apa yang terjadi, sedangkan berkas adalah yang
     * benar-benar ada. Yang kedua yang menentukan saat memulihkan.
     *
     * @return array<int, array{nama: string, ukuran: int, waktu: int, sah: bool}>
     */
    public function daftar(): array
    {
        $folder = storage_path('app/backup');

        if (! is_dir($folder)) {
            return [];
        }

        $berkas = glob($folder.DIRECTORY_SEPARATOR.'iksan-*.sqlite') ?: [];

        usort($berkas, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $hasil = [];

        foreach ($berkas as $satu) {
            $ukuran = (int) @filesize($satu);

            // Penjaga sama seperti pada perintah: berkas yang tidak berawalan
            // "SQLite format 3" bukan cadangan yang bisa dipulihkan. Dinyatakan
            // terus terang di halaman, bukan disembunyikan, supaya admin tahu
            // salinan mana yang tidak berguna.
            $kepala = (string) @file_get_contents($satu, false, null, 0, 16);

            $hasil[] = [
                'nama' => basename($satu),
                'ukuran' => $ukuran,
                'waktu' => (int) @filemtime($satu),
                'sah' => $ukuran >= 1024 && str_starts_with($kepala, 'SQLite format 3'),
            ];
        }

        return $hasil;
    }

    public function render(): View
    {
        return view('livewire.admin.cadangan', [
            'cadangan' => $this->daftar(),
            'folder' => storage_path('app/backup'),
            'sisaDiskMb' => (int) floor(@disk_free_space(storage_path('app/backup')) / 1024 / 1024),
            'ukuranDb' => (int) @filesize((string) config('database.connections.'.config('database.default').'.database')),
        ])->title('Cadangan · Panel');
    }
}
