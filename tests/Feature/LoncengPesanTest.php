<?php

namespace Tests\Feature;

use App\Livewire\Admin\LoncengPesan;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lonceng pesan di kepala panel.
 *
 * Bukan pengganti halaman Pesan — sekadar pintu masuk: lencana menghitung yang
 * belum dibaca, daftar pendek, dan "Lihat semua" menuju halaman penuh. Yang
 * diuji di sini adalah dua janji yang mudah rusak tanpa ketahuan:
 *
 * 1. Lencana hanya menghitung pesan BELUM dibaca. Membaca satu mengurangi
 *    angkanya satu.
 * 2. "Lihat semua" benar-benar mengarah ke halaman Pesan (admin.messages.index),
 *    bukan ke notifikasi atau ke dashboard.
 */
class LoncengPesanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): \App\Models\User
    {
        return \App\Models\User::factory()->create(['role' => 'super_admin']);
    }

    /** Pesan kontak; kolom isi terenkripsi lewat cast model. */
    private function pesan(string $email, bool $sudahDibaca = false): ContactMessage
    {
        return ContactMessage::query()->create([
            'name' => 'Pengirim',
            'email' => $email,
            'subject' => 'Subjek '.$email,
            'message' => 'Isi pesan.',
            'is_read' => $sudahDibaca,
        ]);
    }

    public function test_lencana_menghitung_yang_belum_dibaca(): void
    {
        $this->pesan('a@contoh.test');
        $this->pesan('b@contoh.test');
        $this->pesan('c@contoh.test', true);

        $this->actingAs($this->admin());

        Livewire::test(LoncengPesan::class)
            ->assertViewHas('belum', 2)
            ->assertViewHas('daftar', fn ($d) => $d->count() <= 6);
    }

    public function test_membaca_satu_mengurangi_lencana(): void
    {
        $pesan = $this->pesan('a@contoh.test');

        $this->actingAs($this->admin());

        Livewire::test(LoncengPesan::class)
            ->assertViewHas('belum', 1)
            ->call('bukaPesan', $pesan->id);

        // Setelah dibaca, lencana kosong.
        Livewire::test(LoncengPesan::class)->assertViewHas('belum', 0);
    }

    /**
     * Tautan "Lihat semua" mengarah ke HALAMAN PESAN, bukan ke lonceng
     * notifikasi atau ke dashboard.
     *
     * Daftar pendek memang tidak dirender selama panel tertutup — itu
     * rancangannya, bukan kelalaian. Karena itu panelnya dibuka dulu.
     */
    public function test_lihat_semua_mengarah_ke_halaman_pesan(): void
    {
        $this->pesan('a@contoh.test');

        $this->actingAs($this->admin());

        Livewire::test(LoncengPesan::class)
            ->call('alihkan')
            ->assertSee('Lihat semua pesan')
            ->assertSee(route('admin.messages.index'));
    }
}
