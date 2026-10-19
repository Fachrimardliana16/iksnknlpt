<?php

namespace App\Http\Middleware;

use App\Support\Keamanan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak seluruh permintaan dari alamat yang sedang diblokir (checklist F2-06).
 *
 * Sebelum ini pemeriksaan blokir hanya ada di dalam gerbang panel admin.
 * Akibatnya alamat yang diblokir karena brute force masih bebas menghajar
 * halaman masuk lewat jalur lain, mengunggah bukti pembayaran, atau mengisi
 * formulir kontak — persis tindakan yang membuatnya diblokir.
 *
 * PENGECUALIAN YANG DISENGAJA
 *
 * Pengguna panel yang SUDAH masuk tidak diblokir. Alasannya praktis dan
 * disengaja: halaman untuk membuka blokir ada di dalam panel. Kalau blokir
 * ikut menutup panel, kesalahan satu kali — sandi salah berulang dari kantor
 * sendiri — mengunci admin dari satu-satunya tempat untuk memperbaikinya.
 * Identitas pengguna panel sudah dibuktikan lewat sesi dan perannya, jadi
 * blokir di sini memang ditujukan untuk permintaan yang belum terbukti.
 *
 * Melewati blokir selalu dicatat, sehingga bukan jalan yang tak terlihat.
 */
class BlokirIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Keamanan::ipDiblokir($request)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user !== null) {
            Keamanan::catat(
                \App\Enums\TipeKejadianKeamanan::IpDiblokir,
                $request,
                $user->email,
                $user->id,
                'Pengguna panel yang sudah masuk melewati blokir alamat untuk '.$request->path(),
            );

            return $next($request);
        }

        // Permintaan Livewire dijawab dengan kode 403 yang dapat dibaca
        // antarmuka, bukan halaman HTML penuh yang akan tersisip ke DOM.
        if ($request->header('X-Livewire')) {
            return response()->json([
                'message' => 'Akses dari jaringan ini diblokir sementara.',
            ], 403);
        }

        abort(403, 'Akses dari jaringan ini diblokir sementara.');
    }
}
