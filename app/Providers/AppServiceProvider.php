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
        //
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
        $trusted = config('iksan.trusted_proxies');

        if ($trusted !== null) {
            URL::forceScheme('https');
        }
    }
}
