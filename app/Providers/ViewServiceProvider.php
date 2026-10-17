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
         * $toko tersedia di seluruh layout. Nilainya berasal dari cache, jadi
         * navbar dan footer tidak menambah kueri pada setiap permintaan.
         */
        View::composer(['layouts.front', 'layouts.admin', 'errors.*'], function ($view): void {
            $view->with('toko', Pengaturan::instance());
        });
    }
}
