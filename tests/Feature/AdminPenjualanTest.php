<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\TipeKupon;
use App\Livewire\Admin\Kupon;
use App\Livewire\Admin\Pelanggan;
use App\Livewire\Admin\PesanKontak;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Uji halaman Pesan Kontak, Pelanggan, dan Kupon.
 *
 * Yang diperiksa adalah aturan yang menjaga data tetap masuk akal, bukan
 * sekadar tampilannya: pesan yang tidak boleh hilang tanpa jejak, akun yang
 * tidak boleh mengunci dirinya sendiri, dan kupon yang tidak boleh memotong
 * lebih dari nilai belanja.
 */
class AdminPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(PeranPengguna $peran = PeranPengguna::SuperAdmin): User
    {
        return User::factory()->create(['role' => $peran]);
    }

    // ── Pesan kontak ────────────────────────────────────────────────

    public function test_membuka_pesan_menandainya_sudah_dibaca(): void
    {
        $this->actingAs($this->admin());

        $pesan = ContactMessage::query()->create([
            'name' => 'Budi',
            'email' => 'budi@contoh.test',
            'subject' => 'Tanya harga',
            'message' => 'Berapa harganya?',
            'is_read' => false,
        ]);

        Livewire::test(PesanKontak::class)
            ->call('buka', $pesan->id)
            ->assertSet('dipilih', $pesan->id);

        $this->assertTrue($pesan->fresh()->is_read);
    }

    public function test_tanda_dibalas_mencatat_waktu_dan_pelakunya(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $pesan = ContactMessage::query()->create([
            'name' => 'Sari',
            'email' => 'sari@contoh.test',
            'subject' => 'Stok',
            'message' => 'Masih ada?',
            'is_read' => true,
        ]);

        Livewire::test(PesanKontak::class)->call('tandaiBalas', $pesan->id);

        $pesan->refresh();

        $this->assertNotNull($pesan->replied_at);
        $this->assertSame($admin->id, $pesan->replied_by);

        // Menekan lagi melepas tandanya.
        Livewire::test(PesanKontak::class)->call('tandaiBalas', $pesan->id);

        $this->assertNull($pesan->fresh()->replied_at);
    }

    public function test_penghapusan_pesan_mencatat_jejak_tanpa_menyimpan_isi(): void
    {
        $this->actingAs($this->admin());

        $pesan = ContactMessage::query()->create([
            'name' => 'Rahasia',
            'email' => 'rahasia@contoh.test',
            'subject' => 'Keluhan',
            'message' => 'Isi yang bersifat pribadi',
            'is_read' => false,
        ]);

        Livewire::test(PesanKontak::class)->call('hapus', $pesan->id);

        $this->assertDatabaseMissing('contact_messages', ['id' => $pesan->id]);

        // Jejaknya ada, tetapi isi pesannya tidak ikut tersimpan.
        $log = \App\Models\ActivityLog::query()->where('action', 'pesan.dihapus')->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('pribadi', json_encode($log->changes_json));
    }

    // ── Pelanggan ───────────────────────────────────────────────────

    public function test_akun_sendiri_tidak_dapat_diblokir(): void
    {
        $saya = $this->admin();
        $this->actingAs($saya);

        Livewire::test(Pelanggan::class)
            ->call('ubahBlokir', $saya->id)
            ->assertHasErrors(['umum']);

        $this->assertFalse($saya->fresh()->is_blocked);
    }

    public function test_akun_sendiri_tidak_dapat_dihapus(): void
    {
        $saya = $this->admin();
        $this->actingAs($saya);

        Livewire::test(Pelanggan::class)
            ->call('hapus', $saya->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('users', ['id' => $saya->id]);
    }




    public function test_pelanggan_yang_punya_pesanan_tidak_dihapus(): void
    {
        $this->actingAs($this->admin());

        $pelanggan = $this->admin(PeranPengguna::Customer);

        \App\Models\Order::query()->create([
            'user_id' => $pelanggan->id,
            'order_number' => 'UJI-9001',
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

    // ── Kupon ───────────────────────────────────────────────────────

    private function isiForm(\Livewire\Features\SupportTesting\Testable $komponen, array $ganti = []): \Livewire\Features\SupportTesting\Testable
    {
        return $komponen->set(array_merge([
            'code' => 'UJI10',
            'type' => 'percent',
            'value' => '10',
            'min_spend' => '',
            'max_discount' => '50000',
            'quota' => '100',
            'is_active' => true,
        ], $ganti));
    }

    public function test_kupon_tanpa_minimum_belanja_tersimpan(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class))
            ->call('simpan')
            ->assertHasNoErrors();

        $kupon = Coupon::query()->where('code', 'UJI10')->firstOrFail();

        // Kolomnya NOT NULL dengan arti "nol berarti tanpa minimum".
        $this->assertSame('0.00', (string) $kupon->min_spend);
        $this->assertNull($kupon->alasanTidakSah(1000.0), 'tanpa minimum berarti belanja kecil pun sah');
    }

    public function test_kupon_menerapkan_minimum_belanja(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class), ['min_spend' => '200000'])
            ->call('simpan')
            ->assertHasNoErrors();

        $kupon = Coupon::query()->where('code', 'UJI10')->firstOrFail();

        $this->assertNotNull($kupon->alasanTidakSah(100000.0));
        $this->assertNull($kupon->alasanTidakSah(200000.0));
    }

    public function test_persentase_di_atas_seratus_ditolak(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class), ['value' => '150'])
            ->call('simpan')
            ->assertHasErrors(['value']);

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_batas_potongan_menahan_persentase(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class), ['value' => '50', 'max_discount' => '50000'])
            ->call('simpan')
            ->assertHasNoErrors();

        $kupon = Coupon::query()->where('code', 'UJI10')->firstOrFail();

        // 50% dari 500.000 adalah 250.000, tetapi batasnya 50.000.
        $this->assertSame(50000.0, $kupon->hitungPotongan(500000.0));
    }

    public function test_kode_kupon_kembar_ditolak(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class))->call('simpan')->assertHasNoErrors();

        // Huruf kecil diketik, kode disimpan dalam huruf besar.
        $this->isiForm(Livewire::test(Kupon::class), ['code' => 'uji10'])
            ->call('simpan')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_kuota_tidak_boleh_di_bawah_jumlah_pemakaian(): void
    {
        $this->actingAs($this->admin());

        $kupon = Coupon::query()->create([
            'code' => 'SUDAH-DIPAKAI',
            'type' => TipeKupon::Nominal,
            'value' => 20000,
            'min_spend' => 0,
            'quota' => 50,
            'used_count' => 30,
            'is_active' => true,
        ]);

        Livewire::test(Kupon::class)
            ->call('sunting', $kupon->id)
            ->set('quota', '10')
            ->call('simpan')
            ->assertHasErrors(['quota']);

        $this->assertSame(50, $kupon->fresh()->quota);
    }

    public function test_kupon_yang_sudah_dipakai_tidak_dihapus(): void
    {
        $this->actingAs($this->admin());

        $kupon = Coupon::query()->create([
            'code' => 'DIPAKAI',
            'type' => TipeKupon::Persen,
            'value' => 10,
            'min_spend' => 0,
            'used_count' => 1,
            'is_active' => true,
        ]);

        \App\Models\Order::query()->create([
            'order_number' => 'UJI-9002',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 90000,
            'coupon_id' => $kupon->id,
            'shipping_address_snapshot' => ['nama' => 'Uji'],
        ]);

        Livewire::test(Kupon::class)
            ->call('hapus', $kupon->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('coupons', ['id' => $kupon->id]);
    }

    public function test_gratis_ongkir_tidak_memotong_subtotal(): void
    {
        $this->actingAs($this->admin());

        $this->isiForm(Livewire::test(Kupon::class), [
            'type' => 'free_shipping',
            'value' => '0',
            'max_discount' => '',
        ])->call('simpan')->assertHasNoErrors();

        $kupon = Coupon::query()->where('code', 'UJI10')->firstOrFail();

        $this->assertSame(0.0, $kupon->potonganBelanja(500000.0, 25000.0));
        $this->assertSame(25000.0, $kupon->potonganOngkir(500000.0, 25000.0));
    }

    public function test_kuota_habis_menolak_kupon(): void
    {
        $this->actingAs($this->admin());

        $kupon = Coupon::query()->create([
            'code' => 'HABIS',
            'type' => TipeKupon::Nominal,
            'value' => 10000,
            'min_spend' => 0,
            'quota' => 5,
            'used_count' => 5,
            'is_active' => true,
        ]);

        $this->assertNotNull($kupon->alasanTidakSah(500000.0));
    }
}
