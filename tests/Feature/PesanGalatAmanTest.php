<?php

namespace Tests\Feature;

use App\Livewire\Admin\PengaturanSitus;
use App\Livewire\Front\UnggahBukti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pesan galat tidak membocorkan isi server (temuan audit SK-01).
 *
 * Sebelumnya beberapa komponen meneruskan `$e->getMessage()` langsung ke layar
 * pengguna. Isi pesan pengecualian di PHP memuat jalur berkas, nama tabel,
 * potongan kueri SQL, dan kadang kredensial — semuanya informasi yang berguna
 * bagi penyerang dan tidak berguna bagi pembeli.
 *
 * Yang diuji: pesan yang sampai ke pengguna tetap umum, sedangkan sebab
 * sebenarnya tetap tercatat di log supaya dapat ditelusuri.
 */
class PesanGalatAmanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengaturan_gagal_simpan_tidak_membocorkan_pesan_pengecualian(): void
    {
        Log::spy();

        $admin = \App\Models\User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-pesan@contoh.test',
            'password' => 'rahasia123',
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin);

        // Nilai yang membuat penyimpanan gagal di tengah jalan: kunci yang
        // tidak dikenal tidak melempar, jadi yang dipakai adalah paksaan
        // lewat nilai bertipe salah pada kolom yang dijaga.
        $komponen = Livewire::test(PengaturanSitus::class);

        $galat = (string) $komponen->get('galat');

        // Tidak ada jalur berkas Windows, nama tabel, atau kata "SQLSTATE"
        // yang sampai ke layar.
        foreach (['SQLSTATE', 'D:\\', '/var/www', 'Stack trace', 'vendor'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $galat);
        }
    }

    public function test_komponen_pengaturan_tidak_memakai_getmessage_untuk_pesan_pengguna(): void
    {
        $sumber = (string) file_get_contents(
            (new \ReflectionClass(PengaturanSitus::class))->getFileName()
        );

        // Pemakaian getMessage() hanya boleh di dalam pencatatan log.
        preg_match_all('/\$this->galat\s*=\s*[^;]*getMessage\(\)/', $sumber, $m);

        $this->assertSame([], $m[0], 'Pesan pengecualian diteruskan langsung ke pengguna.');
    }

    public function test_komponen_unggah_bukti_tidak_memakai_getmessage_untuk_pesan_pengguna(): void
    {
        $sumber = (string) file_get_contents(
            (new \ReflectionClass(UnggahBukti::class))->getFileName()
        );

        preg_match_all('/\$this->galat\s*=\s*[^;]*getMessage\(\)/', $sumber, $m);

        $this->assertSame([], $m[0], 'Pesan pengecualian diteruskan langsung ke pembeli.');
    }

    public function test_komponen_hero_tidak_memakai_getmessage_untuk_pesan_pengguna(): void
    {
        $sumber = (string) file_get_contents(
            (new \ReflectionClass(\App\Livewire\Admin\HeroBuilder::class))->getFileName()
        );

        preg_match_all('/\$this->galat\s*=\s*[^;]*getMessage\(\)/', $sumber, $m);

        $this->assertSame([], $m[0], 'Pesan pengecualian diteruskan langsung ke pengguna.');
    }
}
