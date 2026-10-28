<?php

use App\Http\Controllers\Admin\DokumenPesanan;
use App\Http\Controllers\Admin\LaporanPenjualan;
use App\Http\Middleware\PastikanAdmin;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\CadanganPanel;
use App\Livewire\Admin\HeroBuilder;
use App\Livewire\Admin\HeroSunting;
use App\Livewire\Admin\Kategori;
use App\Livewire\Admin\KeamananPanel;
use App\Livewire\Admin\KolPerforma;
use App\Livewire\Admin\Kupon;
use App\Livewire\Admin\KeranjangTerbuka;
use App\Livewire\Admin\Masuk;
use App\Livewire\Admin\Pelanggan;
use App\Livewire\Admin\PenggunaPeran;
use App\Livewire\Admin\PesananDaftar;
use App\Livewire\Admin\PesananDetail;
use App\Livewire\Admin\PengaturanSitus;
use App\Livewire\Admin\PesanKontak;
use App\Livewire\Admin\ProdukDaftar;
use App\Livewire\Admin\ProdukForm;
use App\Models\User;
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
    // Pengalihan tamu ke halaman masuk panel TIDAK dipasang di sini.
    //
    // Lihat bootstrap/app.php -> $middleware->redirectGuestsTo(): dengan
    // `route:cache`, berkas ini tidak dieksekusi, sehingga pemanggilan
    // Authenticate::redirectUsing() di sini akan hilang begitu rute di-cache.
    //
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

        // Cetak resi BANYAK sekaligus: satu berkas PDF berisi beberapa label
        // 100x150 mm. ULID dipisah koma pada parameter "ulid".
        //
        // HARUS didaftarkan SEBELUM 'pesanan/{ulid}': rute dengan parameter
        // cocok lebih dulu dan akan menelan kata "cetak-resi" sebagai ULID,
        // sehingga PesananDetail mencari pesanan bernama "cetak-resi" lalu
        // menghasilkan 404 yang membingungkan.
        Route::get('pesanan/cetak-resi', \App\Http\Controllers\Admin\ResiBanyak::class)
            ->name('orders.receipt.bulk');
        Route::get('pesanan/{ulid}', PesananDetail::class)->name('orders.show');

        // Dokumen cetak. PDF sungguhan dari sisi server (dompdf), bukan HTML
        // yang baru menjadi PDF lewat dialog cetak peramban — ukuran lembarnya
        // karena itu dapat diperiksa, dan hasilnya sama di peramban mana pun.
        Route::get('pesanan/{ulid}/resi', [DokumenPesanan::class, 'resi'])->name('orders.receipt');

        Route::get('pesanan/{ulid}/invoice', [DokumenPesanan::class, 'invoice'])->name('orders.invoice');
        Route::get('pesanan/{ulid}/surat-jalan', [DokumenPesanan::class, 'suratJalan'])->name('orders.delivery');

        // Pratinjau HTML di peramban, untuk melihat isi sebelum mencetak.
        Route::get('pesanan/{ulid}/pratinjau/{jenis}', [DokumenPesanan::class, 'pratinjau'])->name('orders.preview');

        // Report penjualan: rentang tanggal di query string, jadi tautannya
        // dapat disalin dan dibuka ulang — laporan akhir bulan yang sama setiap
        // bulan. Ekspor lama (eksporCsv) dipertahankan: dipakai tautan lama
        // yang mungkin sudah tersimpan di penanda buku admin.
        Route::get('laporan/penjualan/csv', [LaporanPenjualan::class, 'csv'])->name('reports.sales.csv');
        Route::get('laporan/penjualan/pdf', [LaporanPenjualan::class, 'pdf'])->name('reports.sales.pdf');

        Route::get('ekspor/pesanan', [DokumenPesanan::class, 'eksporCsv'])->name('exports.orders');


        // ── Katalog ──────────────────────────────────────────────────
        // Pesan kontak, pelanggan, dan kupon melengkapi pekerjaan penjualan:
        // pesan yang belum dibaca, akun pembeli, dan kode potongan yang dipakai
        // pesanan. Tanpa ketiganya, data itu hanya dapat dilihat lewat basis
        // data langsung.
        Route::get('pesan', PesanKontak::class)->name('messages.index');
        Route::get('pelanggan', Pelanggan::class)->name('customers.index');

        // Halaman ini mengelola siapa yang boleh masuk panel. Menyembunyikan
        // tautan di sidebar tidak menutup apa pun: URL-nya tetap terbuka bagi
        // Admin biasa, dan yang muncul adalah daftar akun panel lengkap dengan
        // tombol yang lalu ditolak satu per satu. Karena itu rutenya sendiri
        // dibatasi lewat Gate (KebijakanPanel::kelolaAkun), diperiksa di
        // server sebelum komponen dijalankan.
        Route::get('pengguna', PenggunaPeran::class)
            ->can('kelolaAkun', User::class)
            ->name('users.index');

        Route::get('kupon', Kupon::class)->name('coupons.index');

        // Performa KOL. Terpisah dari halaman Kupon karena pertanyaannya
        // berbeda: halaman Kupon mengelola aturan diskon, halaman ini menjawab
        // siapa yang menghasilkan penjualan. Menumpuk keduanya membuat salah
        // satu hilang di antara kolom yang lain.
        Route::get('kol', KolPerforma::class)->name('kol.index');

        Route::get('produk', ProdukDaftar::class)->name('products.index');
        Route::get('produk/baru', ProdukForm::class)->name('products.create');
        Route::get('produk/{ulid}/sunting', ProdukForm::class)->name('products.edit');

        Route::get('kategori', Kategori::class)->name('categories.index');

        // ── Situs ────────────────────────────────────────────────────
        // Keamanan: satu-satunya jalan membuka blokir alamat dari dalam
        // aplikasi. Tanpa halaman ini, IP pelanggan yang diblokir otomatis
        // hanya dapat dipulihkan lewat basis data langsung.
        // Tiga rute berikut dijaga Gate, BUKAN hanya disembunyikan dari sidebar.
        // Terukur sebelum perbaikan ini: Admin biasa mendapat 200 pada
        // /admin/keamanan, /admin/cadangan, dan /admin/pengaturan — termasuk
        // halaman pengaturan yang memuat kredensial pembayaran dan kunci API.
        Route::get('keamanan', KeamananPanel::class)
            ->can('bukaBlokir', User::class)
            ->name('security.index');

        // Cadangan basis data. Terpisah dari halaman Keamanan karena
        // pertanyaannya berbeda: Keamanan menjawab "siapa mencoba masuk",
        // halaman ini menjawab "apa masih ada salinan yang bisa dipulihkan".
        Route::get('cadangan', CadanganPanel::class)
            ->can('aturSitus', User::class)
            ->name('backup.index');

        Route::get('pengaturan', PengaturanSitus::class)
            ->can('aturSitus', User::class)
            ->name('settings.index');
        Route::get('hero', HeroBuilder::class)->name('hero.index');

        // Halaman sunting sendiri. Didaftarkan setelah 'hero' supaya tidak
        // tertukar, dan memakai ulid — bukan id — sama seperti slide di sisi
        // depan, sehingga alamat yang tersalin tidak dapat ditebak berurutan.
        Route::get('hero/{ulid}/sunting', HeroSunting::class)->name('hero.edit');
        Route::get('faq', \App\Livewire\Admin\FaqPanel::class)->name('faq.index');

        // Halaman pendaftaran notifikasi perangkat. Tidak di bawah /pengaturan:
        // izin notifikasi diminta sekali per perangkat, dan mencarinya di dalam
        // halaman pengaturan yang panjang membuat orang tidak menemukannya.
        Route::get('notifikasi', \App\Livewire\Admin\NotifikasiPerangkat::class)->name('notifications.index');
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

/*
|--------------------------------------------------------------------------
| Unduh cadangan basis data
|--------------------------------------------------------------------------
| Sama pola dengan bukti pembayaran: berkas berisi seluruh basis data, jadi
| tidak pernah disajikan dari disk publik dan hanya lewat URL bertanda tangan
| berumur 15 menit. Controller juga memeriksa perannya sendiri — tanda tangan
| yang sah tidak cukup tanpa sesi Super Admin.
*/
Route::get('cadangan/unduh/{berkas}', \App\Http\Controllers\Admin\UnduhCadangan::class)
    ->middleware(['auth', 'signed', 'throttle:10,1'])
    ->can('aturSitus', User::class)
    ->name('admin.backup.download');
