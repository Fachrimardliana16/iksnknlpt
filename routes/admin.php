<?php

use App\Http\Controllers\Admin\DokumenPesanan;
use App\Http\Middleware\PastikanAdmin;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\HeroBuilder;
use App\Livewire\Admin\Kategori;
use App\Livewire\Admin\KeamananPanel;
use App\Livewire\Admin\Kupon;
use App\Livewire\Admin\KeranjangTerbuka;
use App\Livewire\Admin\Masuk;
use App\Livewire\Admin\Pelanggan;
use App\Livewire\Admin\PesananDaftar;
use App\Livewire\Admin\PesananDetail;
use App\Livewire\Admin\PengaturanSitus;
use App\Livewire\Admin\PesanKontak;
use App\Livewire\Admin\ProdukDaftar;
use App\Livewire\Admin\ProdukForm;
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

        // ── Penjualan ────────────────────────────────────────────────
        Route::get('keranjang-terbuka', KeranjangTerbuka::class)->name('carts.index');

        Route::get('pesanan', PesananDaftar::class)->name('orders.index');
        Route::get('pesanan/{ulid}', PesananDetail::class)->name('orders.show');

        // Dokumen cetak. Dibuka di tab baru, jadi tidak memakai tata letak
        // panel: saat mencetak, navigasi hanya memakan kertas.
        Route::get('pesanan/{ulid}/resi', [DokumenPesanan::class, 'resi'])->name('orders.receipt');
        Route::get('pesanan/{ulid}/invoice', [DokumenPesanan::class, 'invoice'])->name('orders.invoice');
        Route::get('pesanan/{ulid}/surat-jalan', [DokumenPesanan::class, 'suratJalan'])->name('orders.delivery');

        Route::get('ekspor/pesanan', [DokumenPesanan::class, 'eksporCsv'])->name('exports.orders');


        // ── Katalog ──────────────────────────────────────────────────
        // Pesan kontak, pelanggan, dan kupon melengkapi pekerjaan penjualan:
        // pesan yang belum dibaca, akun pembeli, dan kode potongan yang dipakai
        // pesanan. Tanpa ketiganya, data itu hanya dapat dilihat lewat basis
        // data langsung.
        Route::get('pesan', PesanKontak::class)->name('messages.index');
        Route::get('pelanggan', Pelanggan::class)->name('customers.index');
        Route::get('kupon', Kupon::class)->name('coupons.index');

        Route::get('produk', ProdukDaftar::class)->name('products.index');
        Route::get('produk/baru', ProdukForm::class)->name('products.create');
        Route::get('produk/{ulid}/sunting', ProdukForm::class)->name('products.edit');

        Route::get('kategori', Kategori::class)->name('categories.index');

        // ── Situs ────────────────────────────────────────────────────
        // Keamanan: satu-satunya jalan membuka blokir alamat dari dalam
        // aplikasi. Tanpa halaman ini, IP pelanggan yang diblokir otomatis
        // hanya dapat dipulihkan lewat basis data langsung.
        Route::get('keamanan', KeamananPanel::class)->name('security.index');

        Route::get('pengaturan', PengaturanSitus::class)->name('settings.index');
        Route::get('hero', HeroBuilder::class)->name('hero.index');
    });
});

/*
|--------------------------------------------------------------------------
| Berkas bukti pembayaran
|--------------------------------------------------------------------------
| Sengaja di luar awalan /admin: ini bukan halaman panel, melainkan pengiriman
| berkas. Perlindungannya dua lapis — tanda tangan URL berumur 15 menit
| (checklist F10-09) dan pemeriksaan sesi admin di dalam controller. Berkasnya
| berada di disk privat karena memuat nama pengirim dan nomor rekening.
*/
Route::get('bukti-bayar/{payment:ulid}', \App\Http\Controllers\Admin\BuktiPembayaran::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('admin.payments.proof');
