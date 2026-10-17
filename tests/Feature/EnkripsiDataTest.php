<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\BlindIndex;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kolom PII disimpan terenkripsi, dan pencarian memakai indeks buta.
 * F1-10 dan F1-11.
 */
class EnkripsiDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_tersimpan_terenkripsi_dan_tidak_terbaca_mentah(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone_number' => '628120000001',
        ]);

        $mentah = DB::table('users')->where('id', $user->id)->first();

        $this->assertNotSame('budi@example.test', $mentah->email);
        $this->assertNotSame('Budi Santoso', $mentah->name);
        $this->assertNotSame('628120000001', $mentah->phone_number);

        // Nilai tetap dapat dibaca lewat model, karena cast yang mendekripsi.
        $this->assertSame('budi@example.test', $user->fresh()->email);
    }

    public function test_pencarian_email_lewat_indeks_buta(): void
    {
        User::factory()->create(['email' => 'siti@example.test']);

        $ditemukan = User::query()
            ->where('email_bidx', BlindIndex::buat('siti@example.test', 'email_bidx'))
            ->first();

        $this->assertNotNull($ditemukan);
        $this->assertSame('siti@example.test', $ditemukan->email);
    }

    public function test_indeks_buta_menormalkan_huruf_besar_dan_spasi(): void
    {
        User::factory()->create(['email' => 'Andi@Example.Test']);

        $ditemukan = User::query()
            ->where('email_bidx', BlindIndex::buat('  andi@example.test  ', 'email_bidx'))
            ->first();

        $this->assertNotNull($ditemukan, 'Indeks buta harus mengabaikan huruf besar dan spasi tepi.');
    }

    public function test_indeks_buta_wajib_unik(): void
    {
        User::factory()->create(['email' => 'kembar@example.test']);

        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'kembar@example.test']);
    }

    public function test_kata_sandi_di_hash_dan_tidak_pernah_tersimpan_mentah(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-sekali']);

        $mentah = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('rahasia-sekali', $mentah);
        $this->assertTrue(password_verify('rahasia-sekali', $mentah));
    }

    public function test_peran_admin_dikenali(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $pelanggan = User::factory()->create();

        $this->assertSame(PeranPengguna::SuperAdmin, $admin->role);
        $this->assertTrue($admin->admin());
        $this->assertTrue($admin->superAdmin());
        $this->assertFalse($pelanggan->admin());
    }
}
