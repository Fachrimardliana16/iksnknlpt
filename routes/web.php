<?php

use App\Http\Middleware\SetLocale;
use App\Livewire\Front\About;
use App\Livewire\Front\Beranda;
use App\Livewire\Front\Checkout;
use App\Livewire\Front\DetailProduk;
use App\Livewire\Front\HalamanStatis;
use App\Livewire\Front\KeranjangHalaman;
use App\Livewire\Front\Kontak;
use App\Livewire\Front\LacakPesanan;
use App\Livewire\Front\PesananSukses;
use App\Livewire\Front\ProdukDaftar;
use Illuminate\Support\Facades\Route;

/*
| Rute sisi depan. Seluruhnya publik. Keranjang berbasis sesi, jadi pembelian
| tidak menuntut pendaftaran akun.
|
| Penggantian bahasa lewat /lang/{locale} yang menulis cookie, bukan lewat
| parameter pada setiap rute, supaya URL tetap bersih.
*/

Route::middleware(SetLocale::class)->group(function (): void {
    Route::get('/', Beranda::class)->name('front.home');

    Route::get('/produk', ProdukDaftar::class)->name('front.products.index');
    Route::get('/produk/{product:slug}', DetailProduk::class)->name('front.products.show');

    Route::get('/tentang', About::class)->name('front.about');
    Route::get('/kontak', Kontak::class)->name('front.contact');

    Route::get('/keranjang', KeranjangHalaman::class)->name('front.cart');
    Route::get('/checkout', Checkout::class)->name('front.checkout');

    Route::get('/pesanan/sukses/{order:ulid}', PesananSukses::class)->name('front.order.success');
    Route::get('/lacak', LacakPesanan::class)->name('front.order.track');

    Route::get('/halaman/{slug}', HalamanStatis::class)->name('front.page');

    Route::get('/lang/{locale}', function (string $locale) {
        if (! in_array($locale, SetLocale::DIDUKUNG, true)) {
            $locale = config('app.locale');
        }

        return redirect()
            ->back(fallback: route('front.home'))
            ->withCookie(cookie(
                name: SetLocale::COOKIE,
                value: $locale,
                minutes: 60 * 24 * 365,
                path: '/',
                secure: request()->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ));
    })->name('front.lang');
});
