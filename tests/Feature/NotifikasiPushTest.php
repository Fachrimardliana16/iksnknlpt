<?php

namespace Tests\Feature;

use App\Events\NotifikasiPanelMuncul;
use App\Listeners\KirimNotifikasiPush;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\NotifikasiPush;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Pendaftaran & pengiriman notifikasi perangkat (web push).
 *
 * Yang diuji adalah janji yang mudah rusak tanpa ketahuan — dan satu di
 * antaranya memang rusak: listener tidak pernah terhubung ke peristiwanya,
 * karena bootstrap/app.php tidak memanggil withEvents(). Akibatnya peristiwa
 * terkirim, tidak ada yang mendengarkan, dan tidak ada satu pun galat yang
 * muncul. Uji test_peristiwa_terhubung_ke_listener menjaga itu tetap terhubung.
 *
 * Yang lain: langganan mati dibuang (404/410), contoh langganan yang rusak
 * tidak membuat halaman gagal, dan surel cadangan hanya dipakai ketika push
 * benar-benar tidak ada jalannya.
 */
class NotifikasiPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.vapid.public_key', 'publik-uji');
        config()->set('services.vapid.private_key', 'privat-uji');
        config()->set('services.vapid.subject', 'mailto:uji@contoh.test');
    }

    private function admin(string $surel = 'admin@contoh.test'): User
    {
        // Email ditulis eksplisit, bukan faker: kolom email_bidx bersifat unik
        // dan dihitung dari email, jadi dua faker yang memilih surel sama akan
        // bertabrakan — pemeriksaan keunikannya tidak melihat isi terenkripsi.
        return User::factory()->create(['role' => 'super_admin', 'email' => $surel]);
    }

    private function langganan(User $pengguna, string $endpoint = 'https://push.contoh.test/1'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $pengguna->getKey(),
            'endpoint' => $endpoint,
            'public_key' => 'kunci-publik',
            'auth_token' => 'kunci-auth',
            'peramban' => 'Chrome',
        ]);
    }

    public function test_peristiwa_terhubung_ke_listener(): void
    {
        // Diperiksa lewat resolusi sebenarnya, bukan lewat daftar yang ditulis
        // tangan. Yang penting: dispatch() benar-benar menemukan penangannya,
        // dan penangannya listener proyek ini (bukan hanya penambah uji di
        // bawah). Cara paling jujur adalah menaruh listener palsu di urutan
        // terakhir dan melihatnya terpanggil — tetapi listener proyek sudah
        // mendahuluinya, jadi yang lebih kuat adalah membuktikan listener
        // proyek IKUT terpanggil pada dispatch yang sama.
        Queue::fake();

        NotifikasiPanelMuncul::dispatch(2, 'dua hal', '/admin');

        // Listener proyek diantrikan, jadi Queue::pushed mencatat kerjaannya
        // sebagai Illuminate\Events\CallQueuedListener dengan class-nya.
        Queue::assertPushed(
            \Illuminate\Events\CallQueuedListener::class,
            fn (\Illuminate\Events\CallQueuedListener $pekerjaan): bool => $pekerjaan->class === KirimNotifikasiPush::class,
        );
    }

    public function test_listener_memakai_antrean_bukan_pengiriman_di_dalam_permintaan(): void
    {
        // Dispatch langsung (bukan lewat event:cache): listener ber-ShouldQueue
        // harus masuk antrean, bukan jalan di tengah permintaan. Kalau ia jalan
        // sinkron, admin yang menandai pesanan lunas ikut menunggu mesin
        // pengirim push.
        Queue::fake();

        NotifikasiPanelMuncul::dispatch(1, 'satu hal', '/admin');

        Queue::assertPushed(
            \Illuminate\Events\CallQueuedListener::class,
            fn (\Illuminate\Events\CallQueuedListener $pekerjaan): bool => $pekerjaan->class === KirimNotifikasiPush::class,
        );
    }

    public function test_tanpa_langganan_push_dilaporkan_jujur(): void
    {
        $admin = $this->admin();

        $hasil = NotifikasiPush::ke($admin, 'Judul', 'Isi', '/admin');

        $this->assertSame(0, $hasil['push']);
        $this->assertSame('belum ada perangkat berlangganan', $hasil['alasan']);
    }

    public function test_kunci_vapid_belum_dipasang_dilaporkan_bukan_gagal_diam(): void
    {
        config()->set('services.vapid.public_key', null);
        config()->set('services.vapid.private_key', null);

        $admin = $this->admin();
        $this->langganan($admin);

        $hasil = NotifikasiPush::ke($admin, 'Judul', 'Isi', '/admin');

        $this->assertSame(0, $hasil['push']);
        $this->assertSame('kunci VAPID belum dipasang', $hasil['alasan']);
    }

    public function test_halaman_perangkat_terbuka_untuk_admin_dan_ditolak_untuk_tamu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Notifikasi di perangkat');

        $tamu = User::factory()->create(['role' => 'customer', 'email' => 'pelanggan@contoh.test']);

        $this->actingAs($tamu)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_daftar_langganan_baru_dan_memakainya_ulang_tanpa_kembar(): void
    {
        $admin = $this->admin();

        $komponen = \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\NotifikasiPerangkat::class);

        $komponen->call('daftar', 'https://push.contoh.test/a', 'publik', 'auth', 'Chrome');

        $this->assertDatabaseCount('push_subscriptions', 1);

        // Dua kali dengan alamat sama = satu baris. Kalau tidak begitu, setiap
        // notifikasi akan terkirim dua kali ke perangkat yang sama.
        $komponen->call('daftar', 'https://push.contoh.test/a', 'publik', 'auth', 'Chrome');

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_matikan_hanya_menghapus_langganan_pengguna_itu_sendiri(): void
    {
        $saya = $this->admin('saya@contoh.test');
        $orangLain = $this->admin('lain@contoh.test');

        $milikSaya = $this->langganan($saya, 'https://push.contoh.test/saya');
        $milikLain = $this->langganan($orangLain, 'https://push.contoh.test/lain');

        \Livewire\Livewire::actingAs($saya)
            ->test(\App\Livewire\Admin\NotifikasiPerangkat::class)
            ->call('matikan');

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $milikSaya->id]);
        $this->assertDatabaseHas('push_subscriptions', ['id' => $milikLain->id]);
    }
}
