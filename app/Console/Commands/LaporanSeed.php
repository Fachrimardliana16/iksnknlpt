<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LaporanSeed extends Command
{
    protected $signature = 'iksan:laporan-seed {--tulis= : Simpan keluaran JSON ke berkas ini}';

    protected $description = 'Cetak jumlah baris dan pemeriksaan khusus setelah seeding';

    public function handle(): int
    {
        ob_start();
        require base_path('tools/seed-report.php');
        $keluaran = (string) ob_get_clean();

        $this->line($keluaran);

        $tujuan = $this->option('tulis');

        if (is_string($tujuan) && $tujuan !== '') {
            $path = str_starts_with($tujuan, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:#', $tujuan)
                ? $tujuan
                : base_path($tujuan);

            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, $keluaran);
        }

        return self::SUCCESS;
    }
}
