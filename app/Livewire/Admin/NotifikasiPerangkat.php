<?php

namespace App\Livewire\Admin;

use App\Models\PushSubscription;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

/**
 * Pendaftaran notifikasi perangkat (web push) untuk admin.
 *
 * Bootstrap: admin memasang izin di peramban, lalu peramban mengirim alamat
 * push ke server. Skrip `app.js` memanggil `daftar()` di sini setelah
 * berhasil berlangganan.
 *
 * Ada tombol "Matikan" supaya izin dapat dicabut tanpa harus membuka
 * pengaturan peramban — pengguna yang tidak bisa mematikan sesuatu akan
 * mematikannya dari sumbernya, dan itu berarti tidak bisa dihubungi lagi.
 */
#[Layout('layouts.admin')]
class NotifikasiPerangkat extends KomponenAdmin
{
    public bool $aktif = false;

    public ?string $pesan = null;

    public function mount(): void
    {

        $this->muatUlang();
    }

    public function daftar(string $endpoint, string $publicKey, string $authToken, ?string $peramban = null): void
    {
        $pengguna = auth()->user();

        if ($pengguna === null) {
            return;
        }

        // Alamat push ini sudah terdaftar pada akun lain. Endpoint milik
        // peramban orang lain tidak boleh diambil alih: kalau boleh, satu admin
        // yang mengetahui alamat itu dapat memindahkan notifikasi orang lain
        // ke perangkatnya sendiri.
        $ada = PushSubscription::query()->where('endpoint', $endpoint)->first();

        if ($ada !== null && $ada->user_id !== $pengguna->getKey()) {
            $this->pesan = 'Alamat push ini sudah terdaftar pada akun lain.';

            return;
        }

        // updateOrCreate dengan KUNCI endpoint + user_id (bukan endpoint saja).
        //
        // Endpoint datang dari sisi klien. Kalau kuncinya hanya endpoint, satu
        // admin yang tahu (atau menebak) endpoint milik admin lain dapat
        // mendaftarkannya sebagai miliknya sendiri — baris pemilik lama
        // berpindah kepadanya beserta kunci push-nya. Endpoint milik sendiri
        // tetap satu baris walau tombol "Izinkan" ditekan dua kali.
        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint, 'user_id' => $pengguna->getKey()],
            [
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'peramban' => $peramban ?? $this->namaPeramban(),
                'last_used_at' => now(),
            ],
        );

        $this->pesan = 'Perangkat ini akan menerima notifikasi.';
        $this->muatUlang();
    }

    public function matikan(): void
    {
        $pengguna = auth()->user();

        if ($pengguna === null) {
            return;
        }

        // Hanya milik pengguna ini. Matikan "semua" berarti satu admin yang
        // logout mematikan notifikasi semua orang.
        PushSubscription::query()
            ->where('user_id', $pengguna->getKey())
            ->delete();

        $this->pesan = 'Notifikasi dimatikan untuk perangkat Anda.';
        $this->muatUlang();
    }

    public function ujiPush(): void
    {
        $pengguna = auth()->user();

        if ($pengguna === null) {
            return;
        }

        $hasil = \App\Support\NotifikasiPush::ke(
            $pengguna,
            'Notifikasi uji',
            'Ini notifikasi uji dari panel Iksan Knalpot.',
            route('admin.dashboard'),
        );

        $this->pesan = match (true) {
            $hasil['push'] > 0 => 'Notifikasi terkirim ke '.$hasil['push'].' perangkat.',
            $hasil['alasan'] !== null => 'Belum terkirim: '.$hasil['alasan'],
            default => 'Belum terkirim.',
        };
    }

    private function muatUlang(): void
    {
        $this->aktif = PushSubscription::query()
            ->where('user_id', auth()->id())
            ->exists();
    }

    private function namaPeramban(): string
    {
        $ua = (string) request()->userAgent();

        return match (true) {
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'OPR') => 'Opera',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'lain',
        };
    }

    public function render(): View
    {
        return view('livewire.admin.notifikasi-perangkat', [
            'jumlahPerangkat' => PushSubscription::query()
                ->where('user_id', auth()->id())
                ->count(),
            'kunciPublik' => config('services.vapid.public_key'),
            'siap' => \App\Support\NotifikasiPush::siap(),
        ])->title('Notifikasi perangkat · Panel');
    }
}
