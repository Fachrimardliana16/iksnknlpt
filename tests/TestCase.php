<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Menyiapkan berkas basis data test, dan menjaganya tetap terpisah dari
     * basis data kerja.
     *
     * Suite memakai `database/testing.sqlite` (lihat phpunit.xml). Berkas itu
     * dibuat di sini bila belum ada, supaya SQLite dapat membukanya dan
     * PRAGMA journal_mode=WAL berlaku sah. Dengan ":memory:" WAL tidak
     * berlaku, Laravel membuang koneksi lalu membukanya ulang memakai jalur
     * dari konfigurasi — dan yang dimigrasikan menjadi database/database.sqlite,
     * berkas KERJA, yang isinya hilang dua kali di mesin ini.
     *
     * Penjaga tambahan: bila konfigurasi test sampai menunjuk ke basis data
     * kerja (mis. karena .env memaksa), test dihentikan sebelum menyentuh apa pun.
     */
    protected function setUpTraits(): array
    {
        $this->pastikanBasisDataTestTerpisah();

        // Kurs mata uang TIDAK boleh memanggil jaringan saat test.
        //
        // Kelas Kurs mencoba https://api.frankfurter.dev lalu open.er-api.com
        // dengan timeout 4 detik. Pada suite penuh ini terjadi ratusan kali,
        // dan bila salah satu permintaan menggantung, PHPUnit berhenti dengan
        // "Maximum execution time of 60 seconds exceeded" di tengah kueri
        // Eloquent — galat yang menunjuk tempat yang salah dan menghabiskan
        // waktu untuk dikejar.
        //
        // Test memakai kurs tetap. Perilaku jaringan diuji terpisah bila perlu.
        \Illuminate\Support\Facades\Cache::put('kurs:usd-idr', [
            'kurs' => 18000.0,
            'sumber' => 'uji',
            'waktu' => now()->toAtomString(),
        ], now()->addHour());

        // Rute bawaan "login" tidak ada di aplikasi ini: middleware auth
        // diarahkan ke halaman masuk panel oleh routes/admin.php. Pengalihan
        // itu dipasang saat berkas rute dimuat, dan di test rute dimuat ulang
        // per kasus — tanpa baris ini, tamu yang membuka panel mendapat 500
        // "Route [login] not defined" alih-alih 302.
        \Illuminate\Auth\Middleware\Authenticate::redirectUsing(
            static fn () => route('admin.login')
        );

        return parent::setUpTraits();
    }

    private function pastikanBasisDataTestTerpisah(): void
    {
        $basis = (string) config('database.connections.sqlite.database', '');
        $kerja = str_replace('\\', '/', database_path('database.sqlite'));
        $uji = str_replace('\\', '/', $basis);

        // Cache konfigurasi dibuang SEBELUM aplikasi dinyalakan, oleh
        // tests/bootstrap.php. Baris ini adalah jaring pengaman terakhir:
        // bila basis data test tetap menunjuk berkas kerja, test berhenti.
        if ($uji === '' || $uji === ':memory:' || $uji === $kerja) {
            // Konfigurasi test TIDAK boleh menyentuh berkas kerja. Bila sampai
            // di sini, phpunit.xml berubah atau config ter-cache dari lingkungan
            // lain; lebih baik gagal terang-terangan daripada menghapus data.
            $this->fail(
                'Basis data test menunjuk ke '.($uji === '' ? '(kosong)' : $uji)
                .'. Hentikan: jalankan `php artisan config:clear` dan pastikan phpunit.xml '
                .'memakai database/testing.sqlite, bukan :memory: atau berkas kerja.'
            );
        }

        $folder = dirname($basis);

        if (! is_dir($folder)) {
            @mkdir($folder, 0755, true);
        }

        if (! is_file($basis)) {
            @touch($basis);
        }
    }
}
