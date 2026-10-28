<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Unduh berkas cadangan.
 *
 * Berkas ini salinan SELURUH basis data, bukan aset biasa. Yang diuji karena
 * itu bukan cuma "tombolnya jalan", tapi jalur yang mengunduhnya tidak bisa
 * disalahgunakan:
 *
 * 1. Tanpa tanda tangan → ditolak. Tidak ada alamat permanen.
 * 2. Tamu dan peran Pelanggan → ditolak walau tandatangannya sah.
 * 3. Nama berkas berisi "../" → ditolak. Ini yang membuat folder cadangan
 *    bisa dipakai untuk mengambil .env.
 * 4. Tanda tangan kedaluwarsa → ditolak.
 * 5. Tautan sah → berkas benar-benar terkirim.
 */
class UnduhCadanganTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(string $surel = 'bos@contoh.test'): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => $surel]);
    }

    private function pembeli(string $surel = 'pembeli@contoh.test'): User
    {
        return User::factory()->create(['role' => 'customer', 'email' => $surel]);
    }

    /** Cadangan sungguhan di folder backup. */
    private function buatCadangan(string $nama): string
    {
        $folder = storage_path('app/backup');

        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $tujuan = $folder.DIRECTORY_SEPARATOR.$nama;
        copy(config('database.connections.sqlite.database'), $tujuan);

        return $tujuan;
    }

    private function tautSah(string $berkas): string
    {
        return URL::temporarySignedRoute('admin.backup.download', now()->addMinutes(15), ['berkas' => $berkas]);
    }

    public function test_tautan_sah_mengirim_berkas_cadangan(): void
    {
        $nama = 'iksan-2026-10-25_020000.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            $this->actingAs($this->superAdmin())
                ->get($this->tautSah($nama))
                ->assertOk()
                ->assertDownload($nama);
        } finally {
            @unlink($jalur);
        }
    }

    public function test_tanpa_tanda_tangan_ditolak(): void
    {
        $nama = 'iksan-2026-10-25_020001.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            // Tidak ada tanda tangan sama sekali: URL permanen tidak ada.
            // Tamu dialihkan ke halaman masuk panel oleh middleware auth.
            $this->get('/cadangan/unduh/'.$nama)->assertRedirect('/admin/login');
        } finally {
            @unlink($jalur);
        }
    }

    public function test_pembeli_ditolak_walau_tandatangannya_sah(): void
    {
        $nama = 'iksan-2026-10-25_020002.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            // Tanda tangan sah, tetapi perannya Pelanggan. Berkas ini memuat
            // surel dan alamat semua pembeli lain.
            $this->actingAs($this->pembeli())
                ->get($this->tautSah($nama))
                ->assertForbidden();
        } finally {
            @unlink($jalur);
        }
    }

    public function test_tamu_ditolak(): void
    {
        $nama = 'iksan-2026-10-25_020003.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            // Rute ini kini memakai middleware auth, jadi tamu dialihkan ke
            // halaman masuk panel sebelum sampai ke controller.
            $this->get($this->tautSah($nama))->assertRedirect('/admin/login');
        } finally {
            @unlink($jalur);
        }
    }

    public function test_nama_berkas_dengan_titik_titik_ditolak(): void
    {
        $this->actingAs($this->superAdmin());

        // Yang dicoba: keluar dari folder cadangan menuju .env.
        $this->get(URL::temporarySignedRoute(
            'admin.backup.download',
            now()->addMinutes(15),
            ['berkas' => '../../../.env'],
        ))->assertNotFound();
    }

    public function test_nama_berkas_di_luar_pola_ditolak(): void
    {
        $this->actingAs($this->superAdmin());

        // Nama yang tidak lazim ditolak 404, bukan diteruskan ke berkas.
        // '' tidak diuji di sini: parameter rute kosong tidak bisa dibentuk
        // sama sekali, jadi tidak pernah sampai ke controller.
        foreach (['database.sqlite', 'app.php', 'iksan.txt', 'IKAN-2026.sqlite', 'a/b.sqlite'] as $berkas) {
            $this->get(URL::temporarySignedRoute(
                'admin.backup.download',
                now()->addMinutes(15),
                ['berkas' => $berkas],
            ))->assertNotFound();
        }
    }

    public function test_tanda_tangan_kedaluwarsa_ditolak(): void
    {
        $nama = 'iksan-2026-10-25_020004.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            $this->actingAs($this->superAdmin())
                ->get(URL::temporarySignedRoute(
                    'admin.backup.download',
                    now()->subMinute(),
                    ['berkas' => $nama],
                ))->assertForbidden();
        } finally {
            @unlink($jalur);
        }
    }

    public function test_cadangan_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->superAdmin());

        $this->get($this->tautSah('iksan-2030-01-01_000000.sqlite'))->assertNotFound();
    }

    public function test_admin_biasa_tidak_boleh_mengunduh(): void
    {
        $nama = 'iksan-2026-10-25_020005.sqlite';
        $jalur = $this->buatCadangan($nama);

        try {
            // Membuat cadangan menulis berkas di server; mengunduhnya dibatasi
            // pada peran yang sama.
            $this->actingAs(User::factory()->create(['role' => 'admin', 'email' => 'staf@contoh.test']))
                ->get($this->tautSah($nama))
                ->assertForbidden();
        } finally {
            @unlink($jalur);
        }
    }
}
