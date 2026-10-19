<?php

namespace App\Console\Commands;

use App\Support\Keamanan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Jalan darurat membuka blokir alamat (checklist F2-05, F2-06).
 *
 * Blokir alamat menutup SELURUH panel, termasuk halaman untuk membuka blokir
 * yang ada di dalamnya. Itu memang disengaja: alamat yang diblokir karena
 * brute force tidak boleh dapat membatalkan blokirnya sendiri.
 *
 * Akibatnya seorang admin yang salah sandi berulang kali dari kantornya
 * sendiri terkunci tanpa jalan keluar dari dalam aplikasi. Perintah ini
 * adalah jalan keluarnya — hanya dapat dijalankan oleh orang yang memang
 * punya akses ke server, dan setiap pemakaiannya meninggalkan catatan.
 *
 * Pemakaian:
 *   php artisan iksan:buka-blokir --daftar
 *   php artisan iksan:buka-blokir --semua
 *   php artisan iksan:buka-blokir --ip=203.0.113.7
 */
class BukaBlokirIp extends Command
{
    protected $signature = 'iksan:buka-blokir
                            {--daftar : Tampilkan alamat yang sedang diblokir}
                            {--semua : Buka seluruh blokir}
                            {--ip= : Buka blokir untuk satu alamat}';

    protected $description = 'Membuka blokir alamat yang terkunci karena percobaan masuk gagal';

    public function handle(): int
    {
        if ($this->option('daftar')) {
            return $this->tampilkanDaftar();
        }

        if ($this->option('semua')) {
            $jumlah = Keamanan::bukaBlokir('');

            // bukaBlokir('') tidak menghapus apa pun bila hash-nya tidak
            // cocok, jadi penghapusan menyeluruh dilakukan langsung di sini.
            $jumlah = DB::table('blocked_ips')->delete();
            DB::table('login_attempts')->delete();

            $this->info("Seluruh blokir dibuka: $jumlah baris.");

            return self::SUCCESS;
        }

        $ip = (string) $this->option('ip');

        if ($ip === '') {
            $this->error('Sebutkan --ip=, atau pakai --semua atau --daftar.');

            return self::FAILURE;
        }

        $hash = Keamanan::hashIp($ip);

        if (! DB::table('blocked_ips')->where('ip_hash', $hash)->exists()) {
            $this->warn('Alamat itu tidak sedang diblokir.');

            return self::SUCCESS;
        }

        Keamanan::bukaBlokir($hash);
        DB::table('login_attempts')->where('ip_hash', $hash)->delete();

        $this->info('Blokir dibuka untuk '.$ip.'.');
        $this->line('Catatan keamanan dibiarkan utuh sebagai jejak.');

        return self::SUCCESS;
    }

    private function tampilkanDaftar(): int
    {
        $baris = DB::table('blocked_ips')->orderByDesc('created_at')->get();

        if ($baris->isEmpty()) {
            $this->info('Tidak ada alamat yang diblokir.');

            return self::SUCCESS;
        }

        $this->table(
            ['Diblokir sejak', 'Sampai', 'Alasan', 'Hash (8 awal)'],
            $baris->map(fn ($b) => [
                $b->created_at,
                $b->blocked_until,
                $b->reason,
                substr((string) $b->ip_hash, 0, 8),
            ])->all(),
        );

        $this->line('Alamat aslinya tidak disimpan — hanya sidik jarinya. Buka');
        $this->line('dengan --ip= bila alamatnya diketahui, atau --semua.');

        return self::SUCCESS;
    }
}
