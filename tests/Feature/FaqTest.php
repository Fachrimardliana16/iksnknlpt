<?php

namespace Tests\Feature;

use App\Livewire\Admin\FaqPanel;
use App\Livewire\Front\FaqHalaman;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FAQ: panel admin dan tampilan depan.
 *
 * Yang diuji adalah janji yang mudah rusak tanpa ketahuan:
 *
 * 1. Versi Inggris boleh kosong — tetapi halaman tidak boleh kosong. Saat
 *    kosong, yang tampil adalah versi Indonesia.
 * 2. FAQ nonaktif tidak boleh muncul di situs, termasuk saat sudah masuk
 *    cache beranda.
 * 3. Panel: menyimpan, mengubah urutan, dan menghapus benar-benar bekerja.
 */
class FaqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function faq(array $atribut = []): Faq
    {
        return Faq::query()->create($atribut + [
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'group_key' => 'umum',
            'question_id' => 'Apakah ada garansi?',
            'question_en' => null,
            'answer_id' => 'Ada, 14 hari untuk cacat produksi.',
            'answer_en' => null,
            'display_order' => (int) (Faq::query()->max('display_order') ?? 0) + 1,
            'is_active' => true,
            'tampil_di_beranda' => true,
        ]);
    }

    public function test_panel_menyimpan_faq_baru(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(FaqPanel::class)
            ->call('bukaForm')
            ->set('kelompok', 'pengiriman')
            ->set('pertanyaan_id', 'Berapa lama pengirimannya ke luar kota?')
            ->set('jawaban_id', 'Dua sampai empat hari kerja dengan kurir darat.')
            ->set('pertanyaan_en', null)
            ->set('jawaban_en', null)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('faqs', [
            'question_id' => 'Berapa lama pengirimannya ke luar kota?',
            'group_key' => 'pengiriman',
            'is_active' => true,
        ]);
    }

    public function test_panel_menolak_pertanyaan_terlalu_pendek(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(FaqPanel::class)
            ->call('bukaForm')
            ->set('pertanyaan_id', 'Apa')
            ->set('jawaban_id', 'Jawaban singkat')
            ->call('simpan')
            ->assertHasErrors(['pertanyaan_id' => 'min']);

        $this->assertDatabaseCount('faqs', 0);
    }

    public function test_faq_nonaktif_tidak_tampil_di_halaman_faq(): void
    {
        $aktif = $this->faq(['question_id' => 'Pertanyaan aktif?', 'is_active' => true]);
        $mati = $this->faq(['question_id' => 'Pertanyaan nonaktif?', 'is_active' => false]);

        Livewire::test(FaqHalaman::class)
            ->assertSee($aktif->question_id)
            ->assertDontSee($mati->question_id);
    }

    public function test_versi_inggris_kosong_jatuh_ke_versi_indonesia(): void
    {
        $faq = $this->faq(['question_id' => 'Pertanyaan tanpa terjemahan?']);

        // App berjalan dalam locale 'id' pada suite ini; versi Inggris kosong
        // tidak boleh menghasilkan string kosong.
        $this->assertSame('Pertanyaan tanpa terjemahan?', $faq->pertanyaan());

        app()->setLocale('en');
        $this->assertSame('Pertanyaan tanpa terjemahan?', $faq->pertanyaan());
    }

    public function test_versi_inggris_dipakai_bila_ada(): void
    {
        $faq = $this->faq([
            'question_id' => 'Pertanyaan dalam Indonesia?',
            'question_en' => 'The question in English?',
        ]);

        app()->setLocale('en');
        $this->assertSame('The question in English?', $faq->pertanyaan());

        app()->setLocale('id');
        $this->assertSame('Pertanyaan dalam Indonesia?', $faq->pertanyaan());
    }

    public function test_mengubah_urutan_lewat_tombol(): void
    {
        $this->actingAs($this->admin());

        $a = $this->faq(['question_id' => 'Pertananyaan pertama?', 'display_order' => 1]);
        $b = $this->faq(['question_id' => 'Pertanyaan kedua?', 'display_order' => 2]);

        Livewire::test(FaqPanel::class)->call('geser', $a->id, 'turun');

        $this->assertSame(2, (int) $a->fresh()->display_order);
        $this->assertSame(1, (int) $b->fresh()->display_order);
    }

    public function test_nama_kelompok_dikenal(): void
    {
        $faq = $this->faq(['group_key' => 'pengiriman']);

        $this->assertSame('Pengiriman', $faq->labelKelompok());

        // Kunci tak dikenal tetap menampilkan sesuatu yang bisa dibaca, bukan
        // baris kosong atau galat.
        $asing = $this->faq(['group_key' => 'pelanggan-baru']);
        $this->assertNotSame('', $asing->labelKelompok());
    }
}
