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
        if (Keamanan::ipDiblokir($request)) {
            Keamanan::catat(\App\Enums\TipeKejadianKeamanan::AksesTidakSah, $request, ringkasan: 'IP diblokir mencoba masuk panel');

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

            abort(403, 'Akun ini tidak memiliki akses ke panel admin.');
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
