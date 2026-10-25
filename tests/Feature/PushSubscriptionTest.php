<?php

namespace Tests\Feature;

use App\Livewire\Admin\NotifikasiPerangkat;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengambilalihan langganan push antar admin (audit keamanan, temuan PUSH-01).
 *
 * `NotifikasiPerangkat::daftar()` memakai updateOrCreate dengan kunci
 * `endpoint` — nilai yang datang dari sisi klien. Akibatnya admin yang
 * mengetahui endpoint milik admin lain dapat mendaftarkan endpoint itu sebagai
 * miliknya sendiri, dan baris pemilik lama dipindahkan kepadanya.
 *
 * Yang dibuktikan di sini sesudah perbaikan (kunci = endpoint + user_id):
 * permintaan penyerang TIDAK lagi memindahkan baris korban, sedangkan
 * pendaftaran endpoint milik sendiri tetap satu baris dan "Matikan" hanya
 * menghapus milik pengguna itu.
 */
class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'email' => $email,
        ]);
    }

    public function test_endpoint_milik_admin_lain_tidak_dapat_diambil_alih(): void
    {
        $korban = $this->admin('korban@contoh.test');
        $penyerang = $this->admin('penyerang@contoh.test');
        $endpoint = 'https://push.example.com/korban-endpoint-rahasia';

        PushSubscription::query()->create([
            'user_id' => $korban->id,
            'endpoint' => $endpoint,
            'public_key' => 'kunci-korban',
            'auth_token' => 'token-korban',
            'peramban' => 'Chrome',
        ]);

        $this->assertSame($korban->id, PushSubscription::query()->first()->user_id);

        // Penyerang masuk, lalu mendaftarkan endpoint milik korban.
        Livewire::actingAs($penyerang)
            ->test(NotifikasiPerangkat::class)
            ->call('daftar', $endpoint, 'kunci-penyerang', 'token-penyerang', 'Chrome');

        $barisKorban = PushSubscription::query()->where('user_id', $korban->id)->firstOrFail();

        $this->assertSame($korban->id, $barisKorban->user_id, 'Baris langganan tidak boleh berpindah pemilik.');
        $this->assertSame('token-korban', $barisKorban->auth_token, 'Kunci korban tidak boleh ditimpa.');
        $this->assertSame('kunci-korban', $barisKorban->public_key);
        $this->assertSame($endpoint, $barisKorban->endpoint);
        $this->assertSame(1, PushSubscription::query()->count(), 'Tidak boleh ada baris kembar.');
        $this->assertSame(0, PushSubscription::query()->where('user_id', $penyerang->id)->count(), 'Penyerang tidak boleh mendapat baris.');
    }

    public function test_endpoint_sendiri_tetap_terdaftar(): void
    {
        $saya = $this->admin('sendiri@contoh.test');

        Livewire::actingAs($saya)
            ->test(NotifikasiPerangkat::class)
            ->call('daftar', 'https://push.example.com/milik-sendiri', 'kunci', 'token', 'Firefox');

        $baris = PushSubscription::query()->firstOrFail();

        $this->assertSame($saya->id, $baris->user_id);
        $this->assertSame('Firefox', $baris->peramban);
    }

    public function test_daftar_dua_kali_tidak_menghasilkan_baris_kembar(): void
    {
        $saya = $this->admin('dua@contoh.test');
        $endpoint = 'https://push.example.com/milik-sendiri-2';

        Livewire::actingAs($saya)
            ->test(NotifikasiPerangkat::class)
            ->call('daftar', $endpoint, 'kunci', 'token-1', 'Firefox');

        // Peramban yang menekan "Izinkan" dua kali: tetap satu baris.
        Livewire::actingAs($saya)
            ->test(NotifikasiPerangkat::class)
            ->call('daftar', $endpoint, 'kunci', 'token-2', 'Firefox');

        $this->assertSame(1, PushSubscription::query()->count(), 'Pendaftaran ulang endpoint sendiri harus memperbarui, bukan menduplikasi.');
        $this->assertSame('token-2', PushSubscription::query()->firstOrFail()->auth_token);
    }

    public function test_matikan_hanya_menghapus_milik_sendiri(): void
    {
        $korban = $this->admin('korban2@contoh.test');
        $penyerang = $this->admin('penyerang2@contoh.test');

        PushSubscription::query()->create([
            'user_id' => $korban->id,
            'endpoint' => 'https://push.example.com/korban-2',
            'public_key' => 'k',
            'auth_token' => 't',
            'peramban' => 'Chrome',
        ]);

        PushSubscription::query()->create([
            'user_id' => $penyerang->id,
            'endpoint' => 'https://push.example.com/penyerang-2',
            'public_key' => 'k',
            'auth_token' => 't',
            'peramban' => 'Chrome',
        ]);

        Livewire::actingAs($penyerang)->test(NotifikasiPerangkat::class)->call('matikan');

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame($korban->id, PushSubscription::query()->first()->user_id);
    }
}
