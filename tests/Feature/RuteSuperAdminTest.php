<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembedaan Admin biasa dan Super Admin pada RUTE.
 *
 * Sidebar menyembunyikan butir Sistem bagi Admin biasa, tetapi itu kosmetik.
 * Yang menentukan adalah pemeriksaan di server: rute yang memuat kredensial
 * atau mengubah siapa yang boleh masuk harus menolak Admin biasa.
 */
class RuteSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->create(['role' => $peran, 'email' => strtolower($peran->value).'@contoh.test']);
    }

    public function test_admin_biasa_ditolak_di_rute_super_admin(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Admin));

        foreach ([
            '/admin/pengguna',
            '/admin/pengaturan',
            '/admin/keamanan',
            '/admin/cadangan',
        ] as $jalur) {
            $this->get($jalur)->assertForbidden();
        }
    }

    public function test_admin_biasa_tetap_boleh_pekerjaan_harian(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Admin));

        foreach ([
            '/admin/produk',
            '/admin/pesanan',
            '/admin/kategori',
            '/admin/kupon',
            '/admin/pesan',
            '/admin/pelanggan',
            '/admin/hero',
            '/admin/faq',
        ] as $jalur) {
            $this->get($jalur)->assertOk();
        }
    }

    public function test_customer_ditolak_di_semua_rute_admin(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::Customer));

        foreach ([
            '/admin',
            '/admin/produk',
            '/admin/pesanan',
            '/admin/pengaturan',
            '/admin/pengguna',
        ] as $jalur) {
            $this->get($jalur)->assertForbidden();
        }
    }

    public function test_super_admin_boleh_semua(): void
    {
        $this->actingAs($this->pengguna(PeranPengguna::SuperAdmin));

        foreach ([
            '/admin/produk',
            '/admin/pengguna',
            '/admin/pengaturan',
            '/admin/keamanan',
            '/admin/cadangan',
        ] as $jalur) {
            $this->get($jalur)->assertOk();
        }
    }
}
