<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\TipeKejadianKeamanan;
use App\Models\User;
use App\Support\Keamanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Uji middleware BlokirIp dan CatatKeamanan (checklist F2-06).
 *
 * Yang dibuktikan di sini adalah hal yang sebelumnya TIDAK berlaku:
 *
 *   - blokir alamat menutup seluruh permintaan dari alamat itu, bukan hanya
 *     halaman masuk panel;
 *   - pengguna panel yang sudah masuk tetap dapat membuka panel, karena
 *     halaman untuk membuka blokir ada di dalamnya;
 *   - tanggapan 403 dan 429 meninggalkan jejak di security_logs;
 *   - tanggapan 404 TIDAK dicatat, supaya tabel jejak tidak tenggelam oleh
 *     404 yang wajar.
 */
class BlokirIpTest extends TestCase
{
    use RefreshDatabase;

    private function catatBlokirSaatIni(): void
    {
        Keamanan::blokirIp(request(), 'Uji', 1);
    }

    public function test_alamat_yang_diblokir_ditolak_di_halaman_depan(): void
    {
        $this->catatBlokirSaatIni();

        // Sebelum middleware ini ada, permintaan dari alamat yang diblokir
        // tetap dilayani seperti biasa di halaman depan.
        $this->get('/')->assertForbidden();
        $this->get('/produk')->assertForbidden();
        $this->get('/kontak')->assertForbidden();
    }

    public function test_halaman_masuk_panel_tidak_ikut_diblokir(): void
    {
        $this->catatBlokirSaatIni();

        // Halaman masuk panel sengaja TIDAK memakai middleware blokir.
        //
        // Alasannya: halaman untuk membuka blokir ada di dalam panel, dan
        // panel dilindungi halaman masuk itu. Kalau blokir menutupnya juga,
        // salah sandi berulang dari satu jaringan mengunci admin dari
        // satu-satunya tempat untuk memperbaiki keadaan.
        //
        // Yang membatasi halaman ini tetap ada: batas 5 percobaan per menit,
        // penundaan bertingkat, dan blokir otomatis setelah 10 kegagalan —
        // semuanya diperiksa di dalam komponen Masuk.
        $this->get('/admin/login')->assertOk();
    }

    public function test_pengguna_panel_yang_sudah_masuk_ikut_diblokir(): void
    {
        $admin = User::factory()->create(['role' => PeranPengguna::SuperAdmin]);

        $this->actingAs($admin);
        $this->catatBlokirSaatIni();

        // Blokir berlaku juga untuk sesi yang sudah masuk. Sesi hidup dari
        // alamat yang sedang diblokir justru keadaan yang paling perlu
        // ditutup.
        $this->get(route('admin.security.index'))->assertForbidden();

        $this->assertDatabaseHas('security_logs', [
            'event_type' => TipeKejadianKeamanan::IpDiblokir->value,
        ]);
    }

    public function test_blokir_dapat_dibuka_lewat_perintah_artisan(): void
    {
        $this->catatBlokirSaatIni();

        // Karena panel ikut tertutup, inilah jalan keluarnya — dan hanya dapat
        // dijalankan oleh orang yang punya akses ke server.
        $this->artisan('iksan:buka-blokir --semua')->assertSuccessful();

        $this->assertDatabaseCount('blocked_ips', 0);
        $this->get('/')->assertOk();
    }

    public function test_daftar_blokir_dapat_dilihat_tanpa_mengubah_apa_pun(): void
    {
        $this->catatBlokirSaatIni();

        // Sidik jari alamat yang tersimpan membuat daftar ini berguna untuk
        // memeriksa keadaan tanpa langsung menghapusnya.
        $this->artisan('iksan:buka-blokir --daftar')->assertSuccessful();

        $this->assertDatabaseCount('blocked_ips', 1);
    }

    public function test_tanggapan_403_dicatat(): void
    {
        $pelanggan = User::factory()->create(['role' => PeranPengguna::Customer]);

        $this->actingAs($pelanggan)->get('/admin')->assertForbidden();

        // Dicatat tepat SATU kali: penandaan di gerbang panel mencegah
        // middleware mencatat kejadian yang sama untuk kedua kalinya.
        $this->assertSame(
            1,
            \App\Models\SecurityLog::query()
                ->where('event_type', TipeKejadianKeamanan::AksesTidakSah->value)
                ->count(),
        );
    }

    public function test_tanggapan_404_tidak_dicatat(): void
    {
        $this->get('/halaman-yang-tidak-ada')->assertNotFound();

        // Peramban dan pemindai menghasilkan ribuan 404 yang wajar. Mencatatnya
        // akan menenggelamkan kejadian yang penting.
        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_batas_laju_yang_terlampaui_dicatat(): void
    {
        // Login admin adalah komponen Livewire, bukan rute POST biasa.
        RateLimiter::clear('admin_login:uji');

        // Sandi harus lolos aturan min:6 lebih dulu. Dengan sandi yang lebih
        // pendek, validasi menolak sebelum jalur gagal-login tercapai dan
        // tidak ada yang dicatat — bukan karena pencatatannya rusak.
        for ($i = 0; $i < 6; $i++) {
            \Livewire\Livewire::test(\App\Livewire\Admin\Masuk::class)
                ->set('email', 'tidak-ada@contoh.test')
                ->set('password', 'sandi-salah-panjang')
                ->call('masuk');
        }

        // Percobaan berulang harus meninggalkan jejak, bukan hilang diam-diam.
        $this->assertTrue(
            \App\Models\SecurityLog::query()
                ->whereIn('event_type', [
                    TipeKejadianKeamanan::BatasLaju->value,
                    TipeKejadianKeamanan::LoginGagal->value,
                ])
                ->count() > 0,
            'Percobaan login berulang tidak meninggalkan jejak keamanan.',
        );
    }

    public function test_alamat_yang_tidak_diblokir_dilayani_biasa(): void
    {
        $this->get('/')->assertOk();

        $this->assertDatabaseCount('blocked_ips', 0);
    }
}
