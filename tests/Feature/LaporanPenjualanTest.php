<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Report penjualan: filter waktu, CSV, dan PDF.
 *
 * Yang diperiksa adalah aturan yang bisa membuat laporan BERKHALI SALAH,
 * bukan sekadar "berhasil mengunduh berkas":
 *
 *   1. Rentang yang diminta harus dipakai APA ADANYA. Versi pertama memotong
 *      `sampai` menjadi `mulai + 366 hari` dan menghasilkan berkas 200 OK
 *      berisi nol baris — admin membacanya sebagai "tidak ada penjualan".
 *   2. Rentang yang jelas salah ketik ditolak dengan pesan, bukan dipangkas.
 *   3. CSV harus benar-benar berisi baris pesanan pada rentang itu.
 *   4. PDF harus benar-benar PDF.
 */
class LaporanPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'admin@contoh.test']);
    }

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function pesanan(array $atribut): Order
    {
        // Order tidak punya factory; dibuat langsung seperti test sewaktu lain.
        return Order::query()->create($atribut + [
            'order_number' => 'IK-2026'.random_int(1000, 9999).'-'.strtoupper(Str::random(4)),
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Penerima Uji'],
        ]);
    }

    public function test_rentang_dipakai_apa_adanya_tanpa_dipotong(): void
    {
        $this->actingAs($this->admin());

        // Pesanan tiga tahun lalu — jauh di luar 366 hari dari awal rentang.
        // Kalau rentang dipotong seperti versi pertama, pesanan ini hilang
        // dari laporan tanpa satu pun pesan galat.
        $lama = $this->pesanan(['created_at' => now()->subYears(3)]);
        $baru = $this->pesanan(['created_at' => now()->subMonth()]);

        $isi = $this->get('/admin/laporan/penjualan/csv?mulai='.now()->subYears(4)->format('Y-m-d')
            .'&sampai='.now()->format('Y-m-d'))->streamedContent();

        $this->assertStringContainsString($lama->order_number, $isi);
        $this->assertStringContainsString($baru->order_number, $isi);
    }

    public function test_rentang_di_luar_batas_ditolak_dengan_pesan(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/laporan/penjualan/csv?mulai=2000-01-01&sampai=2026-12-31')
            ->assertStatus(422);
    }

    public function test_rentang_terbalik_ditukar_bukan_dikosongkan(): void
    {
        $this->actingAs($this->admin());

        // "mulai" lebih baru daripada "sampai": ditukar, bukan dibuang.
        $pesanan = $this->pesanan(['created_at' => now()->subDays(2)]);

        $isi = $this->get('/admin/laporan/penjualan/csv?mulai='.now()->format('Y-m-d')
            .'&sampai='.now()->subDays(10)->format('Y-m-d'))->streamedContent();

        $this->assertStringContainsString($pesanan->order_number, $isi);
    }

    public function test_tanggal_tidak_dibaca_kembali_ke_bulan_berjalan(): void
    {
        $this->actingAs($this->admin());

        $pesanan = $this->pesanan(['created_at' => now()->subDays(1)]);

        // Tanpa parameter tanggal: jatuh ke awal bulan ini sampai hari ini.
        $isi = $this->get('/admin/laporan/penjualan/csv')->streamedContent();

        $this->assertStringContainsString($pesanan->order_number, $isi);
    }

    public function test_pdf_sungguhan_bukan_html(): void
    {
        $this->actingAs($this->admin());

        $this->pesanan(['created_at' => now()->subDays(1)]);

        $res = $this->get('/admin/laporan/penjualan/pdf?mulai='.now()->subDays(5)->format('Y-m-d')
            .'&sampai='.now()->format('Y-m-d'));

        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_tamu_tidak_boleh_membuka_laporan(): void
    {
        $this->get('/admin/laporan/penjualan/csv')->assertRedirect('/admin/login');
    }
}
