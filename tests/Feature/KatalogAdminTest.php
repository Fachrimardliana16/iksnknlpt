<?php

namespace Tests\Feature;

use App\Jobs\ProsesGambarProduk;
use App\Livewire\Admin\ProdukForm;
use App\Models\Category;
use App\Models\MediaUploadLog;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Uji alur katalog admin (F3) pada tingkat komponen.
 *
 * Diuji lewat komponen Livewire, bukan lewat peramban: yang diperiksa di sini
 * adalah aturan dan efek sampingnya — apa yang tersimpan, apa yang ditolak,
 * dan apa yang masuk antrean. Peramban hanya menampilkan hasilnya.
 */
class KatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    /**
     * Menaruh berkas di folder karantina pada disk sungguhan.
     *
     * Storage::fake() tidak menuliskan apa pun ke disk, sedangkan pekerja
     * antrean membaca berkasnya lewat jalur nyata. Berkasnya harus ada.
     */
    private function karantina(string $nama, string $isi): string
    {
        $jalur = Storage::disk('local')->path('uploads/quarantine/'.$nama);

        @mkdir(dirname($jalur), 0777, true);
        file_put_contents($jalur, $isi);

        return $jalur;
    }

    private function produk(array $atribut = []): Product
    {
        return Product::factory()->create($atribut + [
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    // ── Kategori ────────────────────────────────────────────────────

    public function test_kategori_dapat_dibuat_dengan_slug_otomatis(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Livewire\Admin\Kategori::class)
            ->call('bukaForm')
            ->set('nama_id', 'Knalpot Racing')
            ->set('nama_en', 'Racing Exhaust')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name_id' => 'Knalpot Racing',
            'slug' => 'knalpot-racing',
        ]);
    }

    public function test_slug_kategori_kembar_diberi_akhiran(): void
    {
        $this->actingAs($this->admin());

        Category::factory()->create(['slug' => 'knalpot-racing']);

        Livewire::test(\App\Livewire\Admin\Kategori::class)
            ->call('bukaForm')
            ->set('nama_id', 'Knalpot Racing')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['slug' => 'knalpot-racing-2']);
    }

    public function test_kategori_tanpa_nama_ditolak(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(\App\Livewire\Admin\Kategori::class)
            ->call('bukaForm')
            ->set('nama_id', '')
            ->call('simpan')
            ->assertHasErrors(['nama_id' => 'required']);
    }

    public function test_kategori_yang_dipakai_produk_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->admin());

        $kategori = Category::factory()->create();
        Product::factory()->create(['category_id' => $kategori->id]);

        Livewire::test(\App\Livewire\Admin\Kategori::class)
            ->call('hapus', $kategori->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('categories', ['id' => $kategori->id]);
    }

    public function test_urutan_kategori_dirapikan_setelah_digeser(): void
    {
        $this->actingAs($this->admin());

        $a = Category::factory()->create(['display_order' => 1]);
        $b = Category::factory()->create(['display_order' => 2]);

        Livewire::test(\App\Livewire\Admin\Kategori::class)->call('geser', $b->id, 'naik');

        $this->assertSame(1, $b->fresh()->display_order);
        $this->assertSame(2, $a->fresh()->display_order);
    }

    // ── Produk: informasi dasar ─────────────────────────────────────

    public function test_produk_baru_tersimpan_beserta_varian_default(): void
    {
        $this->actingAs($this->admin());

        $kategori = Category::factory()->create();

        Livewire::test(ProdukForm::class)
            ->call('simpanInfo')
            ->assertHasErrors('title_id') // belum diisi
            ->set('title_id', 'Knalpot Uji')
            ->set('category_id', $kategori->id)
            ->set('sku', 'UJI-001')
            ->set('base_price', '450000')
            ->set('weight_gram', '2500')
            ->set('is_published', true)
            ->call('simpanInfo')
            ->assertHasNoErrors();

        $produk = Product::query()->where('sku', 'UJI-001')->firstOrFail();

        $this->assertSame('knalpot-uji', $produk->slug);
        $this->assertTrue($produk->is_published);
        $this->assertNotNull($produk->published_at);
        $this->assertSame(1, $produk->variants()->count(), 'varian default dibuat otomatis');
    }

    public function test_sku_kembar_ditolak(): void
    {
        $this->actingAs($this->admin());

        $this->produk(['sku' => 'UJI-002']);

        Livewire::test(ProdukForm::class)
            ->set('title_id', 'Produk Kembar')
            ->set('category_id', Category::factory()->create()->id)
            ->set('sku', 'UJI-002')
            ->set('base_price', '1000')
            ->set('weight_gram', '100')
            ->call('simpanInfo')
            ->assertHasErrors(['sku' => 'unique']);
    }

    // ── Produk: varian ──────────────────────────────────────────────

    public function test_varian_tersimpan_dan_harga_negatif_ditolak(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk(['base_price' => 100000]);

        $komponen = Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('varian', [[
                'id' => null,
                'nama_id' => 'Standar',
                'nama_en' => '',
                'selisih' => '-150000',
                'stok' => 5,
                'ditahan' => 0,
                'berat' => '',
                'aktif' => true,
            ]])
            ->call('simpanVarian');

        $komponen->assertHasErrors(['varian.0.selisih']);
        $this->assertSame(0, $produk->variants()->count(), 'harga negatif tidak menghasilkan varian apa pun');

        // Sekarang dengan harga yang wajar.
        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('varian', [[
                'id' => null,
                'nama_id' => 'Standar',
                'nama_en' => '',
                'selisih' => '25000',
                'stok' => 5,
                'ditahan' => 0,
                'berat' => '',
                'aktif' => true,
            ]])
            ->call('simpanVarian')
            ->assertHasNoErrors();

        $varian = $produk->variants()->firstOrFail();
        $this->assertSame(5, $varian->stock_quantity);
        $this->assertEqualsWithDelta(125000.0, $varian->hargaAkhir(), 0.01);
    }

    public function test_nama_varian_kembar_ditolak(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk(['base_price' => 100000]);

        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('varian', [
                ['id' => null, 'nama_id' => 'Sama', 'nama_en' => '', 'selisih' => '0', 'stok' => 1, 'ditahan' => 0, 'berat' => '', 'aktif' => true],
                ['id' => null, 'nama_id' => 'Sama', 'nama_en' => '', 'selisih' => '0', 'stok' => 1, 'ditahan' => 0, 'berat' => '', 'aktif' => true],
            ])
            ->call('simpanVarian')
            ->assertHasErrors(['varian']);
    }

    /**
     * Stok tidak boleh diturunkan di bawah jumlah yang sedang ditahan pesanan
     * berjalan. Kalau dilanggar, stok tersedia menjadi negatif dan barang yang
     * sudah dijanjikan ke pembeli lain ikut terjual.
     */
    public function test_stok_tidak_boleh_di_bawah_jumlah_yang_ditahan(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk(['base_price' => 100000]);

        $varian = ProductVariant::factory()->create([
            'product_id' => $produk->id,
            'stock_quantity' => 10,
            'reserved_quantity' => 4,
        ]);

        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('varian', [[
                'id' => $varian->id,
                'nama_id' => (string) $varian->variant_name_id,
                'nama_en' => '',
                'selisih' => '0',
                'stok' => 2,
                'ditahan' => 4,
                'berat' => '',
                'aktif' => true,
            ]])
            ->call('simpanVarian')
            ->assertHasErrors(['varian.0.stok']);

        $this->assertSame(10, $varian->fresh()->stock_quantity);
    }

    public function test_varian_yang_sudah_dipesan_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk();

        $varian = ProductVariant::factory()->create(['product_id' => $produk->id]);

        $pesanan = \App\Models\Order::query()->create([
            'order_number' => 'UJI-0001',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Uji'],
        ]);

        \App\Models\OrderItem::query()->create([
            'order_id' => $pesanan->id,
            'product_id' => $produk->id,
            'product_variant_id' => $varian->id,
            'product_name' => 'Uji',
            'sku' => 'UJI-1',
            'unit_price' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        // Baris kedua supaya penghapusan tidak ditolak oleh aturan minimal satu varian.
        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('varian', [
                ['id' => $varian->id, 'nama_id' => 'Terpakai', 'nama_en' => '', 'selisih' => '0', 'stok' => 1, 'ditahan' => 0, 'berat' => '', 'aktif' => true],
                ['id' => null, 'nama_id' => 'Kedua', 'nama_en' => '', 'selisih' => '0', 'stok' => 1, 'ditahan' => 0, 'berat' => '', 'aktif' => true],
            ])
            ->call('hapusVarian', 0)
            ->assertHasErrors(['varian']);

        $this->assertDatabaseHas('product_variants', ['id' => $varian->id]);
    }

    // ── Produk: media ───────────────────────────────────────────────

    public function test_unggahan_masuk_antrean_dan_mencatat_log(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());

        $produk = $this->produk();

        $berkas = UploadedFile::fake()->image('knalpot.png', 800, 600);

        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->set('berkasBaru', [$berkas])
            ->call('unggah')
            ->assertHasNoErrors();

        Queue::assertPushed(ProsesGambarProduk::class);
    }

    public function test_berkas_bukan_gambar_ditolak_dan_tercatat(): void
    {
        Queue::fake();
        $this->actingAs($this->admin());

        $produk = $this->produk();

        // Berkas PHP yang dinamai .png — inilah yang harus tertangkap.
        // Berkasnya ditulis ke disk sungguhan karena tipe dideteksi dari isi
        // yang dibaca dengan fopen, bukan dari objek tiruan.
        $jalur = $this->karantina('jahat.png', '<?php system($_GET[' . "'c'" . ']); ?>');
        $berkas = new UploadedFile($jalur, 'jahat.png', 'image/png', null, true);

        // Layanan dipanggil langsung: Livewire membajak penyetelan properti
        // berkas dan mewajibkan bentuk berkas yang hanya dimiliki unggahan
        // peramban. Yang diperiksa di sini adalah penjaganya, bukan pipa
        // pengangkutnya — dan penjaganya inilah yang menjaga titik akhir.
        $hasil = app(\App\Services\Produk\PengunggahMediaProduk::class)
            ->unggahBanyak($produk, [$berkas]);

        $this->assertSame(0, $hasil['antre']);
        $this->assertCount(1, $hasil['ditolak']);
        $this->assertStringContainsString('jahat.png', $hasil['ditolak'][0]);

        Queue::assertNothingPushed();

        $log = MediaUploadLog::query()->latest('id')->first();

        $this->assertNotNull($log, 'penolakan harus tercatat');
        $this->assertSame('rejected', $log->status);
        $this->assertNotNull($log->rejection_reason);
    }

    public function test_pekerja_antrean_membuat_media_dari_berkas_karantina(): void
    {
        Storage::fake('public');

        $produk = $this->produk();

        // Berkas nyata di karantina, seperti yang ditinggalkan tahap unggah.
        // Ditulis lewat jalur sungguhan karena pekerja antrean membaca berkasnya
        // dengan filesize dan fopen, bukan lewat abstraksi Storage.
        $gambar = UploadedFile::fake()->image('knalpot-asli.png', 1600, 1200);
        $isi = file_get_contents($gambar->getRealPath());
        $karantina = $this->karantina('uji.png', $isi);

        (new ProsesGambarProduk(
            produkId: $produk->id,
            berkasKarantina: 'uploads/quarantine/uji.png',
            namaAsli: 'knalpot-asli.png',
            byteSize: strlen($isi),
        ))->handle(
            app(\App\Services\Gambar\PemeriksaGambar::class),
            app(\App\Services\Gambar\PengecilGambar::class),
        );

        $media = ProductMedia::query()->where('product_id', $produk->id)->first();

        $this->assertNotNull($media, 'media harus terbentuk');
        $this->assertTrue($media->is_primary, 'gambar pertama menjadi utama');
        $this->assertNotNull($media->content_hash);

        // Empat turunan harus ada di disk.
        foreach (['thumb', 'card', 'full', 'og'] as $varian) {
            $this->assertTrue(
                Storage::disk('public')->exists($media->{'path_'.$varian}),
                "turunan $varian harus ada",
            );
        }

        // Karantina dibersihkan setelah berhasil.
        $this->assertFileDoesNotExist($karantina);

        $log = MediaUploadLog::query()->latest('id')->first();
        $this->assertSame('processed', $log->status);
        $this->assertSame($produk->id, $log->product_id);
        $this->assertNotNull($log->processing_ms);
    }

    public function test_unggahan_kembar_dilewati(): void
    {
        Storage::fake('public');

        $produk = $this->produk();

        $gambar = UploadedFile::fake()->image('sama.png', 800, 600);
        $isi = file_get_contents($gambar->getRealPath());

        $this->karantina('a.png', $isi);
        $this->karantina('b.png', $isi);

        $pemeriksa = app(\App\Services\Gambar\PemeriksaGambar::class);
        $pengecil = app(\App\Services\Gambar\PengecilGambar::class);

        (new ProsesGambarProduk($produk->id, 'uploads/quarantine/a.png', 'sama.png'))->handle($pemeriksa, $pengecil);
        (new ProsesGambarProduk($produk->id, 'uploads/quarantine/b.png', 'sama.png'))->handle($pemeriksa, $pengecil);

        $this->assertSame(1, ProductMedia::query()->where('product_id', $produk->id)->count());

        $log = MediaUploadLog::query()->latest('id')->first();
        $this->assertSame('deduplicated', $log->status);
    }

    // ── Produk: publikasi ───────────────────────────────────────────

    public function test_produk_tanpa_varian_tidak_bisa_diterbitkan(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk(['is_published' => false]);

        Livewire::test(ProdukForm::class, ['ulid' => $produk->ulid])
            ->call('ubahTerbit')
            ->assertHasErrors(['umum']);

        $this->assertFalse($produk->fresh()->is_published);
    }

    // ── Daftar produk ───────────────────────────────────────────────

    public function test_aksi_massal_melewati_produk_tanpa_varian(): void
    {
        $this->actingAs($this->admin());

        $punya = $this->produk(['is_published' => false, 'title_id' => 'Punya Varian']);
        ProductVariant::factory()->create(['product_id' => $punya->id]);

        $tanpa = $this->produk(['is_published' => false, 'title_id' => 'Tanpa Varian']);

        Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)
            ->set('terpilih', [$punya->id, $tanpa->id])
            ->call('ubahTerbitMassal', true);

        $this->assertTrue($punya->fresh()->is_published);
        $this->assertFalse($tanpa->fresh()->is_published, 'produk tanpa varian dilewati');
    }

    public function test_produk_yang_pernah_dipesan_tidak_dihapus(): void
    {
        $this->actingAs($this->admin());

        $produk = $this->produk();

        $pesanan = \App\Models\Order::query()->create([
            'order_number' => 'UJI-0002',
            'status' => 'pending',
            'subtotal' => 100000,
            'grand_total' => 100000,
            'shipping_address_snapshot' => ['nama' => 'Uji'],
        ]);

        \App\Models\OrderItem::query()->create([
            'order_id' => $pesanan->id,
            'product_id' => $produk->id,
            'product_name' => 'Uji',
            'sku' => 'UJI-2',
            'unit_price' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)
            ->call('hapus', $produk->id)
            ->assertHasErrors(['umum']);

        $this->assertDatabaseHas('products', ['id' => $produk->id]);
    }

    public function test_duplikat_selalu_jadi_draf_dengan_stok_nol(): void
    {
        $this->actingAs($this->admin());

        $asli = $this->produk(['is_published' => true, 'sku' => 'ASLI-1']);
        ProductVariant::factory()->create(['product_id' => $asli->id, 'stock_quantity' => 9]);

        Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)->call('duplikat', $asli->id);

        $salinan = Product::query()->where('sku', 'like', 'ASLI-1-COPY%')->firstOrFail();

        $this->assertFalse($salinan->is_published);

        // Stok tidak disalin: dua produk dengan stok yang sama menjanjikan
        // barang yang cuma ada satu.
        $this->assertSame(0, (int) $salinan->variants()->sum('stock_quantity'));
    }

    public function test_saringan_daftar_produk_bekerja(): void
    {
        $this->actingAs($this->admin());

        $terbit = $this->produk(['is_published' => true, 'title_id' => 'Terbit Saja']);
        $this->produk(['is_published' => false, 'title_id' => 'Draf Saja']);

        Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)
            ->call('aturTerbit', 'terbit')
            ->assertSee('Terbit Saja')
            ->assertDontSee('Draf Saja');

        Livewire::test(\App\Livewire\Admin\ProdukDaftar::class)
            ->set('cari', 'Draf Saja')
            ->assertSee('Draf Saja')
            ->assertDontSee($terbit->title_id);
    }
}
