<?php

namespace Tests\Feature;

use App\Livewire\Admin\PesanKontak;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Keamanan pesan kontak (checklist Tahap 5).
 *
 * Sebelum ini `subject` adalah satu-satunya kolom pesan yang masih tersimpan
 * sebagai teks terbuka, sementara name, email, phone, dan message sudah
 * terenkripsi. Isinya ditulis pengunjung bebas — sering memuat nama orang atau
 * nomor pesanan.
 *
 * Yang diuji: subjek baru tersimpan terenkripsi dan tidak terbaca mentah saat
 * basis data dibuka langsung, nilai lama tetap terbaca lewat model, pencarian
 * di panel tetap menemukan pesan lewat subjek, dan user agent panjang dipangkas.
 */
class KeamananPesanKontakTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => 'pesan-admin@contoh.test']);
    }

    private function pesan(array $atribut = []): ContactMessage
    {
        return ContactMessage::query()->create($atribut + [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'message' => 'Isi pesan uji.',
            'is_read' => false,
        ]);
    }

    // ── 5.1 Subjek terenkripsi ──────────────────────────────────────

    public function test_subjek_baru_tersimpan_terenkripsi(): void
    {
        $pesan = $this->pesan(['subject' => 'Tanya knalpot Vario 160']);

        $mentah = DB::table('contact_messages')->where('id', $pesan->id)->first();

        // Basis data yang bocor tidak boleh membocorkan isi subjek.
        $this->assertNotSame('Tanya knalpot Vario 160', $mentah->subject);

        // Nilai tetap terbaca lewat model, karena cast yang mendekripsi.
        $this->assertSame('Tanya knalpot Vario 160', $pesan->fresh()->subject);
    }

    public function test_kolom_subjek_menerima_ciphertext_panjang(): void
    {
        // Ciphertext Laravel selalu lebih panjang dari teks aslinya. Bila kolom
        // masih VARCHAR(255), subjek panjang akan gagal tersimpan — dan itu
        // ditemukan pembeli sebagai pesan kontak yang hilang tanpa sebab.
        $panjang = str_repeat('A', 400);

        $pesan = $this->pesan(['subject' => $panjang]);

        $this->assertSame($panjang, $pesan->fresh()->subject);
    }

    // ── 5.2 Nilai lama tetap terbaca ────────────────────────────────

    public function test_nilai_lama_teks_terbuka_tetap_dapat_dibaca(): void
    {
        // Baris yang ditulis SEBELUM migrasi masih berupa teks terbuka. Model
        // harus tetap membacanya, bukan menampilkan galat dekripsi.
        $id = DB::table('contact_messages')->insertGetId([
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'terenkripsi-lama',
            'email' => 'terenkripsi-lama',
            'phone' => null,
            'subject' => 'Subjek teks terbuka dari sebelum migrasi',
            'message' => 'terenkripsi-lama',
            'ip_hash' => null,
            'user_agent' => 'Seeder/1.0',
            'is_read' => false,
            'replied_at' => null,
            'replied_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pesan = ContactMessage::query()->findOrFail($id);

        $this->assertSame('Subjek teks terbuka dari sebelum migrasi', $pesan->subject);
    }

    // ── Pencarian panel tetap bekerja ───────────────────────────────

    public function test_pencarian_subjek_di_panel_menemukan_pesan(): void
    {
        $this->actingAs($this->admin());

        $dicari = $this->pesan(['subject' => 'Tanya stok NMAX', 'email' => 'cari@example.test', 'name' => 'Pencari Subjek']);
        $this->pesan(['subject' => 'Keluhan pengiriman', 'email' => 'lain@example.test', 'name' => 'Bukan Yang Dicari']);

        $html = Livewire::test(PesanKontak::class)
            ->set('cari', 'stok NMAX')
            ->html();

        // Daftar menampilkan nama pengirim, jadi itu yang dicari di HTML.
        $this->assertStringContainsString('Pencari Subjek', $html);
        $this->assertStringNotContainsString('Bukan Yang Dicari', $html);
    }

    public function test_pencarian_isi_pesan_juga_menemukan_pesan(): void
    {
        $this->actingAs($this->admin());

        $this->pesan(['subject' => 'Subjek netral', 'message' => 'Saya butuh pipa 45mm', 'email' => 'isi@example.test', 'name' => 'Pencari Isi']);
        $this->pesan(['subject' => 'Subjek netral', 'message' => 'Tanya garansi', 'email' => 'lain@example.test', 'name' => 'Bukan Yang Dicari']);

        $html = Livewire::test(PesanKontak::class)
            ->set('cari', 'pipa 45mm')
            ->html();

        $this->assertStringContainsString('Pencari Isi', $html);
        $this->assertStringNotContainsString('Bukan Yang Dicari', $html);
    }

    public function test_pencarian_email_persis_menemukan_pesan(): void
    {
        $this->actingAs($this->admin());

        $this->pesan(['email' => 'cari@example.test', 'name' => 'Pencari Surel']);
        $this->pesan(['email' => 'lain@example.test', 'name' => 'Bukan Yang Dicari']);

        $html = Livewire::test(PesanKontak::class)
            ->set('cari', 'cari@example.test')
            ->html();

        $this->assertStringContainsString('Pencari Surel', $html);
        $this->assertStringNotContainsString('Bukan Yang Dicari', $html);
    }

    public function test_pencarian_tidak_menemukan_apa_pun_tidak_error(): void
    {
        $this->actingAs($this->admin());

        $this->pesan(['subject' => 'Tanya stok', 'email' => 'satu@example.test']);

        Livewire::test(PesanKontak::class)
            ->set('cari', 'kata-yang-tidak-ada-sama-sekali')
            ->assertOk();
    }

    // ── 5.3 User agent dipangkas ────────────────────────────────────

    public function test_user_agent_panjang_dipangkas_255_karakter(): void
    {
        $panjang = str_repeat('Mozilla/5.0 ', 100);

        $pesan = $this->pesan(['user_agent' => $panjang]);

        $tersimpan = DB::table('contact_messages')->where('id', $pesan->id)->value('user_agent');

        $this->assertSame(255, mb_strlen((string) $tersimpan));
    }

    public function test_user_agent_kosong_tetap_dapat_disimpan(): void
    {
        $pesan = $this->pesan(['user_agent' => null]);

        $this->assertNull($pesan->fresh()->user_agent);
    }

    // ── Seeder idempoten ────────────────────────────────────────────

    public function test_seeder_pesan_kontak_tidak_menggandakan_baris(): void
    {
        // Seeder memakai email sebagai kunci, bukan subjek yang terenkripsi.
        // Bila subjek yang dipakai, setiap seeding membuat baris baru.
        $this->seed(\Database\Seeders\ContactMessageSeeder::class);
        $pertama = ContactMessage::query()->count();

        $this->seed(\Database\Seeders\ContactMessageSeeder::class);
        $kedua = ContactMessage::query()->count();

        $this->assertSame($pertama, $kedua);
        $this->assertGreaterThan(0, $kedua);
    }

    public function test_seeder_pesan_kontak_mengisi_subjek_dengan_benar(): void
    {
        $this->seed(\Database\Seeders\ContactMessageSeeder::class);

        $pesan = ContactMessage::query()->whereNotNull('subject')->first();

        $this->assertNotSame('', (string) $pesan->subject);
    }
}
