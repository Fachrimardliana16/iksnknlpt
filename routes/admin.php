<?php

use App\Http\Middleware\PastikanAdmin;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Masuk;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Rute panel admin.
|
| Login berada di luar middleware auth. Seluruh rute lain memakai auth ditambah
| PastikanAdmin, yang memeriksa peran di server dan mencatat percobaan akses
| tidak sah, bukan sekadar menyembunyikan tautan di antarmuka.
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    // Middleware auth mengarahkan tamu ke rute bernama "login", yang tidak ada
    // di aplikasi ini. Arahkan ke halaman masuk panel, bukan ke rute bawaan.
    Authenticate::redirectUsing(fn () => route('admin.login'));

    Route::get('login', Masuk::class)->name('login');

    Route::post('logout', function () {
        $email = auth()->user()?->email;

        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        if ($email !== null) {
            App\Support\Keamanan::catat(App\Enums\TipeKejadianKeamanan::LoginBerhasil, request(), $email, ringkasan: 'Keluar panel');
        }

        return redirect()->route('admin.login');
    })->name('logout');

    Route::middleware(['auth', PastikanAdmin::class])->group(function (): void {
        Route::get('/', Dashboard::class)->name('dashboard');
    });
});
