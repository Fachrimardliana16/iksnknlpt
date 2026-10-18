<?php

namespace App\Console\Commands;

use App\Models\MediaUploadLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Membersihkan berkas karantina yang tertinggal (checklist F3-12).
 *
 * Berkas asli unggahan disimpan di folder karantina selama masa tenggang. Masa
 * itu ada karena dua alasan:
 *
 *   1. Pekerjaan antrean yang gagal sementara akan dicoba ulang, dan percobaan
 *      ulang membutuhkan berkasnya. Menghapusnya terlalu cepat berarti pengguna
 *      harus mengunggah ulang berkas yang sebenarnya baik-baik saja.
 *   2. Ketika pemrosesan gagal permanen, berkasnya disimpan sehari supaya
 *      penyebabnya masih dapat diperiksa, bukan hilang bersama berkasnya.
 *
 * Setelah masa itu lewat, berkasnya hanya mengambil ruang. Perintah ini
 * dijalankan penjadwal setiap hari.
 */
class BersihkanKarantina extends Command
{
    protected $signature = 'iksan:bersihkan-karantina
                            {--hari= : Umur minimum berkas sebelum dihapus}
                            {--uji : Tampilkan yang akan dihapus tanpa menghapus}';

    protected $description = 'Hapus berkas unggahan yang tertinggal di folder karantina';

    public function handle(): int
    {
        $hari = (int) ($this->option('hari') ?? config('iksan.uploads.quarantine_days', 1));
        $uji = (bool) $this->option('uji');

        $batas = now()->subDays($hari)->getTimestamp();
        $disk = Storage::disk('local');

        if (! $disk->exists('uploads/quarantine')) {
            $this->info('Folder karantina kosong.');
        }

        try {
            $daftar = $disk->files('uploads/quarantine');
        } catch (Throwable $e) {
            $this->error('Folder karantina tidak dapat dibaca: '.$e->getMessage());

            return self::FAILURE;
        }

        $terhapus = 0;
        $dilewati = 0;
        $akanDihapus = 0;

        foreach ($daftar as $jalur) {
            try {
                $waktu = $disk->lastModified($jalur);
            } catch (Throwable) {
                continue;
            }

            if ($waktu > $batas) {
                $dilewati++;

                continue;
            }

            if ($uji) {
                $akanDihapus++;
                $this->line('  akan dihapus: '.$jalur);

                continue;
            }

            try {
                $disk->delete($jalur);
                $terhapus++;
            } catch (Throwable $e) {
                $this->warn('  gagal menghapus '.$jalur.': '.$e->getMessage());
            }
        }

        $this->info(sprintf(
            '%s: %d berkas lebih tua dari %d hari, %d masih dalam masa tenggang.',
            $uji ? 'Uji' : 'Selesai',
            $uji ? $akanDihapus : $terhapus,
            $hari,
            $dilewati,
        ));

        // Baris log yang menggantung: pekerjaannya gagal permanen sehingga
        // berkasnya dihapus, tetapi barisnya tidak pernah mencatat kegagalan —
        // bisa terjadi bila proses mati sebelum failed() sempat dipanggil.
        // Ditandai supaya catatannya jujur.
        if (! $uji) {
            $menggantung = MediaUploadLog::query()
                ->whereIn('status', ['queued', 'processing'])
                ->where('created_at', '<', now()->subHours(6))
                ->update([
                    'status' => 'failed',
                    'rejection_reason' => 'Pemrosesan tidak pernah selesai dan berkas sementaranya sudah dibersihkan.',
                ]);

            if ($menggantung > 0) {
                $this->warn($menggantung.' catatan unggahan menggantung ditandai gagal.');
            }
        }

        return self::SUCCESS;
    }
}
