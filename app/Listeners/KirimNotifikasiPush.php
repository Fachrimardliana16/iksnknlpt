<?php

namespace App\Listeners;

use App\Events\NotifikasiPanelMuncul;
use App\Support\NotifikasiPush;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mengirim notifikasi panel ke admin: push (dan surel cadangan).
 *
 * Diantrekan dengan sengaja. Mengirim push berarti membuka koneksi ke layanan
 * push (FCM/Mozilla/Apple) dan menunggu jawabannya; kalau dikerjakan di dalam
 * permintaan, admin yang menandai pesanan lunas ikut menunggu mesin pengirim.
 * Kegagalan pengiriman tidak boleh menunda apa pun.
 */
class KirimNotifikasiPush implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function handle(NotifikasiPanelMuncul $peristiwa): void
    {
        if ($peristiwa->jumlah <= 0) {
            return;
        }

        // Judul memuat jumlahnya: "3 hal perlu perhatian" lebih berguna daripada
        // "Ada notifikasi baru" — admin bisa memutuskan membuka panel atau
        // belum dari judulnya saja.
        $judul = $peristiwa->jumlah === 1
            ? 'Satu hal perlu perhatian'
            : $peristiwa->jumlah.' hal perlu perhatian';

        $isi = $peristiwa->rinci;

        NotifikasiPush::keSemuaAdmin(
            $judul,
            $isi,
            $peristiwa->url,
        );
    }

    public function failed(NotifikasiPanelMuncul $peristiwa, \Throwable $e): void
    {
        // Dicatat, tidak dilempar: notifikasi yang gagal adalah kabar yang
        // hilang, bukan pekerjaan yang rusak.
        report($e);
    }
}
