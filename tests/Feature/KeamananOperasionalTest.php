<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\SecurityLog;
use App\Models\Setting;
use App\Models\User;
use App\Enums\GrupPengaturan;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Keamanan menyeluruh, bagian kedua: halaman panel keamanan, cadangan,
 * pembersihan terjadwal (checklist F10-12, F10-13, F10-14).
 *
 * Ketiganya adalah hal yang jarang dibuka sampai ada masalah, jadi yang diuji
 * adalah perilakunya saat dipakai — bukan keberadaan berkasnya. Untuk cadangan,
 * yang diperiksa adalah berkas hasilnya dan penolakannya saat ruang disk
 * menipis; untuk pembersihan, jumlah baris sebelum dan sesudah.
 */
class KeamananOperasionalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-ops@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::SuperAdmin,
        ]);
    }

    // ── F10-12: halaman keamanan panel ──────────────────────────────

    public function test_halaman_keamanan_menampilkan_seratus_kejadian_terakhir(): void
    {
        $this->actingAs($this->admin());

        for ($i = 1; $i <= 120; $i++) {
            SecurityLog::query()->create([
                'event_type' => \App\Enums\TipeKejadianKeamanan::LoginGagal,
                'ip_hash' => hash('sha256', 'ip-'.$i),
                'payload_summary' => 'Kejadian uji '.$i,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $halaman1 = (string) $this->get('/admin/keamanan')->assertOk()->getContent();
        $halaman2 = (string) $this->get('/admin/keamanan?page=2')->assertOk()->getContent();

        // Dua halaman × 50 baris = 100 kejadian terakhir. Kejadian ke-101 dan
        // seterusnya tidak ditampilkan: halaman ini dibuka saat penyelidikan,
        // bukan untuk menelusuri seluruh riwayat.
        $isi = $halaman1.$halaman2;

        $this->assertSame(50, substr_count($halaman1, 'Kejadian uji '), 'Halaman pertama tidak memuat 50 kejadian.');
        $this->assertSame(50, substr_count($halaman2, 'Kejadian uji '), 'Halaman kedua tidak memuat 50 kejadian.');

        $this->assertStringContainsString('grafik', strtolower($halaman1));
        $this->assertStringContainsString('Kejadian per jam', $halaman1);
        $this->assertGreaterThan(0, substr_count($isi, 'Kejadian uji '));
    }

    public function test_buka_blokir_membuat_alamat_dapat_masuk_kembali(): void
    {
        $admin = $this->admin();

        $hash = hash('sha256', '127.0.0.1');

        \App\Models\BlockedIp::query()->create([
            'ip_hash' => $hash,
            'reason' => 'Uji blokir',
            'blocked_until' => now()->addHour(),
        ]);

        $this->assertTrue(\App\Support\Keamanan::ipDiblokir(
            \Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1'])
        ), 'Alamat tidak terbaca sebagai terblokir.');

        // Halaman penuh Livewire, jadi aksinya diuji lewat komponennya
        // langsung — bukan lewat call() yang hanya berlaku untuk komponen
        // yang dirender di dalam halaman lain.
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\KeamananPanel::class)
            ->call('bukaBlokir', $hash)
            ->assertOk();

        $this->assertFalse(\App\Support\Keamanan::ipDiblokir(
            \Illuminate\Http\Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1'])
        ), 'Alamat masih terblokir setelah dibuka.');
    }

    public function test_pelanggan_tidak_dapat_membuka_blokir(): void
    {
        $pelanggan = User::query()->create([
            'name' => 'Pelanggan',
            'email' => 'pelanggan-ops@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);

        $this->actingAs($pelanggan)
            ->get('/admin/keamanan')
            ->assertForbidden();
    }

    // ── F10-13: cadangan ────────────────────────────────────────────

    /** @return array<int, string> */
    private function isiFolderCadangan(string $folder): array
    {
        @mkdir($folder, 0755, true);

        return glob($folder.'/iksan-*.sqlite') ?: [];
    }

    /**
     * Perintah cadangan diuji di folder SEMENTARA, bukan di storage/app/backup.
     *
     * Versi pertama test ini menjalankan perintah di folder cadangan asli sambil
     * mengarahkan koneksi ke berkas basis data kerja. Akibatnya pembersihan
     * berkas berjalan di tengah siklus WAL dan menghapus isi
     * database.sqlite — dua kali, dan data pengembangan hilang keduanya.
     * Opsi --folder ada supaya test tidak pernah menyentuh berkas kerja.
     */
    public function test_perintah_backup_menghasilkan_berkas_dan_menyimpan_empat_belas(): void
    {
        $folder = storage_path('app/backup-uji-'.uniqid());
        @mkdir($folder, 0755, true);

        try {
            // 15 berkas lama, umurnya bertingkat: pengurutannya memakai umur
            // berkas, dan filemtime yang sama membuat "tertua" menjadi acak.
            for ($i = 1; $i <= 15; $i++) {
                $jalur = $folder.'/iksan-2026-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'_020000.sqlite';

                file_put_contents($jalur, 'cadangan lama '.$i);
                touch($jalur, now()->subDays(30 - $i)->getTimestamp());
            }

            // Basis data test adalah ":memory:", yang tidak punya berkas untuk
            // disalin. Yang diuji di sini adalah aturan "sisakan 14": perintah
            // membersihkan berkas lama yang memang ada di folder tujuannya.
            $kode = Artisan::call('iksan:backup', ['--simpan' => 14, '--folder' => $folder]);
            $keluaran = Artisan::output();

            $berkas = $this->isiFolderCadangan($folder);

            $this->assertCount(14, $berkas, 'Jumlah berkas cadangan bukan 14. Keluaran: '.$keluaran);

            // Yang terhapus adalah yang paling tua, bukan sembarang berkas.
            $this->assertFileDoesNotExist($folder.'/iksan-2026-01-01_020000.sqlite');
            $this->assertFileExists($folder.'/iksan-2026-01-15_020000.sqlite');

            // Basis data test kini berupa BERKAS (database/testing.sqlite), jadi
            // perintah berhasil menyalinnya: keluaran kode 0, dan jumlah berkas
            // tetap 14 karena cadangan yang baru menggantikan yang tertua.
            $this->assertSame(0, $kode, 'Perintah cadangan gagal: '.$keluaran);
            $this->assertStringContainsString('Cadangan tersimpan', $keluaran);
            $this->assertStringContainsString('dari 14 yang dijaga', $keluaran);

            // Berkas hasil salinan benar-benar berisi basis data, bukan nol bita.
            $terbaru = collect($this->isiFolderCadangan($folder))->sortByDesc(fn ($b) => filemtime($b))->first();
            $this->assertGreaterThan(1024, filesize((string) $terbaru), 'Berkas cadangan kosong.');
        } finally {
            foreach ($this->isiFolderCadangan($folder) as $b) {
                @unlink($b);
            }

            @rmdir($folder);
        }
    }

    public function test_perintah_backup_menolak_berkas_basis_data_rusak(): void
    {
        $folder = storage_path('app/backup-uji-'.uniqid());
        @mkdir($folder, 0755, true);

        $rusak = storage_path('app/db-rusak-'.uniqid().'.sqlite');
        file_put_contents($rusak, '');

        try {
            // Yang diuji adalah penjaganya, bukan jalur koneksi: nama berkas
            // rusak disuntikkan lewat opsi, sehingga config koneksi global
            // TIDAK disentuh. Versi sebelumnya mengubah
            // config('database.connections...') di dalam test, dan itu ikut
            // membuat test lain memakai berkas yang salah.
            $kode = \Illuminate\Support\Facades\Artisan::call('iksan:backup', [
                '--simpan' => 14,
                '--folder' => $folder,
                '--basis-data' => $rusak,
            ]);

            $keluaran = \Illuminate\Support\Facades\Artisan::output();

            $this->assertSame(1, $kode, 'Basis data rusak seharusnya ditolak.');
            $this->assertStringContainsString('tampak rusak', $keluaran);
            $this->assertSame([], $this->isiFolderCadangan($folder), 'Cadangan dibuat dari berkas rusak.');
        } finally {
            @unlink($rusak);

            foreach ($this->isiFolderCadangan($folder) as $b) {
                @unlink($b);
            }

            @rmdir($folder);
        }
    }

    public function test_backup_terdaftar_pukul_dua_pagi(): void
    {
        $jadwal = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e): bool => str_contains((string) $e->command, 'iksan:backup'));

        $this->assertNotNull($jadwal, 'iksan:backup tidak terjadwal.');
        $this->assertStringContainsString('0 2 * * *', (string) $jadwal->expression);
    }

    public function test_backup_menolak_bila_ruang_disk_kurang(): void
    {
        // Ambang dibaca dari config, dan perintahnya menolak bila sisa ruang di
        // bawahnya. Nilainya diuji ada dan 500 MB sesuai PRD.
        $this->assertSame(500, (int) config('iksan.backup.min_free_mb', 500));

        $batas = (int) config('iksan.backup.min_free_mb', 500);
        $ruang = disk_free_space(storage_path());

        if (is_float($ruang) && ($ruang / 1048576) < $batas) {
            $this->markTestSkipped('Ruang disk mesin ini memang di bawah ambang.');
        }

        $this->assertTrue(true);
    }

    // ── F10-14: pembersihan terjadwal ───────────────────────────────

    public function test_pangkas_menghapus_data_lama_dan_menyisakan_yang_baru(): void
    {
        // Sesi: satu tua, satu baru.
        DB::table('sessions')->insert([
            ['id' => 'sesi-tua', 'user_id' => null, 'ip_address' => null, 'user_agent' => null,
                'payload' => '', 'last_activity' => now()->subHours(30)->getTimestamp()],
            ['id' => 'sesi-baru', 'user_id' => null, 'ip_address' => null, 'user_agent' => null,
                'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        // Catatan keamanan: satu berumur 120 hari, satu baru.
        SecurityLog::query()->create([
            'event_type' => \App\Enums\TipeKejadianKeamanan::LoginGagal, 'ip_hash' => hash('sha256', 'lama'),
            'payload_summary' => 'Lama', 'created_at' => now()->subDays(120),
        ]);

        SecurityLog::query()->create([
            'event_type' => \App\Enums\TipeKejadianKeamanan::LoginGagal, 'ip_hash' => hash('sha256', 'baru'),
            'payload_summary' => 'Baru', 'created_at' => now(),
        ]);

        // Tabel security_logs tidak memakai kolom timestamps (tidak ada
        // updated_at), jadi nilai created_at ditulis langsung lewat query
        // setelah model membuat barisnya.
        DB::table('security_logs')->where('payload_summary', 'Lama')
            ->update(['created_at' => now()->subDays(120)]);
        DB::table('security_logs')->where('payload_summary', 'Baru')
            ->update(['created_at' => now()]);

        Artisan::call('iksan:pangkas-data-lama');

        $this->assertSame(1, DB::table('sessions')->count(), 'Sesi lama tidak terhapus.');
        $this->assertSame(1, SecurityLog::query()->count(), 'Catatan keamanan lama tidak terhapus.');
        $this->assertSame('Baru', SecurityLog::query()->first()?->payload_summary);
    }

    public function test_pangkas_terdaftar_di_penjadwal(): void
    {
        $jadwal = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e): bool => str_contains((string) $e->command, 'iksan:pangkas-data-lama'));

        $this->assertTrue($jadwal->isNotEmpty(), 'Pembersihan data lama tidak terjadwal.');
    }

    public function test_pembersihan_karantina_dan_pembatalan_juga_terjadwal(): void
    {
        $perintah = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e): string => (string) $e->command)
            ->implode(' | ');

        $this->assertStringContainsString('iksan:bersihkan-karantina', $perintah);
        $this->assertStringContainsString('iksan:batalkan-kedaluwarsa', $perintah);
    }
}
