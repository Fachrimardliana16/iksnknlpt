<?php

namespace App\Http\Middleware;

use App\Enums\PeranPengguna;
use App\Support\Keamanan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang panel admin.
 *
 * Pemeriksaan dilakukan di server, bukan dengan menyembunyikan tombol di
 * antarmuka. Peran Pelanggan yang menembus rute admin ditolak dan dicatat.
 */
class PastikanAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Blokir alamat diperiksa lebih dulu, sebelum identitas.
        //
        // Termasuk untuk pengguna yang sudah masuk. Blokir berlaku satu jam
        // dan bertambah sendiri saat ambang kegagalan terlampaui; sesi yang
        // masih hidup dari alamat yang sedang diblokir justru keadaan yang
        // paling perlu ditutup.
        //
        // Karena panel ikut tertutup, halaman buka-blokir di dalamnya tidak
        // dapat dicapai saat blokir sedang berlaku. Jalan daruratnya ada di
        // luar panel: perintah `php artisan iksan:buka-blokir`, yang hanya
        // dapat dijalankan oleh orang yang memang punya akses ke server.
        if (Keamanan::ipDiblokir($request)) {
            Keamanan::catat(\App\Enums\TipeKejadianKeamanan::IpDiblokir, $request, $request->user()?->email, $request->user()?->id, 'Alamat diblokir mencoba membuka panel');

            abort(403, 'Akses dari jaringan ini diblokir sementara.');
        }

        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        $peran = $user->role instanceof PeranPengguna
            ? $user->role
            : PeranPengguna::tryFrom((string) $user->role);

        if ($peran === null || ! $peran->bisaMasukPanel()) {
            Keamanan::catat(\App\Enums\TipeKejadianKeamanan::AksesTidakSah, $request, $user->email, $user->id, 'Pelanggan mencoba membuka '.$request->path());

            // Ditandai supaya middleware CatatKeamanan tidak mencatat
            // kejadian yang sama untuk kedua kalinya.
            return CatatKeamanan::tandaiSudahDicatat(
                response('Akun ini tidak memiliki akses ke panel admin.', 403)
            );
        }

        if ($user->is_blocked) {
            abort(403, 'Akun ini diblokir.');
        }

        // Sesi admin berumur pendek. Sesi yang terlalu lama dibiarkan terbuka
        // adalah risiko terbesar pada panel yang hanya dipakai di satu meja.
        $terakhir = $request->session()->get('admin.terakhir_aktif');

        if ($terakhir !== null && now()->timestamp - (int) $terakhir > config('session.lifetime') * 60) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('admin.login'))->with('galat', 'Sesi berakhir. Silakan masuk lagi.');
        }

        $request->session()->put('admin.terakhir_aktif', now()->timestamp);

        return $next($request);
    }
}
