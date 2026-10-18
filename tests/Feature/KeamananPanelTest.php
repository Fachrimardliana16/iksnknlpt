<?php

namespace Tests\Feature;

use App\Enums\TipeKejadianKeamanan;
use App\Livewire\Admin\KeamananPanel;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Uji halaman keamanan (checklist F10-12).
 *
 * Yang terpenting diuji di sini adalah pembukaan blokir: halaman ini satu-
 * satunya jalan memulihkan alamat yang diblokir otomatis, dan tanpa itu
 * pelanggan yang salah diblokir tidak dapat dilayani tanpa menyunting basis
 * data langsung.
 */
class KeamananPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function hash(): string
    {
        return str_repeat('a', 64);
    }

    public function test_buka_blokir_menghapus_baris_dan_mencatat_jejak(): void
    {
        $this->actingAs($this->admin());

        BlockedIp::query()->create([
            'ip_hash' => $this->hash(),
            'reason' => 'brute force',
            'blocked_until' => now()->addHour(),
        ]);

        Livewire::test(KeamananPanel::class)
            ->call('bukaBlokir', $this->hash())
            ->assertHasNoErrors()
            ->assertSet('pesan', 'Blokir dibuka. Alamat itu dapat masuk kembali.');

        $this->assertDatabaseCount('blocked_ips', 0);

        // Jejak harus ada di dua tempat: catatan aktivitas admin dan catatan
        // keamanan. Blokir yang dipasang otomatis lalu dibuka manual adalah
        // kejadian yang harus dapat ditelusuri kembali.
        $this->assertDatabaseHas('activity_logs', ['action' => 'keamanan.blokir-dibuka']);

        $log = SecurityLog::query()->where('event_type', TipeKejadianKeamanan::AksiAdmin->value)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Blokir dibuka manual', (string) $log->payload_summary);
    }

    public function test_buka_blokir_menolak_sidik_jari_tidak_sah(): void
    {
        $this->actingAs($this->admin());

        BlockedIp::query()->create([
            'ip_hash' => $this->hash(),
            'reason' => 'uji',
            'blocked_until' => now()->addHour(),
        ]);

        Livewire::test(KeamananPanel::class)
            ->call('bukaBlokir', 'bukan-hash')
            ->assertSet('galatUmum', 'Sidik jari alamat tidak sah.');

        // Data yang sah tidak boleh tersentuh oleh masukan yang salah.
        $this->assertDatabaseCount('blocked_ips', 1);
    }

    public function test_buka_blokir_yang_sudah_tidak_ada_memberi_pesan_jelas(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(KeamananPanel::class)
            ->call('bukaBlokir', $this->hash())
            ->assertSet('galatUmum', 'Blokir untuk alamat ini sudah tidak ada.');
    }

    public function test_blokir_manual_memasang_batas_waktu(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(KeamananPanel::class)
            ->call('blokirManual', $this->hash(), 6)
            ->assertHasNoErrors();

        $blokir = BlockedIp::query()->untukIp($this->hash())->firstOrFail();

        $this->assertTrue($blokir->aktif());
        // Enam jam, dengan kelonggaran untuk waktu eksekusi uji.
        $this->assertEqualsWithDelta(
            now()->addHours(6)->timestamp,
            $blokir->blocked_until->timestamp,
            10,
        );
    }

    public function test_lama_blokir_manual_dibatasi(): void
    {
        $this->actingAs($this->admin());

        // Nilai di luar batas tidak boleh menghasilkan blokir abadi.
        Livewire::test(KeamananPanel::class)->call('blokirManual', $this->hash(), 999999);

        $blokir = BlockedIp::query()->untukIp($this->hash())->firstOrFail();

        $this->assertLessThanOrEqual(
            now()->addHours(720)->addMinute()->timestamp,
            $blokir->blocked_until->timestamp,
        );
    }

    public function test_pembersihan_hanya_menyentuh_percobaan_yang_tidak_terkunci(): void
    {
        $this->actingAs($this->admin());

        LoginAttempt::query()->create([
            'ip_hash' => $this->hash(),
            'email_bidx' => 'aa',
            'attempts' => 3,
            'last_attempt_at' => now()->subHour(),
            'locked_until' => null,
        ]);

        LoginAttempt::query()->create([
            'ip_hash' => str_repeat('b', 64),
            'email_bidx' => 'bb',
            'attempts' => 9,
            'last_attempt_at' => now(),
            'locked_until' => now()->addHour(),
        ]);

        Livewire::test(KeamananPanel::class)->call('bersihkanPercobaanGagal');

        // Yang terkunci tetap ada: menghapusnya membuka kesempatan menebak
        // kata sandi lagi.
        $this->assertDatabaseCount('login_attempts', 1);
        $this->assertDatabaseHas('login_attempts', ['email_bidx' => 'bb']);
    }

    public function test_grafik_selalu_dua_puluh_empat_titik(): void
    {
        $this->actingAs($this->admin());

        SecurityLog::query()->create([
            'ip_hash' => $this->hash(),
            'event_type' => TipeKejadianKeamanan::LoginGagal,
            'route' => 'admin/login',
        ]);

        $komponen = Livewire::test(KeamananPanel::class);
        $grafik = $komponen->viewData('grafik');

        $this->assertCount(24, $grafik, 'satu titik untuk setiap jam');
        $this->assertSame(1, $grafik->sum('jumlah'), 'satu kejadian terhitung sekali, bukan sekali per jam');
    }

    public function test_saringan_rentang_waktu_membatasi_daftar(): void
    {
        $this->actingAs($this->admin());

        SecurityLog::query()->create([
            'ip_hash' => $this->hash(),
            'event_type' => TipeKejadianKeamanan::LoginGagal,
            'route' => 'admin/login',
        ]);

        // Kejadian lama di luar rentang 24 jam.
        $lama = SecurityLog::query()->create([
            'ip_hash' => str_repeat('c', 64),
            'event_type' => TipeKejadianKeamanan::AksesTidakSah,
            'route' => 'admin/pesanan',
        ]);

        $lama->forceFill(['created_at' => now()->subDays(20)])->save();

        Livewire::test(KeamananPanel::class)
            ->call('aturHari', '1')
            ->assertViewHas('daftar', fn ($daftar) => $daftar->total() === 1);

        Livewire::test(KeamananPanel::class)
            ->call('aturHari', '30')
            ->assertViewHas('daftar', fn ($daftar) => $daftar->total() === 2);
    }
}
