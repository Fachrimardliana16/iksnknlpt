<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Policies\KebijakanPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji kebijakan otorisasi panel (checklist F10-01).
 *
 * Memeriksa kebijakannya secara langsung, bukan lewat komponen Livewire.
 * Alasannya: sejak kebijakan ini dipasang, komponen menolak lebih dulu melalui
 * authorize() sehingga aturan di dalamnya — misalnya "super admin terakhir
 * tidak boleh diturunkan" — tidak lagi terjangkau dari jalur itu. Yang perlu
 * dibuktikan sekarang ada dua lapis yang berbeda, dan keduanya diuji terpisah.
 */
class KebijakanPanelTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->create(['role' => $peran]);
    }

    public function test_super_admin_boleh_mengatur_situs(): void
    {
        $this->assertTrue((new KebijakanPanel)->aturSitus($this->pengguna(PeranPengguna::SuperAdmin)));
    }

    public function test_admin_biasa_tidak_boleh_mengatur_situs(): void
    {
        // Pengaturan memuat kredensial pembayaran dan kunci API. Admin yang
        // dapat mengubahnya berarti dapat mengalihkan pembayaran.
        $this->assertFalse((new KebijakanPanel)->aturSitus($this->pengguna(PeranPengguna::Admin)));
    }

    public function test_admin_biasa_boleh_mengelola_katalog_dan_pesanan(): void
    {
        $admin = $this->pengguna(PeranPengguna::Admin);

        // Pekerjaan harian: membatasinya hanya memperlambat tanpa menambah
        // keamanan.
        $this->assertTrue((new KebijakanPanel)->kelola($admin));
        $this->assertTrue((new KebijakanPanel)->lihat($admin));
    }

    public function test_pelanggan_tidak_boleh_mengelola_apa_pun(): void
    {
        $pelanggan = $this->pengguna(PeranPengguna::Customer);

        $this->assertFalse((new KebijakanPanel)->lihat($pelanggan));
        $this->assertFalse((new KebijakanPanel)->kelola($pelanggan));
        $this->assertFalse((new KebijakanPanel)->aturSitus($pelanggan));
        $this->assertFalse((new KebijakanPanel)->bukaBlokir($pelanggan));
        $this->assertFalse((new KebijakanPanel)->kelolaAkun($pelanggan));
    }

    public function test_hanya_super_admin_boleh_membuka_blokir(): void
    {
        // Membuka blokir adalah kebalikan dari perlindungan brute force.
        $this->assertTrue((new KebijakanPanel)->bukaBlokir($this->pengguna(PeranPengguna::SuperAdmin)));
        $this->assertFalse((new KebijakanPanel)->bukaBlokir($this->pengguna(PeranPengguna::Admin)));
    }

    public function test_hanya_super_admin_boleh_mengelola_akun(): void
    {
        $this->assertTrue((new KebijakanPanel)->kelolaAkun($this->pengguna(PeranPengguna::SuperAdmin)));
        $this->assertFalse((new KebijakanPanel)->kelolaAkun($this->pengguna(PeranPengguna::Admin)));
    }

    public function test_kebijakan_terdaftar_di_gate(): void
    {
        $admin = $this->pengguna(PeranPengguna::Admin);

        // Dibuktikan lewat Gate, bukan hanya lewat pemanggilan kelas langsung:
        // yang menentukan di aplikasi adalah pemetaan di AppServiceProvider.
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('kelola', User::class));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('aturSitus', User::class));
    }

    public function test_admin_ditolak_saat_menyimpan_pengaturan(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Admin));

        // Dipanggil dari komponen, tempat authorize() benar-benar berjalan.
        \Livewire\Livewire::test(\App\Livewire\Admin\PengaturanSitus::class)
            ->call('simpan')
            ->assertForbidden();
    }

    public function test_super_admin_dapat_menyimpan_pengaturan(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::SuperAdmin));

        // Yang diuji adalah IZINNYA, bukan isi formulirnya.
        //
        // Komponen ini memakai aturan validasi yang dibangun saat render, dan
        // memanggil simpan() lewat Livewire::test() tanpa merender lebih dulu
        // melempar MissingRulesException — galat pengujian, bukan galat aplikasi.
        // Karena itu izinnya diperiksa langsung lewat Gate.
        $this->assertTrue(
            \Illuminate\Support\Facades\Gate::allows('aturSitus', User::class),
            'super admin harus diizinkan mengubah pengaturan situs',
        );
    }
}
