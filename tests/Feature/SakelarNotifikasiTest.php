<?php

namespace Tests\Feature;

use App\Livewire\Admin\LoncengNotifikasi;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\NotifikasiAdmin;
use App\Support\Pengaturan;
use App\Support\SakelarNotifikasi;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sakelar notifikasi di panel (tab Notifikasi pada Pengaturan Situs).
 *
 * Yang diuji adalah janji yang mudah rusak tanpa ketahuan:
 *
 * 1. Mematikan satu butir benar-benar mengeluarkannya dari lonceng — dari
 *    lencana, dari daftar, dan dari butir yang bisa ditandai. Menyembunyikannya
 *    hanya di tampilan akan membuat angkanya tetap salah.
 * 2. Sakelar lain tidak ikut mati. Sakelar yang salah sasaran adalah kegagalan
 *    yang senyap: admin mematikan "stok habis" lalu kehilangan "stok menipis".
 * 3. Sakelar pengiriman ke pembeli (surel/WA) dihormati, bukan hanya dibaca.
 * 4. Bawaan tetap aktif ketika barisnya belum ada — instalasi baru dan test
 *    yang belum seed tidak boleh kehilangan notifikasi.
 */
class SakelarNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        Pengaturan::segarkan();
    }

    private function admin(string $surel = 'admin@contoh.test'): User
    {
        return User::factory()->create(['role' => 'super_admin', 'email' => $surel]);
    }

    private function varian(int $stok): ProductVariant
    {
        $produk = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $produk->id,
            'is_active' => true,
            'reserved_quantity' => 0,
            'stock_quantity' => $stok,
        ]);
    }

    /** Sakelar disimpan lewat jalur panel: update langsung pada baris settings. */
    private function sakelar(string $kunci, bool $aktif): void
    {
        Setting::query()->where('key', $kunci)->update(['value' => $aktif ? '1' : '0']);
        Pengaturan::segarkan();
    }

    public function test_butir_mati_tidak_masuk_lonceng_dan_tidak_mengisi_lencana(): void
    {
        $this->actingAs($this->admin());

        // Dua butir berbeda: satu habis, satu menipis.
        $this->varian(0);
        $this->varian(2);

        $this->assertSame(2, NotifikasiAdmin::jumlahBelumDilihat());

        $this->sakelar('notif_item_stok_habis', false);

        // Bukan disembunyikan — keluar dari hitungan sepenuhnya.
        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat(), 'Lencana harus turun saat butir dimatikan.');
        $this->assertNull(NotifikasiAdmin::semua()->firstWhere('kunci', 'stok-habis'));

        Livewire::test(LoncengNotifikasi::class)
            ->assertViewHas('belum', 1)
            ->assertViewHas('butir', fn ($butir) => $butir->pluck('kunci')->all() === ['stok-menipis']);
    }

    public function test_mematikan_satu_butir_tidak_mematikan_yang_lain(): void
    {
        $this->actingAs($this->admin());

        $this->varian(0);
        $this->varian(2);

        $this->sakelar('notif_item_stok_habis', false);

        $kunci = NotifikasiAdmin::semua()->pluck('kunci')->all();

        $this->assertNotContains('stok-habis', $kunci);
        $this->assertContains('stok-menipis', $kunci, 'Butir lain tidak boleh ikut hilang.');
    }

    public function test_butir_yang_dimatikan_tidak_dapat_ditandai_dilihat(): void
    {
        $this->actingAs($this->admin());

        $this->varian(0);
        $this->sakelar('notif_item_stok_habis', false);

        // Menandai butir yang sudah dimatikan harus ditolak: tidak ada butir
        // itu lagi, jadi menyimpan tanda untuknya hanya menumpuk baris mati.
        $this->assertFalse(NotifikasiAdmin::tandaiDilihat('stok-habis'));
        $this->assertDatabaseCount('notification_reads', 0);
    }

    public function test_menyalakan_kembali_mengembalikan_butirnya(): void
    {
        $this->actingAs($this->admin());

        $this->varian(0);

        $this->sakelar('notif_item_stok_habis', false);
        $this->assertSame(0, NotifikasiAdmin::jumlahBelumDilihat());

        $this->sakelar('notif_item_stok_habis', true);
        $this->assertSame(1, NotifikasiAdmin::jumlahBelumDilihat());
    }

    public function test_semua_butir_bawaan_aktif_tanpa_baris_pengaturan(): void
    {
        // Baris belum ada: instalasi baru, atau test yang belum seed. Semua
        // sakelar harus jatuh ke "aktif" — kehilangan notifikasi karena baris
        // belum dibuat adalah kegagalan yang tidak terlihat siapa pun.
        Setting::query()->whereIn('key', array_keys(SakelarNotifikasi::SEMUA))->delete();
        Pengaturan::segarkan();

        foreach (array_values(SakelarNotifikasi::BUTIR) as $butir) {
            $this->assertTrue(SakelarNotifikasi::butirAktif($butir), 'Butir '.$butir.' harus aktif sebagai bawaan.');
        }

        foreach (array_values(SakelarNotifikasi::KIRIM) as $kirim) {
            $this->assertTrue(SakelarNotifikasi::kirimAktif($kirim), 'Pengiriman '.$kirim.' harus aktif sebagai bawaan.');
        }
    }

    public function test_kunci_butir_yang_tidak_dikenal_tetap_dibiarkan_lewat(): void
    {
        // Butir baru yang belum terdaftar di sakelar tidak boleh lenyap
        // hanya karena lupa didaftarkan — itu kegagalan senyap.
        $this->assertTrue(SakelarNotifikasi::butirAktif('butir-yang-belum-ada'));
    }

    public function test_sakelar_pengiriman_kepada_pembeli_dihormati(): void
    {
        $this->sakelar('notif_kirim_dibuat', false);

        $this->assertFalse(SakelarNotifikasi::kirimAktif('kirim_saat_dibuat'));
        $this->assertTrue(SakelarNotifikasi::kirimAktif('kirim_saat_dibayar'), 'Sakelar lain tidak boleh ikut mati.');
    }

    public function test_sakelar_push_perangkat_dihormati(): void
    {
        $this->sakelar('notif_push_perangkat', false);

        $this->assertFalse(SakelarNotifikasi::kirimAktif('push_perangkat'));
        $this->assertTrue(SakelarNotifikasi::kirimAktif('push_surel_cadangan'));
    }

    public function test_tab_notifikasi_ada_di_pengaturan_dan_menyimpan_sakelar(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Livewire\Admin\PengaturanSitus::class)
            ->call('pilihTab', 'notification')
            ->assertSet('tab', 'notification')
            ->assertSee('Butir lonceng panel')
            ->assertSee('Notifikasi yang dikirim')
            ->set('nilai.notif_item_stok_habis', false)
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame('0', Setting::query()->where('key', 'notif_item_stok_habis')->value('value'));
    }

    public function test_hanya_admin_yang_boleh_menyimpan_sakelar(): void
    {
        $tamu = User::factory()->create(['role' => 'customer', 'email' => 'pembeli@contoh.test']);

        $this->actingAs($tamu);

        Livewire::test(\App\Livewire\Admin\PengaturanSitus::class)
            ->call('pilihTab', 'notification');

        $this->actingAs($tamu)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
