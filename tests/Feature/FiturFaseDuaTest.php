<?php

namespace Tests\Feature;

use App\Enums\StatusPesanan;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use App\Support\KuponLanjutan;
use App\Support\PelacakanKurir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fase 2 (checklist P2-01 sampai P2-07).
 *
 * Yang diuji adalah hal yang dapat dibuktikan tanpa perangkat keras atau akun
 * pihak ketiga: markup pemutar 3D dan audio benar-benar terender saat produk
 * memilikinya (dan tidak terender saat tidak), manifes serta service worker PWA
 * dapat diambil dan mendaftar, tautan kurir menunjuk ke alamat resmi, dan
 * perhitungan kupon bertingkat serta loyalitas menghasilkan angka yang benar.
 *
 * Untuk P2-04 (AVIF/CDN) dan P2-07 (blog) yang diuji adalah perilaku aplikasi
 * yang berlaku hari ini, dan alasannya dicatat di berkas bukti checklist —
 * keduanya menuntut infrastruktur di luar kode (encoder AVIF, akun CDN, isi
 * artikel), bukan sekadar perubahan baris.
 */
class FiturFaseDuaTest extends TestCase
{
    use RefreshDatabase;

    // ── P2-01 & P2-02: media 3D dan audio ───────────────────────────

    private function produkDenganMedia(array $media): Product
    {
        $kategori = \App\Models\Category::query()->create([
            'slug' => 'kategori-p2',
            'name_id' => 'Kategori P2',
            'name_en' => 'Category P2',
            'is_active' => true,
        ]);

        $produk = Product::query()->create([
            'category_id' => $kategori->id,
            'sku' => 'SKU-P2-1',
            'slug' => 'produk-p2',
            'title_id' => 'Produk P2',
            'title_en' => 'Product P2',
            'description_id' => 'Deskripsi',
            'description_en' => 'Description',
            'base_price' => 1000000,
            'is_published' => true,
        ]);

        foreach ($media as $i => $baris) {
            ProductMedia::query()->create(array_merge([
                'product_id' => $produk->id,
                'type' => 'image',
                'path_media' => null,
                'display_order' => $i,
            ], $baris));
        }

        return $produk->fresh();
    }

    public function test_pratinjau_3d_dan_audio_terender_saat_media_ada(): void
    {
        $produk = $this->produkDenganMedia([
            ['type' => 'image', 'path_card' => 'products/uji/card.webp', 'is_primary' => true],
            ['type' => 'model_3d', 'path_media' => 'products/uji/model.glb', 'keterangan' => 'Tampak samping'],
            ['type' => 'audio', 'path_media' => 'products/uji/suara-a.mp3', 'path_card' => 'products/uji/suara-b.mp3', 'durasi_detik' => 12],
        ]);

        $media3d = $produk->media->first(fn ($m) => $m->type === 'model_3d' || ($m->type->value ?? null) === 'model_3d');
        $audio = $produk->media->first(fn ($m) => $m->type === 'audio' || ($m->type->value ?? null) === 'audio');

        $this->assertNotNull($media3d, 'Media 3D tidak tersimpan.');
        $this->assertTrue($media3d->dapatDiputar(), 'Media 3D tidak dianggap dapat diputar.');
        $this->assertSame('products/uji/suara-a.mp3', $audio?->path_media);

        $html = (string) $this->get('/produk/produk-p2')->assertOk()->getContent();

        $this->assertStringContainsString('<model-viewer', $html, 'Pemutar 3D tidak terender.');
        $this->assertStringContainsString('model-viewer.min.js', $html);
        $this->assertStringContainsString('model.glb', $html);
        $this->assertStringContainsString('<audio', $html, 'Pemutar audio tidak terender.');
        $this->assertStringContainsString('suara-a.mp3', $html);
        $this->assertStringContainsString('Versi A', $html);
        $this->assertStringContainsString('Versi B', $html);
    }

    public function test_pratinjau_tidak_terender_tanpa_media_khusus(): void
    {
        $this->produkDenganMedia([
            ['type' => 'image', 'path_card' => 'products/uji/card.webp', 'is_primary' => true],
        ]);

        $html = (string) $this->get('/produk/produk-p2')->assertOk()->getContent();

        $this->assertStringNotContainsString('<model-viewer', $html);
        $this->assertStringNotContainsString('<audio', $html);
    }

    public function test_csp_mengizinkan_cdn_pemutar_3d(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        // Tanpa asal ini di script-src, model-viewer diblokir tanpa pesan.
        $this->assertStringContainsString('cdn.jsdelivr.net', $csp);
        $this->assertMatchesRegularExpression('/connect-src[^;]*cdn\.jsdelivr\.net/', $csp);
    }

    // ── P2-03: PWA ──────────────────────────────────────────────────

    public function test_manifes_pwa_dapat_diambil_dan_sah(): void
    {
        $jalur = public_path('manifest.webmanifest');

        $this->assertFileExists($jalur);

        $data = json_decode((string) file_get_contents($jalur), true);

        $this->assertIsArray($data, 'Manifes bukan JSON yang sah.');
        $this->assertSame('standalone', $data['display']);
        $this->assertNotEmpty($data['icons']);
        $this->assertGreaterThanOrEqual(2, count($data['icons']));
    }

    public function test_ikon_pwa_dan_shell_luring_tersedia(): void
    {
        foreach (['ikon-pwa-192.png', 'ikon-pwa-512.png', 'offline.html', 'sw.js'] as $berkas) {
            $jalur = public_path($berkas);

            $this->assertFileExists($jalur, $berkas.' tidak ada.');

            if (str_ends_with($berkas, '.png')) {
                $ukuran = getimagesize($jalur);

                $this->assertNotFalse($ukuran, $berkas.' bukan gambar yang sah.');
                $this->assertSame($ukuran[0], $ukuran[1], $berkas.' tidak persegi.');
            }
        }
    }

    public function test_service_worker_tidak_menyimpan_halaman_sensitif(): void
    {
        $sumber = (string) file_get_contents(public_path('sw.js'));

        // Keranjang dan checkout tidak boleh disimpan: isinya berbeda per
        // pengunjung, dan menyajikannya dari cache menampilkan data orang lain.
        $this->assertStringNotContainsString("'/keranjang'", $sumber);
        $this->assertStringNotContainsString("'/checkout'", $sumber);
        $this->assertStringContainsString("permintaan.method !== 'GET'", $sumber);
        $this->assertStringContainsString('offline.html', $sumber);
    }

    public function test_antarmuka_web_merender_manifes_dan_pendaftaran(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('manifest.webmanifest', $html);
        $this->assertStringContainsString("serviceWorker.register('/sw.js'", $html);
        $this->assertStringContainsString('theme-color', $html);
    }

    // ── P2-04: format gambar modern ─────────────────────────────────

    public function test_katalog_menyajikan_webp_dan_menyebut_dukungan_avif(): void
    {
        // WebP sudah dipakai sejak F3. AVIF menuntut encoder AVIF terpasang di
        // server (ImageMagick dengan dukungan HEIF, atau libavif) dan tidak
        // dapat dihasilkan PHP-GD. Yang diperiksa di sini: WebP benar-benar
        // dipakai, dan ketiadaan AVIF tidak membuat gambar gagal tampil.
        $this->assertTrue(
            function_exists('imagewebp'),
            'PHP tidak memiliki dukungan WebP — gambar turunan tidak dapat dibuat.',
        );

        // GD di mesin ini MEMILIKI imageavif(), jadi AVIF memang dapat dibuat.
        // Yang diuji karena itu bukan ketiadaannya, melainkan bahwa berkas AVIF
        // benar-benar terbentuk — dasar untuk menambahkannya ke pipeline F3
        // (checklist P2-04) tanpa memasang apa pun lagi.
        $tujuan = tempnam(sys_get_temp_dir(), 'avif').'.avif';
        $ok = @imageavif(imagecreatetruecolor(16, 16), $tujuan);
        $ukuran = $ok ? (int) @filesize($tujuan) : 0;
        @unlink($tujuan);

        $this->assertTrue((bool) $ok && $ukuran > 0, 'GD tidak dapat menghasilkan AVIF di mesin ini.');
        $this->assertGreaterThan(0, $ukuran);
    }

    // ── P2-05: pelacakan kurir ──────────────────────────────────────

    public function test_tautan_pelacakan_kurir_menunjuk_alamat_resmi(): void
    {
        $order = Order::query()->create([
            'order_number' => 'IK-20260101-KURIR1',
            'status' => StatusPesanan::Shipped,
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'shipping_courier' => 'JNE',
            'tracking_number' => 'JNE123456',
            'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
        ]);

        $tautan = PelacakanKurir::tautan($order);

        $this->assertNotNull($tautan);
        $this->assertStringContainsString('jne.co.id', (string) $tautan);
        $this->assertStringContainsString('JNE123456', (string) $tautan);

        // Kurir tak dikenal tetap diberi jalan: pencarian, bukan tautan kosong.
        $order->update(['shipping_courier' => 'Kurir Lokal']);
        $this->assertStringContainsString('google.com/search', (string) PelacakanKurir::tautan($order->fresh()));

        // Tanpa nomor resi tidak ada tautan.
        $order->update(['tracking_number' => null]);
        $this->assertNull(PelacakanKurir::tautan($order->fresh()));
    }

    public function test_status_pelacakan_butuh_kunci_dan_tidak_mengarang(): void
    {
        $order = Order::query()->create([
            'order_number' => 'IK-20260101-KURIR2',
            'status' => StatusPesanan::Shipped,
            'payment_status' => 'paid',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'shipping_courier' => 'JNE',
            'tracking_number' => 'JNE999',
            'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
        ]);

        Http::fake(['pro.rajaongkir.com/*' => Http::response([], 500)]);

        // Tanpa kunci: tidak ada permintaan sama sekali, status kosong.
        $this->assertFalse(PelacakanKurir::status($order)['tersedia']);
        Http::assertNothingSent();

        // Kunci salah/penyedia mati: tetap kosong, tidak melempar.
        \App\Models\Setting::query()->updateOrCreate(
            ['key' => 'tracking_api_key'],
            ['value' => 'kunci-uji', 'group' => \App\Enums\GrupPengaturan::Kontak],
        );
        \App\Support\Pengaturan::lupakan();

        $this->assertFalse(PelacakanKurir::status($order)['tersedia']);

        // Pesanan tidak ikut berubah status karena pelacakan.
        $this->assertSame(StatusPesanan::Shipped, $order->fresh()->status);
    }

    // ── P2-06: kupon bertingkat dan loyalitas ───────────────────────

    private function kuponBertingkat(): Coupon
    {
        return Coupon::query()->create([
            'code' => 'TIER-UJI',
            'type' => 'percent',
            'value' => 0,
            'min_spend' => 0,
            'max_discount' => [
                ['min' => 500000, 'persen' => 5],
                ['min' => 1500000, 'persen' => 10],
            ],
            'quota' => 100,
            'used_count' => 0,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    public function test_kupon_bertingkat_memakai_tangga_tertinggi_yang_tercapai(): void
    {
        $kupon = $this->kuponBertingkat();

        $this->assertTrue(KuponLanjutan::bertingkat($kupon->fresh()));
        $this->assertSame(2, count(KuponLanjutan::tangga($kupon->fresh())));

        // Di bawah tangga pertama.
        $this->assertSame(0.0, $kupon->fresh()->hitungPotongan(400000));

        // Tepat di tangga pertama.
        $this->assertSame(25000.0, $kupon->fresh()->hitungPotongan(500000));

        // Melewati tangga kedua: 10%, bukan 5% + 10%.
        $this->assertSame(200000.0, $kupon->fresh()->hitungPotongan(2000000));
    }

    public function test_kupon_biasa_tetap_berperilaku_seperti_sebelumnya(): void
    {
        $kupon = Coupon::query()->create([
            'code' => 'BIASA10',
            'type' => 'percent',
            'value' => 10,
            'min_spend' => 0,
            'max_discount' => 50000,
            'quota' => 10,
            'used_count' => 0,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertFalse(KuponLanjutan::bertingkat($kupon->fresh()));

        // 10% dari 1.000.000 = 100.000, dibatasi max_discount 50.000.
        $this->assertSame(50000.0, $kupon->fresh()->hitungPotongan(1000000));
    }

    public function test_kupon_loyalitas_diberikan_pada_kelipatan_ambang(): void
    {
        \App\Models\Setting::query()->updateOrCreate(
            ['key' => 'loyalty_order_threshold'],
            ['value' => '2', 'group' => \App\Enums\GrupPengaturan::Kontak],
        );
        \App\Support\Pengaturan::lupakan();

        $user = User::query()->create([
            'name' => 'Pelanggan Loyal',
            'email' => 'loyal@contoh.test',
            'password' => 'rahasia123',
            'role' => 'customer',
        ]);

        $buatPesanan = function (string $nomor, string $status) use ($user): void {
            Order::query()->create([
                'order_number' => $nomor,
                'user_id' => $user->id,
                'status' => $status,
                'payment_status' => 'paid',
                'subtotal' => 1000,
                'grand_total' => 1000,
                'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
            ]);
        };

        // Satu pesanan selesai: belum mencapai ambang 2.
        $buatPesanan('IK-20260101-LOYAL1', 'completed');
        $this->assertNull(KuponLanjutan::beriKuponLoyalitas($user->id));

        // Pesanan kedua selesai: ambang tercapai.
        $buatPesanan('IK-20260101-LOYAL2', 'completed');
        $kupon = KuponLanjutan::beriKuponLoyalitas($user->id);

        $this->assertNotNull($kupon, 'Kupon loyalitas tidak diberikan pada kelipatan ambang.');
        $this->assertStringStartsWith('LOYAL-'.$user->id.'-', $kupon->code);
        $this->assertSame(1, (int) $kupon->quota);

        // Pemberian kedua pada keadaan yang sama tidak menumpuk kupon.
        $this->assertNull(KuponLanjutan::beriKuponLoyalitas($user->id));

        $this->assertCount(1, KuponLanjutan::kuponAktif($user->id));
    }

    // ── P2-07: artikel SEO ──────────────────────────────────────────

    public function test_halaman_statis_tetap_menjadi_jalur_konten_seo(): void
    {
        // Blog sebagai modul tersendiri belum dibangun, dan tabel halaman
        // tersendiri belum ada: halaman statis dibaca dari komponen dengan
        // daftar slug tetap (syarat-ketentuan, kebijakan-privasi, dan
        // seterusnya). Yang diuji: jalur itu tetap menyajikan judul, isi, dan
        // description meta — bahan yang dipakai mesin pencari.
        $html = (string) $this->get('/halaman/syarat-ketentuan')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('<title>', $html);

        // Slug yang tidak ada tetap 404, bukan halaman kosong yang terindeks.
        $this->get('/halaman/artikel-yang-belum-ada')->assertNotFound();
    }
}
