<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Livewire\Admin\ProdukDaftar;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Otorisasi aksi Livewire panel admin.
 *
 * Pertanyaannya bukan "apakah tautannya disembunyikan", melainkan "apakah
 * seseorang yang BUKAN admin dapat menjalankan method yang mengubah data".
 *
 * Livewire mengirim aksi lewat /livewire/update. Rute itu tidak memakai
 * middleware 'admin' — middleware rute asal hanya berlaku saat halaman
 * pertama dimuat. Karena itu setiap komponen admin wajib memeriksa izin di
 * dalam methodnya sendiri, dan test ini menjaga agar itu tidak hilang.
 */
class OtorisasiAksiAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdmin, 'email' => 'a@contoh.test']);
    }

    private function pelanggan(): User
    {
        return User::factory()->create(['role' => PeranPengguna::Customer, 'email' => 'c@contoh.test']);
    }

    public function test_pelanggan_tidak_dapat_menjalankan_aksi_hapus_produk(): void
    {
        $produk = Product::factory()->create();

        $this->actingAs($this->pelanggan());

        try {
            Livewire::test(ProdukDaftar::class)->call('hapus', $produk->id);
        } catch (\Throwable) {
            // Ditolak di mana pun (403, abort, atau galat otorisasi) sudah benar.
        }

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
    }

    public function test_pelanggan_tidak_dapat_menerbitkan_produk_massal(): void
    {
        $produk = Product::factory()->create(['is_published' => false]);

        $this->actingAs($this->pelanggan());

        try {
            Livewire::test(ProdukDaftar::class)
                ->set('terpilih', [$produk->id])
                ->call('ubahTerbitMassal', true);
        } catch (\Throwable) {
        }

        $this->assertFalse((bool) $produk->fresh()->is_published);
    }

    public function test_tamu_tidak_dapat_membuka_halaman_admin_lewat_http(): void
    {
        // Jalur yang benar-benar dipakai peramban: rute, bukan Livewire::test.
        // Livewire::test melewati middleware rute, jadi ia TIDAK boleh dipakai
        // untuk membuktikan penjagaan tamu.
        $this->get('/admin/produk')->assertRedirect('/admin/login');
    }

    public function test_pelanggan_ditolak_di_rute_admin_lewat_http(): void
    {
        $this->actingAs($this->pelanggan());

        $this->get('/admin/produk')->assertForbidden();
    }
}
