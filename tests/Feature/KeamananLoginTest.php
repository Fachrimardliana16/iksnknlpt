<?php

namespace Tests\Feature;

use App\Enums\StatusPesanan;
use App\Models\Setting;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * F2-03 sampai F2-06 dan F10-01: pembatas laju, blokir brute force, dan
 * pemeriksaan peran di server.
 */
class KeamananLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('admin-login:127.0.0.1');
        \App\Models\BlockedIp::query()->delete();
        \App\Models\LoginAttempt::query()->delete();
    }

    public function test_tamu_diarahkan_ke_halaman_masuk_panel(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_pelanggan_tidak_bisa_membuka_panel(): void
    {
        $pelanggan = User::factory()->create();

        $this->actingAs($pelanggan)->get('/admin')->assertForbidden();

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'unauthorized_access',
        ]);
    }

    public function test_admin_bisa_membuka_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_login_gagal_mencatat_kejadian_keamanan(): void
    {
        User::factory()->superAdmin()->create(['email' => 'admin@example.test']);

        \Livewire\Livewire::test(\App\Livewire\Admin\Masuk::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'salah-sekali')
            ->call('masuk');

        $this->assertDatabaseHas('security_logs', [
            'event_type' => 'login_failed',
        ]);
    }

    public function test_pesan_galat_tidak_membedakan_email_tidak_terdaftar_dari_sandi_salah(): void
    {
        User::factory()->superAdmin()->create(['email' => 'admin@example.test']);

        $adaEmail = \Livewire\Livewire::test(\App\Livewire\Admin\Masuk::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'salah')
            ->call('masuk');

        $tidakAda = \Livewire\Livewire::test(\App\Livewire\Admin\Masuk::class)
            ->set('email', 'tidak-ada@example.test')
            ->set('password', 'salah')
            ->call('masuk');

        $this->assertSame(
            $adaEmail->get('galat'),
            $tidakAda->get('galat'),
            'Pesan galat harus seragam untuk mencegah enumerasi akun.',
        );
    }

    public function test_sepuluh_kegagalan_memblokir_ip_satu_jam(): void
    {
        User::factory()->superAdmin()->create(['email' => 'admin@example.test']);

        for ($i = 0; $i < 10; $i++) {
            \App\Support\Keamanan::catatLoginGagal(request(), 'admin@example.test');
        }

        $this->assertDatabaseCount('blocked_ips', 1);
        $this->assertTrue(\App\Support\Keamanan::ipDiblokir(request()));

        $blokir = \App\Models\BlockedIp::query()->first();
        $this->assertTrue($blokir->blocked_until->gt(now()->addMinutes(55)));
    }

    public function test_ip_diblokir_tidak_bisa_masuk_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        \App\Support\Keamanan::blokirIp(request(), 'Uji', 1);

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_nomor_wa_kosong_membuat_widget_tidak_dirender(): void
    {
        // RefreshDatabase tidak menjalankan seeder, jadi baris pengaturan harus
        // dibuat sendiri. Tanpa ini, update() menyentuh nol baris dan kedua
        // pemeriksaan di bawah lulus atau gagal tanpa menguji apa pun.
        Setting::query()->where('key', 'whatsapp_number')->delete();

        Setting::query()->create([
            'key' => 'whatsapp_number',
            'value' => null,
            'group_name' => 'contact',
            'type' => 'text',
            'label' => 'Nomor WhatsApp',
            'is_encrypted' => false,
            'is_public' => true,
        ]);

        Pengaturan::lupakan();

        $this->get('/')->assertOk()->assertDontSee('wa.me/');

        Setting::query()->where('key', 'whatsapp_number')->update(['value' => '628123456789']);
        Pengaturan::lupakan();

        // Halaman publik disimpan di cache. Yang membatalkannya adalah naiknya
        // versi katalog — hal yang sama yang dilakukan panel admin saat
        // pengaturan disimpan. Test menirunya di sini, bukan memanggil
        // Cache::flush(): yang perlu dibuktikan adalah MEKANISME pembatalannya.
        \App\Support\CacheKatalog::tandaiBerubah();

        $this->get('/')->assertOk()->assertSee('wa.me/628123456789');
    }

    public function test_header_keamanan_terpasang(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("nonce-", $csp);
        $this->assertStringNotContainsString('script-src \'unsafe-inline\'', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
