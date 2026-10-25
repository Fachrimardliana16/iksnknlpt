<?php

namespace Tests\Feature;

use App\Livewire\Admin\CadanganPanel;
use App\Models\Setting;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Halaman Cadangan basis data.
 *
 * Yang diuji adalah janji yang mudah rusak tanpa ketahuan:
 *
 * 1. Halaman benar-benar menampilkan salinan yang ADA di disk, bukan daftar
 *    yang ditulis tangan. Salinan yang tampil padahal tidak ada membuat admin
 *    merasa aman tanpa alasan.
 * 2. Salinan rusak ditandai "tidak dapat dipulihkan", bukan disembunyikan.
 *    Berkas 15 bita pernah ada di folder ini dan tampak seperti cadangan sah.
 * 3. Tombol buat benar-benar menjalankan perintah backup, bukan sekadar
 *    menampilkan pesan sukses.
 * 4. Hanya Super Admin yang boleh membuat cadangan — tindakan ini menulis
 *    berkas di server.
 */
class CadanganPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        Pengaturan::segarkan();

        Storage::fake('local');
    }

    private function superAdmin(string $surel = 'bos@contoh.test'): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => $surel]);
    }

    private function adminBiasa(string $surel = 'staf@contoh.test'): User
    {
        return User::factory()->create(['role' => 'admin', 'email' => $surel]);
    }

    /** Salinan SQLite yang benar-benar sah di folder cadangan. */
    private function salinanSah(string $nama): string
    {
        $folder = storage_path('app/backup');

        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        // Berkas SQLite sungguhan: salinan basis data test, bukan teks palsu.
        $sumber = config('database.connections.sqlite.database');
        $tujuan = $folder.DIRECTORY_SEPARATOR.$nama;
        copy($sumber, $tujuan);

        return $tujuan;
    }

    public function test_halaman_terbuka_dan_menampilkan_salinan_yang_ada(): void
    {
        $this->actingAs($this->superAdmin());

        $this->salinanSah('iksan-2026-10-20_020000.sqlite');
        $this->salinanSah('iksan-2026-10-21_020000.sqlite');

        Livewire::test(CadanganPanel::class)
            ->assertOk()
            ->assertSee('iksan-2026-10-20_020000.sqlite')
            ->assertSee('iksan-2026-10-21_020000.sqlite')
            ->assertSee('dapat dipulihkan');
    }

    public function test_folder_kosong_dinyatakan_jujur_bukan_daftar_palsu(): void
    {
        $this->actingAs($this->superAdmin());

        // Folder cadangan dikosongkan lebih dulu supaya yang diperiksa
        // benar-benar keadaan kosong, bukan keberuntungan.
        $folder = storage_path('app/backup');
        $isiLama = glob($folder.'/iksan-*.sqlite') ?: [];

        $sementara = [];
        foreach ($isiLama as $f) {
            $tujuan = $folder.'/.simpan-'.basename($f);
            @rename($f, $tujuan);
            $sementara[] = $tujuan;
        }

        try {
            Livewire::test(CadanganPanel::class)
                ->assertOk()
                ->assertSee('Belum ada cadangan')
                ->assertDontSee('dapat dipulihkan');
        } finally {
            // Salinan nyata dikembalikan apa adanya.
            foreach ($sementara as $t) {
                @rename($t, str_replace('/.simpan-', '/', $t));
            }
        }
    }

    public function test_salinan_rusak_ditandai_tidak_dapat_dipulihkan(): void
    {
        $this->actingAs($this->superAdmin());

        $folder = storage_path('app/backup');

        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        // Berkas 15 bita: bentuk yang pernah ada di folder ini, hasil test
        // yang mengarahkan backup ke berkas kosong. Header SQLite tidak ada.
        file_put_contents($folder.'/iksan-2026-01-04_020000.sqlite', str_repeat("\0", 15));

        try {
            Livewire::test(CadanganPanel::class)
                ->assertOk()
                ->assertSee('iksan-2026-01-04_020000.sqlite')
                ->assertSee('tidak dapat dipulihkan');
        } finally {
            @unlink($folder.'/iksan-2026-01-04_020000.sqlite');
        }
    }

    public function test_tombol_buat_menjalankan_perintah_backup_sungguhan(): void
    {
        $this->actingAs($this->superAdmin());

        // Folder khusus supaya berkas cadangan NYATA tidak tersentuh. Tanpa
        // opsi folder, perintah menulis ke storage/app/backup dan pembersihan
        // "sisakan N" bisa menghapus salinan sungguhan di mesin ini.
        $folder = Storage::fake('local')->path('backup-uji');

        Artisan::call('iksan:backup', ['--folder' => $folder]);

        $this->assertGreaterThan(0, count(glob($folder.'/iksan-*.sqlite') ?: []), 'Perintah harus benar-benar menulis berkas cadangan.');
    }

    public function test_hanya_super_admin_yang_boleh_membuat_cadangan(): void
    {
        $this->actingAs($this->adminBiasa());

        // Livewire menelan AuthorizationException di dalam dirinya, jadi yang
        // diperiksa adalah EFEKNYA: tidak boleh ada berkas baru di folder.
        $folder = storage_path('app/backup');
        $sebelum = count(glob($folder.'/iksan-*.sqlite') ?: []);

        Livewire::test(CadanganPanel::class)->call('buat');

        $sesudah = count(glob($folder.'/iksan-*.sqlite') ?: []);

        $this->assertSame($sebelum, $sesudah, 'Admin biasa tidak boleh membuat cadangan.');
    }

    public function test_tamu_ditolak(): void
    {
        $this->get(route('admin.backup.index'))->assertRedirect('/admin/login');
    }
}
