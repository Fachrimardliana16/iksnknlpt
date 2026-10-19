<?php

namespace App\Http\Controllers\Admin;

use App\Models\Payment;
use App\Services\Pembayaran\PembayaranManual;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengirim berkas bukti transfer (checklist F10-09).
 *
 * Bukti transfer memuat nama pengirim, nomor rekening, dan nominal — data yang
 * cukup untuk menyalahgunakan rekening. Berkasnya disimpan di disk privat dan
 * TIDAK dapat diambil hanya dengan mengetahui alamatnya.
 *
 * Aksesnya lewat URL bertanda tangan berumur 15 menit. Tanda tangan itu
 * memastikan tautan yang tersalin ke tempat lain akan kedaluwarsa dengan
 * sendirinya, dan tidak dapat ditebak. Rute ini juga dilindungi middleware
 * admin, sehingga tanda tangan yang sah sekalipun tidak cukup tanpa sesi panel.
 */
class BuktiPembayaran
{
    public function __invoke(Request $request, Payment $payment): StreamedResponse|BinaryFileResponse
    {
        // Diperiksa lebih dulu: `signed` saja tidak cukup untuk berkas yang
        // bersifat pribadi.
        abort_unless(auth()->check() && auth()->user()->role->bisaMasukPanel(), 403);

        abort_if(blank($payment->proof_path), 404, 'Bukti tidak ada untuk pembayaran ini.');

        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        abort_unless($disk->exists($payment->proof_path), 404, 'Berkas bukti tidak ditemukan di penyimpanan.');

        $jalur = $disk->path($payment->proof_path);
        $mime = @mime_content_type($jalur) ?: 'application/octet-stream';

        // Bukti transfer bukan halaman web. Disajikan sebagai lampiran supaya
        // tidak pernah dijalankan sebagai dokumen di asal aplikasi ini.
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
            basename($payment->proof_path),
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }
}
