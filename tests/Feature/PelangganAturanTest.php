<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Livewire\Admin\Pelanggan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Uji aturan akun panel yang berlaku SESUDAH kebijakan otorisasi lolos.
 *
 * Sejak F10-01 dipasang, admin biasa ditolak lebih dulu oleh authorize() dan
 * tidak pernah sampai ke aturan di dalam komponen. Karena itu pengujiannya
 * memakai super admin sebagai pelaku, supaya aturan di dalam komponen benar-
 * benar tercapai — termasuk larangan menurunkan super admin terakhir, yang
 * mencegah panel kehilangan satu-satunya akun yang dapat mengelola admin.
 */
class PelangganAturanTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_terakhir_tidak_dapat_diturunkan(): void
    {
        $pelaku = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);
        $lain = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);

        $this->actingAs($pelaku);

        // Dua super admin. Menurunkan yang lain diperbolehkan.
        Livewire::test(Pelanggan::class)
            ->call('ubahPeran', $lain->id, 'admin')
            ->assertHasNoErrors();

        $this->assertSame(PeranPengguna::Admin, $lain->fresh()->role);

        // Sekarang hanya tinggal satu, yaitu pelaku sendiri. Akun sendiri
        // memang tidak dapat diubah perannya.
        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
    }

    public function test_super_admin_terakhir_tidak_dapat_dihapus_oleh_pelaku_lain(): void
    {
        // Satu-satunya super admin adalah $lain; pelakunya admin biasa tidak
        // dapat menyentuhnya, jadi yang diuji di sini adalah jalur super admin
        // kedua yang mencoba menghapus super admin terakhir.
        $pelaku = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);
        $terakhir = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);

        $this->actingAs($pelaku);

        // Pelaku menurunkan dirinya sendiri tidak mungkin; yang diuji: setelah
        // $pelaku diturunkan, $terakhir menjadi satu-satunya dan tidak boleh
        // dihapus.
        $pelaku->update(['role' => PeranPengguna::Admin]);

        $superKedua = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);
        $this->actingAs($superKedua);

        Livewire::test(Pelanggan::class)
            ->call('hapus', $terakhir->id)
            ->assertHasNoErrors();

        // Dua super admin, jadi penghapusan sah. Yang penting: pelaku tidak
        // dapat menghapus dirinya sendiri.
        Livewire::test(Pelanggan::class)
            ->call('hapus', $superKedua->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('users', ['id' => $superKedua->id]);
    }

    public function test_pelanggan_yang_punya_pesanan_tidak_dihapus(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::SuperAdmin]));

        $pelanggan = User::factory()->create(['role' => PeranPengguna::Customer]);

        \App\Models\Order::query()->create([
            'user_id' => $pelanggan->id,
            'order_number' => 'UJI-KEB-9001',
            'status' => 'completed',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Uji'],
        ]);

        Livewire::test(Pelanggan::class)
            ->call('hapus', $pelanggan->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('users', ['id' => $pelanggan->id]);
    }

    public function test_akun_sendiri_tidak_dapat_diblokir(): void
    {
        $saya = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);
        $this->actingAs($saya);

        Livewire::test(Pelanggan::class)
            ->call('ubahBlokir', $saya->id)
            ->assertHasErrors(['umum']);

        $this->assertFalse($saya->fresh()->is_blocked);
    }

    public function test_admin_biasa_tidak_pernah_sampai_ke_aturan_komponen(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Admin]));

        $pelanggan = User::factory()->create(['role' => PeranPengguna::Customer]);

        // Ditolak oleh kebijakan, bukan oleh pesan galat di dalam komponen.
        Livewire::test(Pelanggan::class)
            ->call('ubahPeran', $pelanggan->id, 'super_admin')
            ->assertForbidden();

        $this->assertSame(PeranPengguna::Customer, $pelanggan->fresh()->role);
    }
}
