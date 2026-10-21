<?php

namespace App\Console\Commands;

use App\Support\CatatanAktivitas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cadangan basis data (checklist F10-13).
 *
 * Menyimpan 14 salinan terakhir dan menolak berjalan bila ruang disk tersisa
 * kurang dari 500 MB. Pemeriksaan ruang itu bukan kehati-hatian berlebihan:
 * cadangan yang gagal di tengah jalan karena disk penuh akan meninggalkan
 * berkas separuh yang tampak seperti cadangan sah, dan itu baru ketahuan saat
 * seseorang benar-benar mencoba memulihkannya.
 *
 * Berkas lama dihapus setelah cadangan baru berhasil ditulis, bukan sebelumnya.
 * Urutan itu penting: bila penulisan gagal, salinan sebelumnya masih utuh.
 */
class CadangkanBasisData extends Command
{
    protected $signature = 'iksan:backup
                            {--simpan=14 : Jumlah salinan terakhir yang disimpan}
                            {--min-disk-mb=500 : Ruang disk minimum yang harus tersisa}
                            {--folder= : Folder tujuan (bawaan storage/app/backup)}
                            {--basis-data= : Berkas basis data yang disalin (bawaan dari koneksi aktif)}';

    protected $description = 'Menyimpan cadangan basis data dan membersihkan salinan lama';

    public function handle(): int
    {
        $minDiskMb = (int) $this->option('min-disk-mb');
        $simpan = max(1, (int) $this->option('simpan'));

        // Folder dapat diganti lewat opsi, dan test WAJIB memakainya.
        //
        // Tanpa opsi ini, test "sisakan 14" harus menjalankan perintahnya di
        // folder cadangan ASLI sambil mengarahkan koneksi basis data ke berkas
        // kerja — dan pembersihan berkas di tengah siklus WAL dapat menghapus
        // isi database.sqlite. Itu pernah terjadi dua kali di mesin ini.
        $folder = (string) ($this->option('folder') ?: storage_path('app/backup'));

        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            $this->error('Folder cadangan tidak dapat dibuat: '.$folder);

            return self::FAILURE;
        }

        $sisaMb = (int) floor(@disk_free_space($folder) / 1024 / 1024);

        if ($sisaMb > 0 && $sisaMb < $minDiskMb) {
            $this->error(sprintf(
                'Ruang disk tersisa %d MB, di bawah batas %d MB. Cadangan dibatalkan.',
                $sisaMb,
                $minDiskMb,
            ));

            // Kegagalan ini dicatat sebagai kejadian, bukan hanya dicetak:
            // cadangan yang berhenti berjalan harus ketahuan, bukan menyusut
            // diam-diam sampai tidak ada salinan tersisa.
            CatatanAktivitas::catat('backup.gagal', [
                'sebab' => 'ruang disk kurang',
                'sisa_mb' => $sisaMb,
                'batas_mb' => $minDiskMb,
            ]);

            return self::FAILURE;
        }

        $nama = 'iksan-'.now()->format('Y-m-d_His').'.sqlite';
        $tujuan = $folder.DIRECTORY_SEPARATOR.$nama;

        // Berkas yang disalin dapat ditentukan lewat opsi. Dipakai test supaya
        // tidak perlu mengubah config koneksi global — mengubahnya di dalam
        // test pernah membuat test lain memakai basis data yang salah.
        $basisData = (string) ($this->option('basis-data')
            ?: config('database.connections.'.config('database.default').'.database'));

        if (! is_string($basisData) || ! is_file($basisData)) {
            $this->error('Berkas basis data tidak ditemukan: '.(string) $basisData);

            return self::FAILURE;
        }

        // Penjaga kerusakan: berkas 0 bita atau yang tidak berawalan "SQLite
        // format 3" TIDAK dicadangkan, dan tidak boleh menimpa cadangan sehat.
        // Tanpa penjaga ini, basis data yang sudah rusak tercadangkan seolah
        // sehat, lalu cadangan baik yang tersisa ikut terhapus oleh aturan
        // "sisakan 14" — persis yang pernah terjadi di mesin ini.
        $kepala = (string) @file_get_contents($basisData, false, null, 0, 16);

        if (filesize($basisData) < 1024 || ! str_starts_with($kepala, "SQLite format 3")) {
            $this->error('Berkas basis data tampak rusak ('.filesize($basisData).' bita). Cadangan dibatalkan.');

            CatatanAktivitas::catat('backup.gagal', [
                'sebab' => 'berkas basis data tidak sah',
                'ukuran' => filesize($basisData),
            ]);

            return self::FAILURE;
        }

        try {
            // Salinan mentah berkas, bukan dump SQL. Untuk SQLite ini justru
            // lebih aman: dump yang dijalankan sambil ada penulisan dapat
            // menghasilkan berkas yang tidak konsisten.
            if (! @copy($basisData, $tujuan)) {
                throw new \RuntimeException('Penyalinan berkas gagal.');
            }

            $ukuranMb = round(filesize($tujuan) / 1024 / 1024, 2);

            $this->info(sprintf('Cadangan tersimpan: %s (%.2f MB)', $nama, $ukuranMb));
        } catch (Throwable $e) {
            @unlink($tujuan);

            $this->error('Cadangan gagal: '.$e->getMessage());

            CatatanAktivitas::catat('backup.gagal', ['sebab' => $e->getMessage()]);

            return self::FAILURE;
        }

        $terhapus = $this->bersihkanLama($folder, $simpan);

        if ($terhapus > 0) {
            $this->line($terhapus.' cadangan lama dihapus.');
        }

        $tersisa = count(glob($folder.DIRECTORY_SEPARATOR.'iksan-*.sqlite') ?: []);
        $this->info('Total cadangan tersimpan: '.$tersisa.' dari '.$simpan.' yang dijaga.');

        CatatanAktivitas::catat('backup.berhasil', [
            'berkas' => $nama,
            'ukuran_mb' => $ukuranMb,
            'tersisa' => $tersisa,
        ]);

        return self::SUCCESS;
    }

    /**
     * Menyisakan sejumlah cadangan terbaru.
     *
     * Diurutkan menurut waktu berkas, bukan menurut namanya, supaya berkas yang
     * disalin dengan nama tidak lazim tetap diperlakukan menurut umurnya.
     */
    private function bersihkanLama(string $folder, int $simpan): int
    {
        $daftar = glob($folder.DIRECTORY_SEPARATOR.'iksan-*.sqlite') ?: [];

        if (count($daftar) <= $simpan) {
            return 0;
        }

        usort($daftar, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $terhapus = 0;

        foreach (array_slice($daftar, $simpan) as $lama) {
            if (@unlink($lama)) {
                $terhapus++;
            }
        }

        return $terhapus;
    }
}
