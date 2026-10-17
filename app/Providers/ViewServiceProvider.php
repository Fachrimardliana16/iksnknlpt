<?php

namespace App\Providers;

use App\Support\Pengaturan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * $toko dipakai di navbar, footer, bar navigasi bawah, widget WhatsApp,
         * halaman galat, dan sebagian view komponen. Dibagikan ke semua view,
         * bukan lewat composer bernama, karena view komponen Livewire dirender
         * di luar rantai layout dan komposer bernama tidak menjangkaunya.
         *
         * Nilainya diambil dari cache pada akses pertama, jadi berbagi objek
         * ini tidak menambah kueri pada setiap permintaan.
         */
        View::share('toko', Pengaturan::instance());
    }
}
