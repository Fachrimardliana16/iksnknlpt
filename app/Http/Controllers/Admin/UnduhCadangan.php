<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengunduh satu berkas cadangan basis data.
 *
 * Cadangan adalah salinan SELURUH basis data — pesanan, alamat pengiriman,
 * nomor telepon, dan surel pelanggan. Berkasnya karena itu diperlakukan seperti
 * bukti pembayaran, bukan seperti logo situs:
 *
 *   - TIDAK pernah disajikan dari disk publik. Tidak ada URL permanen yang
 *     bisa ditebak, dan tautan yang tersalin ke tempat lain tidak berguna.
 *   - Aksesnya lewat URL bertanda tangan berumur 15 menit.
 *   - Rute ini juga menolak tamu dan peran Pelanggan di dalam controllernya,
 *     karena tanda tangan yang sah sekalipun tidak cukup untuk berkas sebesar
 *     ini.
 *
 * Diunduh sebagai lampiran, bukan dibuka di peramban: berkas .sqlite yang
 * dibuka langsung tidak berarti apa-apa bagi yang mengunduh, dan menyajikannya
 * sebagai dokumen membuka kemungkinan berkas disalahgunakan sebagai dokumen
 * aktif di asal aplikasi ini.
 */
class UnduhCadangan
{
    public function __invoke(Request $request, string $berkas): StreamedResponse|BinaryFileResponse
    {
        abort_unless(auth()->check() && auth()->user()->role->bisaMasukPanel(), 403);

        // Membuat cadangan menulis berkas di server, jadi mengunduhnya pun
        // dibatasi pada peran yang sama.
        abort_unless(auth()->user()->role->bisaUbahKredensial(), 403);

        // Nama berkas datang dari URL dan hanya boleh berupa nama, bukan jalur.
        // Tanpa pemeriksaan ini, "../.env" akan keluar dari folder cadangan.
        $nama = basename($berkas);

        if ($nama !== $berkas || ! preg_match('/^iksan-[\w.-]+\.sqlite$/', $nama)) {
            abort(404, 'Nama cadangan tidak sah.');
        }

        $folder = realpath(storage_path('app/backup'));

        if ($folder === false) {
            abort(404, 'Folder cadangan belum ada.');
        }

        $jalur = realpath($folder.DIRECTORY_SEPARATOR.$nama);

        // realpath mengikuti symlink dan menormalkan "..", jadi berkas di luar
        // folder cadangan tidak akan lolos walau namanya lolos pola di atas.
        abort_if($jalur === false || ! str_starts_with($jalur, $folder.DIRECTORY_SEPARATOR), 404, 'Cadangan tidak ditemukan.');

        abort_unless(is_file($jalur), 404, 'Cadangan tidak ditemukan.');

        $mime = @mime_content_type($jalur) ?: 'application/octet-stream';

        return response()->streamDownload(
            function () use ($jalur): void {
                $handle = fopen($jalur, 'rb');

                if ($handle === false) {
                    return;
                }

                while (! feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }

                fclose($handle);
            },
            $nama,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) filesize($jalur),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }
}
