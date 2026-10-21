<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\KebijakanPanel;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Keranjang sebagai singleton.
        //
        // Tanpa ini, setiap app(Keranjang::class) membuat instance baru dengan
        // cache kosong, dan setiap komponen Livewire yang menyentuhnya
        // mengulang kueri keranjang — pada beranda tercatat 11 kali untuk satu
        // permintaan. Kueri itu sama persis, jadi mengulanginya hanya membakar
        // koneksi basis data. Pada muat pertama (keranjang belum ada) efeknya
        // paling terasa: tiap panggilan mengulang pencarian "belum ada".
        $this->app->singleton(\App\Services\Keranjang::class);
    }

    public function boot(): void
    {
        // Kebijakan panel (checklist F10-01).
        //
        // Dipetakan ke kelas User, bukan ke model tertentu, karena yang dibedakan
        // adalah siapa yang boleh melakukan tindakan tertentu di panel — bukan
        // akses ke satu baris data. Middleware PastikanAdmin sudah menjaga
        // rutenya; kebijakan ini yang membedakan Admin dari Super Admin.
        Gate::policy(User::class, KebijakanPanel::class);

        $this->configureSqlite();
        $this->configureProxyTrust();
    }

    /**
     * SQLite butuh PRAGMA eksplisit. Tanpa foreign_keys, seluruh kunci asing
     * di DBSCHEMA.md menjadi dekorasi. Tanpa busy_timeout, penulisan bersamaan
     * (keranjang, checkout) gagal dengan "database is locked".
     */
    private function configureSqlite(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $connection = $event->connection;

            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $connection->getPdo();

            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA temp_store = MEMORY');
            $pdo->exec('PRAGMA cache_size = -20000');

            // WAL hanya untuk basis data BERKAS.
            //
            // Basis data ":memory:" tidak mengenal WAL. Menjalankan PRAGMA-nya
            // membuat Laravel membuang koneksi lalu membukanya ulang TANPA
            // ":memory:" — koneksi baru memakai jalur dari konfigurasi, yaitu
            // database/database.sqlite. Akibatnya yang dimigrasikan adalah
            // berkas KERJA, bukan basis data test; di mesin ini berkas itu
            // sempat terkunci proses lain, WAL-nya terpotong, dan isinya
            // hilang (0 bita) — dua kali.
            $basisData = (string) $connection->getDatabaseName();

            if ($basisData === ':memory:' || $basisData === '') {
                return;
            }

            // WAL tidak dapat diubah di dalam transaksi, jadi kegagalannya diabaikan
            // dengan sengaja bila koneksi kebetulan sudah berada dalam transaksi.
            try {
                $pdo->exec('PRAGMA journal_mode = WAL');
            } catch (\Throwable) {
                // Diabaikan dengan sengaja.
            }
        });
    }

    /**
     * Di belakang tunnel, permintaan tiba sebagai HTTP polos dari proxy.
     * Tanpa ini, seluruh URL aset dirender sebagai http dan diblokir peramban
     * sebagai mixed content.
     */
    private function configureProxyTrust(): void
    {
        // Skema URL TIDAK dipaksa di sini.
        //
        // Dulu: begitu proxy dipercaya, seluruh URL dipaksa https. Akibatnya
        // akses LOKAL (http://127.0.0.1:8100) juga dirender dengan https, CSS
        // gagal dimuat, dan halaman tampil tanpa gaya — kerusakan yang sama
        // dengan yang hendak diperbaiki, hanya berpindah tempat.
        //
        // Yang benar: biarkan skema mengikuti permintaan. Saat tiba lewat
        // tunnel, header X-Forwarded-Proto: https dihormati karena proxy sudah
        // dipercaya (lihat config/iksan.php -> trusted_proxies), sehingga aset
        // dirender https. Saat dibuka langsung di mesin ini, tetap http.
        //
        // Baris ini sengaja kosong; kepercayaan proxy dipasang di
        // bootstrap/app.php, dan pembacaan skema dilakukan Laravel sendiri.
    }
}
