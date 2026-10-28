<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penutupan halaman Pengguna & Peran di LEVEL RUTE.
 *
 * Sebelumnya halaman ini hanya lewat middleware PastikanAdmin, yang memeriksa
 * "boleh masuk panel" dan bukan "boleh mengelola akun panel". Akibatnya Admin
 * biasa dapat membuka /admin/pengguna: menunya memang disembunyikan dari
 * sidebar, tapi URL-nya tetap hidup dan yang tampil adalah daftar akun panel
 * beserta tombol yang lalu ditolak satu per satu.
 *
 * Yang diuji di sini adalah lapis paling luar: permintaan HTTP biasa, tanpa
 * Livewire, tanpa menekan tombol apa pun.
 */
class PenggunaPeranRuteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_biasa_ditolak_di_halaman_pengguna(): void
    {
        $admin = User::factory()->create(['role' => PeranPengguna::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_masih_boleh_membuka_halaman_pengguna(): void
    {
        $super = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);

        $this->actingAs($super)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_pelanggan_tetap_ditolak_di_gerbang_panel(): void
    {
        $pelanggan = User::factory()->create(['role' => PeranPengguna::Customer]);

        // Bukan redirect ke login: gerbang panel sudah menolaknya duluan.
        $this->actingAs($pelanggan)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect('/admin/login');
    }
}
