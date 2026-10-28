<?php

use App\Http\Middleware\BlokirIp;
use App\Http\Middleware\SetLocale;
use App\Livewire\Front\About;
use App\Livewire\Front\Beranda;
use App\Livewire\Front\Checkout;
use App\Livewire\Front\DetailProduk;
use App\Livewire\Front\UnggahBukti;
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

Route::middleware([SetLocale::class, BlokirIp::class])->group(function (): void {
    Route::get('/', Beranda::class)->name('front.home');

    Route::get('/produk', ProdukDaftar::class)
        ->middleware('laju:search,q')
        ->name('front.products.index');
    Route::get('/produk/{product:slug}', DetailProduk::class)->name('front.products.show');

    /*
    | Halaman kategori.
    |
    | Didaftarkan SESUDAH /produk/{product:slug} agar tidak tertukar: keduanya
    | memakai satu segmen, dan urutan pendaftaran menentukan siapa yang menang.
    | Awalan /kategori/ dipakai supaya tidak ada kemungkinan bentrok sama sekali.
    */
    Route::get('/kategori/{slug}', \App\Livewire\Front\Kategori::class)->name('front.category');

    /*
    | Halaman unggah bukti transfer.
    |
    | Dibuka tanpa akun karena checkout toko ini tidak mewajibkan akun. Yang
    | menjaga aksesnya adalah ULID pesanan: 26 karakter acak yang tidak dapat
    | ditebak, dan hanya diketahui pembeli dari halaman sukses pesanannya.
    */
    Route::get('/pesanan/{ulid}/bayar', UnggahBukti::class)
        ->middleware('laju:payment_proof')
        ->name('front.payment.upload');

    Route::get('/tentang', About::class)->name('front.about');

    Route::get('/kontak', Kontak::class)->name('front.contact');

    Route::get('/keranjang', KeranjangHalaman::class)->name('front.cart');
    Route::get('/checkout', Checkout::class)->name('front.checkout');

    Route::get('/pesanan/sukses/{order:ulid}', PesananSukses::class)->name('front.order.success');
    Route::get('/lacak', LacakPesanan::class)->middleware('laju:track_order')->name('front.order.track');

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

    /*
    | SEO/GEO: peta situs dan robots.txt dilayani dari rute, bukan berkas statis.
    |
    | Alasannya bukan selera: daftar produk berubah setiap hari, dan robots.txt
    | yang berupa berkas statis akan berbeda dari sitemap.xml begitu ada produk
    | baru. Keduanya dibangun dari SATU sumber (App\Support\PetaSitus), jadi
    | mustahil keduanya saling bertentangan.
    |
    | Diletakkan di dalam grup yang sama supaya pemblokiran IP tetap berlaku.
    */
    Route::get('/sitemap.xml', function (): \Illuminate\Http\Response {
        return response(\App\Support\PetaSitus::xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    })->name('front.sitemap');

    Route::get('/robots.txt', function (): \Illuminate\Http\Response {
        return response(\App\Support\PetaSitus::robots(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    })->name('front.robots');
});
