<?php

namespace Tests\Feature;

use App\Enums\GrupPengaturan;
use App\Livewire\Admin\HeroBuilder;
use App\Livewire\Admin\PengaturanSitus;
use App\Models\ActivityLog;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checklist F4-01..F4-11.
 *
 * Pengujian ini memakai basis data sungguhan dengan data awal yang benar-benar
 * di-seed, bukan nilai palsu. Yang diuji adalah perilaku yang tampak dari luar:
 * apa yang tersimpan, apa yang tercatat, dan apa yang sampai ke peramban.
 */
class PengaturanSitusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);

        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_blocked' => false,
        ]);
    }

    // ── F4-01 sampai F4-06: menyimpan nilai per tab ──────────────────

    public function test_tab_identitas_menyimpan_nama_dan_tagline(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->assertSet('tab', 'identity')
            ->set('nilai.site_name', 'Iksan Knalpot Racing')
            ->set('nilai.site_tagline', 'Knalpot custom Yogyakarta')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('Iksan Knalpot Racing', Setting::query()->where('key', 'site_name')->value('value'));
        $this->assertSame('Knalpot custom Yogyakarta', Setting::query()->where('key', 'site_tagline')->value('value'));
    }

    public function test_tab_kontak_menyimpan_whatsapp_dan_alamat(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Kontak->value)
            ->assertSet('tab', 'contact')
            ->set('nilai.whatsapp_number', '628999888777')
            ->set('nilai.address_full', "Jl. Kaliurang KM 8\nSleman, Yogyakarta")
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('628999888777', Setting::query()->where('key', 'whatsapp_number')->value('value'));
        $this->assertStringContainsString('Kaliurang', (string) Setting::query()->where('key', 'address_full')->value('value'));
    }

    /** F4-03: URL media sosial wajib https. */
    public function test_tautan_media_sosial_javascript_ditolak(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Sosial->value)
            ->set('nilai.social_instagram', 'javascript:alert(1)')
            ->call('simpan')
            ->assertHasErrors('nilai.social_instagram');

        $this->assertNotSame('javascript:alert(1)', Setting::query()->where('key', 'social_instagram')->value('value'));
    }

    public function test_tautan_media_sosial_http_ditolak(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Sosial->value)
            ->set('nilai.social_instagram', 'http://instagram.com/iksanknalpot')
            ->call('simpan')
            ->assertHasErrors('nilai.social_instagram');
    }

    public function test_tautan_media_sosial_https_diterima(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Sosial->value)
            ->set('nilai.social_instagram', 'https://instagram.com/iksanknalpot')
            ->set('nilai.social_instagram_visible', true)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('https://instagram.com/iksanknalpot', Setting::query()->where('key', 'social_instagram')->value('value'));
    }

    public function test_memilih_tidak_tampil_menyimpan_boolean_nol(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Sosial->value)
            ->set('nilai.social_facebook_visible', false)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('0', Setting::query()->where('key', 'social_facebook_visible')->value('value'));
    }

    public function test_tab_lanjutan_menolak_pajak_bukan_angka(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            ->set('nilai.tax_percent', 'sebelas persen')
            ->call('simpan')
            ->assertHasErrors('nilai.tax_percent');
    }

    // ── F4-04: kredensial tidak pernah kembali ke antarmuka ──────────

    public function test_secret_tidak_pernah_dikirim_balik_ke_ui(): void
    {
        $this->actingAs($this->admin());

        Setting::query()->where('key', 'paypal_client_secret')->first()
            ->update(['value' => 'RAHASIA-SUPER-123']);

        $komponen = Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value);

        // Nilai mentahnya benar-benar tersimpan di basis data, terenkripsi.
        $tersimpan = Setting::query()->where('key', 'paypal_client_secret')->first();
        $this->assertSame('RAHASIA-SUPER-123', $tersimpan->value);

        // Tetapi tidak pernah muncul di properti komponen…
        $this->assertSame('', $komponen->get('nilai')['paypal_client_secret']);

        // …maupun di HTML yang dikirim ke peramban.
        $komponen->assertDontSee('RAHASIA-SUPER-123');

        // Yang tampil hanya penanda bahwa nilainya sudah diisi.
        $this->assertTrue($komponen->get('nilai')['__terisi_paypal_client_secret']);
        $komponen->assertSee('tersimpan');
    }

    public function test_kredensial_kosong_tidak_menimpa_yang_tersimpan(): void
    {
        $this->actingAs($this->admin());

        Setting::query()->where('key', 'paypal_client_secret')->first()
            ->update(['value' => 'JANGAN-TERTIMPA']);

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            // Kolom rahasia dibiarkan kosong, hanya mode yang diubah.
            ->set('nilai.paypal_mode', 'live')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(
            'JANGAN-TERTIMPA',
            Setting::query()->where('key', 'paypal_client_secret')->first()->value,
        );
        $this->assertSame('live', Setting::query()->where('key', 'paypal_mode')->value('value'));
    }

    public function test_mengganti_kredensial_menyimpan_nilai_baru_terenkripsi(): void
    {
        $this->actingAs($this->admin());

        Setting::query()->where('key', 'paypal_client_secret')->first()
            ->update(['value' => 'LAMA']);

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            ->set('nilai.paypal_client_secret', 'BARU')
            ->call('simpan')
            ->assertHasNoErrors();

        $baris = Setting::query()->where('key', 'paypal_client_secret')->first();

        $this->assertSame('BARU', $baris->value, 'Nilai terdekripsi harus nilai baru.');
        $this->assertNotSame('BARU', $baris->getRawOriginal('value'), 'Nilai di basis data harus tetap terenkripsi.');
    }

    public function test_hapus_kredensial_mengosongkan_nilai(): void
    {
        $this->actingAs($this->admin());

        Setting::query()->where('key', 'paypal_client_secret')->first()
            ->update(['value' => 'AKAN-DIHAPUS']);

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            ->call('hapusKredensial', 'paypal_client_secret')
            ->assertHasNoErrors();

        $this->assertNull(Setting::query()->where('key', 'paypal_client_secret')->value('value'));
    }

    public function test_hapus_kunci_bukan_rahasia_ditolak(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            ->call('hapusKredensial', 'paypal_mode')
            ->assertSet('galat', fn (?string $g): bool => str_contains((string) $g, 'bukan kredensial'));

        $this->assertNotNull(Setting::query()->where('key', 'paypal_mode')->value('value'));
    }

    // ── F4-07: catatan aktivitas ─────────────────────────────────────

    public function test_perubahan_setting_tercatat_di_activity_logs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(PengaturanSitus::class)
            ->set('nilai.site_name', 'Nama Baru')
            ->call('simpan');

        $log = ActivityLog::query()->where('action', 'setting.diubah')->latest('id')->first();

        $this->assertNotNull($log, 'Perubahan pengaturan harus meninggalkan catatan aktivitas.');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertArrayHasKey('site_name', $log->changes_json);
        $this->assertSame('Nama Baru', $log->changes_json['site_name']['baru']);
    }

    /** Nilai terenkripsi dicatat sebagai [changed], bukan nilainya. */
    public function test_nilai_terenkripsi_tercatat_sebagai_penanda(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', GrupPengaturan::Pembayaran->value)
            ->set('nilai.paypal_client_secret', 'RAHASIA-JANGAN-TERCATAT')
            ->call('simpan');

        $log = ActivityLog::query()->where('action', 'setting.diubah')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('[changed]', $log->changes_json['paypal_client_secret']);

        // Nilai rahasia tidak boleh muncul di mana pun pada isi catatan.
        $this->assertStringNotContainsString(
            'RAHASIA-JANGAN-TERCATAT',
            (string) json_encode($log->changes_json),
        );
    }

    public function test_simpan_tanpa_perubahan_tidak_membuat_catatan(): void
    {
        $this->actingAs($this->admin());

        ActivityLog::query()->delete();

        Livewire::test(PengaturanSitus::class)
            ->call('simpan')
            ->assertSet('pesanSukses', 'Tidak ada perubahan untuk disimpan.');

        $this->assertSame(0, ActivityLog::query()->where('action', 'setting.diubah')->count());
    }

    // ── F4-08: cache pengaturan ──────────────────────────────────────

    public function test_cache_pengaturan_diinvalidasi_setelah_simpan(): void
    {
        $this->actingAs($this->admin());

        // Isi cache dengan nilai lama.
        Pengaturan::segarkan();
        $this->assertSame('Iksan Knalpot', Pengaturan::instance()->teks('site_name'));

        Livewire::test(PengaturanSitus::class)
            ->set('nilai.site_name', 'Iksan Knalpot Baru')
            ->call('simpan')
            ->assertHasNoErrors();

        // Tanpa invalidasi, pembacaan berikutnya masih mengembalikan nilai lama.
        $this->assertSame('Iksan Knalpot Baru', Pengaturan::instance()->teks('site_name'));
    }

    // ── F4-09 sampai F4-11: hero builder ────────────────────────────

    public function test_hero_menambah_slide_kosong_dan_langsung_membukanya(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(HeroBuilder::class)
            ->call('tambah')
            ->assertSet('galat', null);

        $this->assertSame(1, HeroSlide::query()->count());

        $slide = HeroSlide::query()->first();
        $this->assertFalse($slide->is_active, 'Slide baru harus nonaktif sampai punya berkas.');
    }

    public function test_hero_menolak_lebih_dari_lima_slide(): void
    {
        $this->actingAs($this->admin());

        for ($i = 0; $i < 5; $i++) {
            HeroSlide::create([
                'title_id' => 'Slide '.$i, 'title_en' => 'Slide '.$i,
                'media_path' => 'hero/a.webp', 'display_order' => $i, 'is_active' => $i === 0,
            ]);
        }

        Livewire::test(HeroBuilder::class)
            ->call('tambah')
            ->assertSet('galat', fn (?string $g): bool => str_contains((string) $g, 'batas 5'));

        $this->assertSame(5, HeroSlide::query()->count());
    }

    public function test_hero_menolak_menonaktifkan_slide_aktif_terakhir(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Satu-satunya', 'title_en' => 'Only',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => true,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('ubahAktif', $slide->id)
            ->assertSet('galat', fn (?string $g): bool => str_contains((string) $g, 'Minimal satu slide'));

        $this->assertTrue($slide->fresh()->is_active);
    }

    public function test_hero_tidak_dapat_mengaktifkan_slide_tanpa_berkas(): void
    {
        $this->actingAs($this->admin());

        $aktif = HeroSlide::create([
            'title_id' => 'Aktif', 'title_en' => 'Active',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => true,
        ]);

        $kosong = HeroSlide::create([
            'title_id' => 'Kosong', 'title_en' => 'Empty',
            'media_path' => '', 'display_order' => 1, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('ubahAktif', $kosong->id)
            ->assertSet('galat', fn (?string $g): bool => str_contains((string) $g, 'belum punya berkas'));

        $this->assertFalse($kosong->fresh()->is_active);
        $this->assertTrue($aktif->fresh()->is_active);
    }

    public function test_hero_urutan_rapat_setelah_geser_dan_hapus(): void
    {
        $this->actingAs($this->admin());

        $ids = [];

        foreach (range(0, 3) as $i) {
            $ids[] = HeroSlide::create([
                'title_id' => 'Slide '.$i, 'title_en' => 'Slide '.$i,
                'media_path' => 'hero/'.$i.'.webp', 'display_order' => $i, 'is_active' => $i === 0,
            ])->id;
        }

        // Geser slide terakhir ke atas sebanyak dua kali.
        Livewire::test(HeroBuilder::class)
            ->call('naik', $ids[3])
            ->call('naik', $ids[3]);

        $urutan = HeroSlide::query()->orderBy('display_order')->pluck('id')->all();

        $this->assertSame([$ids[0], $ids[3], $ids[1], $ids[2]], $urutan);
        $this->assertSame([0, 1, 2, 3], HeroSlide::query()->orderBy('display_order')->pluck('display_order')->all());

        // Hapus slide kedua (bukan satu-satunya yang aktif), urutan harus
        // dirapatkan kembali dari 0 tanpa celah.
        Livewire::test(HeroBuilder::class)->call('hapus', $ids[1]);

        $this->assertSame([0, 1, 2], HeroSlide::query()->orderBy('display_order')->pluck('display_order')->all());
        $this->assertSame([$ids[0], $ids[3], $ids[2]], HeroSlide::query()->orderBy('display_order')->pluck('id')->all());
    }

    /** Slide aktif terakhir tidak boleh dihapus — hero tidak boleh kosong. */
    public function test_hero_menolak_menghapus_slide_aktif_terakhir(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Terakhir', 'title_en' => 'Last',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => true,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('hapus', $slide->id)
            ->assertSet('galat', fn (?string $g): bool => str_contains((string) $g, 'satu-satunya'));

        $this->assertSame(1, HeroSlide::query()->count());
    }

    public function test_hero_terapkan_urutan_menolak_id_yang_bukan_milik_slide(): void
    {
        $this->actingAs($this->admin());

        $a = HeroSlide::create(['title_id' => 'A', 'title_en' => 'A', 'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => true]);
        $b = HeroSlide::create(['title_id' => 'B', 'title_en' => 'B', 'media_path' => 'hero/b.webp', 'display_order' => 1, 'is_active' => false]);

        Livewire::test(HeroBuilder::class)
            ->call('terapkanUrutan', [$b->id, 999999, 'bukan-angka', $a->id]);

        $this->assertSame(
            [$b->id, $a->id],
            HeroSlide::query()->orderBy('display_order')->pluck('id')->all(),
        );
    }

    public function test_hero_menyimpan_judul_dua_bahasa(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Awal', 'title_en' => 'Start',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->set('form.title_id', 'Knalpot Full System NMAX')
            ->set('form.title_en', 'NMAX Full System Exhaust')
            ->set('form.subtitle_id', 'Titanium, suara bass')
            ->set('form.subtitle_en', 'Titanium, deep tone')
            ->set('form.primary_button_label_id', 'Lihat katalog')
            ->set('form.primary_button_label_en', 'Browse catalog')
            ->set('form.primary_button_url', 'https://iksanknalpot.test/produk')
            ->call('simpan')
            ->assertHasNoErrors();

        $slide->refresh();

        $this->assertSame('Knalpot Full System NMAX', $slide->title_id);
        $this->assertSame('NMAX Full System Exhaust', $slide->title_en);
        $this->assertSame('Titanium, deep tone', $slide->subtitle_en);
        $this->assertSame('https://iksanknalpot.test/produk', $slide->primary_button_url);
    }

    public function test_hero_menolak_jadwal_berakhir_sebelum_mulai(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Jadwal', 'title_en' => 'Schedule',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->set('form.title_id', 'Jadwal')
            ->set('form.title_en', 'Schedule')
            ->set('form.starts_at', '2026-12-31T10:00')
            ->set('form.ends_at', '2026-01-01T10:00')
            ->call('simpan')
            ->assertHasErrors('form.ends_at');
    }

    public function test_hero_menolak_judul_kosong(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Ada', 'title_en' => 'Exists',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->set('form.title_id', '')
            ->call('simpan')
            ->assertHasErrors('form.title_id');
    }

    public function test_hero_menolak_url_tombol_bukan_url(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Tautan', 'title_en' => 'Link',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->set('form.title_id', 'Tautan')
            ->set('form.title_en', 'Link')
            ->set('form.primary_button_url', 'javascript:alert(1)')
            ->call('simpan')
            ->assertHasErrors('form.primary_button_url');
    }

    /** F4-11: video maksimum 8 MB dan 15 detik, ditegakkan di server. */
    public function test_hero_menolak_video_lebih_dari_delapan_megabita(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Video', 'title_en' => 'Video',
            'media_path' => '', 'media_type' => 'video', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->set('form.media_type', 'video')
            ->set('berkasVideo', \Illuminate\Http\UploadedFile::fake()->create('promo.mp4', 9000, 'video/mp4'))
            ->call('simpan')
            ->assertHasErrors('berkasVideo');
    }

    public function test_hero_dapat_mengubah_titik_fokus(): void
    {
        $this->actingAs($this->admin());

        $slide = HeroSlide::create([
            'title_id' => 'Fokus', 'title_en' => 'Focus',
            'media_path' => 'hero/a.webp', 'display_order' => 0, 'is_active' => false,
        ]);

        Livewire::test(HeroBuilder::class)
            ->call('sunting', $slide->id)
            ->call('ubahFokus', 0.25, 0.75);

        $this->assertEqualsWithDelta(0.25, $slide->fresh()->focal_point_x, 0.001);
        $this->assertEqualsWithDelta(0.75, $slide->fresh()->focal_point_y, 0.001);
        $this->assertSame('25% 75%', $slide->fresh()->posisiFokus());
    }

    public function test_hero_pindah_pratinjau_antara_ponsel_dan_desktop(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(HeroBuilder::class)
            ->assertSet('pratinjau', 'ponsel')
            ->call('ubahPratinjau', 'desktop')
            ->assertSet('pratinjau', 'desktop')
            ->call('ubahPratinjau', 'ponsel')
            ->assertSet('pratinjau', 'ponsel');
    }

    public function test_hero_tab_tidak_sah_diabaikan(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(PengaturanSitus::class)
            ->call('pilihTab', 'bukan-tab')
            ->assertSet('tab', 'identity');
    }
}
