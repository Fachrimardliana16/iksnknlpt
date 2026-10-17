<?php

namespace Tests\Feature;

use App\Services\Gambar\PemeriksaGambar;
use App\Services\Gambar\PengecilGambar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F3-06 sampai F3-09: berkas palsu ditolak, metadata dibuang, dan turunan
 * ukuran dihasilkan.
 */
class UnggahanGambarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function gambar(int $lebar = 800, int $tinggi = 600, string $nama = 'uji.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, $lebar, $tinggi);
    }

    // ── Pemeriksa ───────────────────────────────────────────────────

    public function test_gambar_sah_diterima(): void
    {
        $hasil = app(PemeriksaGambar::class)->periksa($this->gambar());

        $this->assertSame('', $hasil['alasan']);
        $this->assertSame('image/jpeg', $hasil['mime']);
        $this->assertNotNull($hasil['hash']);
    }

    public function test_berkas_php_berkedok_jpg_ditolak(): void
    {
        // Isi berkas adalah kode PHP, tetapi ekstensinya .jpg. Pemeriksaan
        // ekstensi saja akan meloloskannya.
        //
        // Berkas ditulis ke jalur nyata, bukan memakai createWithContent().
        // Helper itu memakai tmpfile(), yang di Windows hanya dapat dibaca lewat
        // handle pemiliknya, sehingga finfo->file gagal dan yang teruji justru
        // keterbatasan sistem berkas, bukan pemeriksa ini.
        //
        // Muatannya menghindari shell_exec dan system: Windows Defender
        // mengunci berkas sementara berisi signature webshell sehingga berkasnya
        // tidak dapat dibuka sama sekali, dan yang teruji jadi antivirus, bukan
        // pemeriksa ini. Tag PHP yang sederhana sudah cukup membuktikan bahwa
        // deteksi memakai isi berkas.
        $jalur = tempnam(sys_get_temp_dir(), 'iksan_uji_');
        file_put_contents($jalur, "<?php echo \$_GET['c']; ?>");

        $palsu = new UploadedFile(
            $jalur,
            'jahat.jpg',
            'image/jpeg',
            null,
            true,
        );

        $hasil = app(PemeriksaGambar::class)->periksa($palsu);

        $this->assertNotSame('', $hasil['alasan'], 'Berkas PHP berkedok .jpg harus ditolak.');
        $this->assertStringContainsString('tidak diizinkan', $hasil['alasan']);
        $this->assertStringContainsString('php', strtolower($hasil['mime'] ?? ''));

        @unlink($jalur);
    }

    public function test_svg_ditolak(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'gambar.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $hasil = app(PemeriksaGambar::class)->periksa($svg);

        $this->assertNotSame('', $hasil['alasan']);
    }

    public function test_gambar_terlalu_kecil_ditolak(): void
    {
        $hasil = app(PemeriksaGambar::class)->periksa($this->gambar(50, 40));

        $this->assertStringContainsString('di bawah minimum', $hasil['alasan']);
    }

    public function test_rasio_ekstrem_ditolak(): void
    {
        // 1400 x 50 melewati batas rasio 20:1.
        $hasil = app(PemeriksaGambar::class)->periksa($this->gambar(1400, 50, 'panjang.jpg'));

        $this->assertNotSame('', $hasil['alasan']);
        $this->assertMatchesRegularExpression('/rasio|dimensi|minimum/i', $hasil['alasan']);
    }

    public function test_kuota_berkas_per_produk_ditegakkan(): void
    {
        $batas = (int) config('iksan.uploads.max_files_per_product');

        $hasil = app(PemeriksaGambar::class)->periksa($this->gambar(), $batas, 0);

        $this->assertStringContainsString('batas', $hasil['alasan']);
    }

    public function test_kuota_total_ukuran_produk_ditegakkan(): void
    {
        $batasMb = (int) config('iksan.uploads.max_total_mb_per_product');
        $totalByte = $batasMb * 1024 * 1024;

        $hasil = app(PemeriksaGambar::class)->periksa($this->gambar(), 0, $totalByte);

        $this->assertStringContainsString('Total media', $hasil['alasan']);
    }

    // ── Pengecil dan pembersih metadata ─────────────────────────────

    public function test_turunan_empat_ukuran_dihasilkan(): void
    {
        $berkas = $this->gambar(1800, 1400);

        $hasil = app(PengecilGambar::class)->proses(
            $berkas->getRealPath(),
            'products/uji',
            'gambar-1',
        );

        $this->assertSame(['thumb', 'card', 'full', 'og'], array_keys($hasil['paths']));

        foreach ($hasil['paths'] as $nama => $jalur) {
            Storage::disk('public')->assertExists($jalur);

            // Setiap turunan harus berupa WebP yang sah, bukan salinan berkas asal.
            $info = getimagesizefromstring(Storage::disk('public')->get($jalur));
            $this->assertNotFalse($info, "Turunan {$nama} bukan gambar sah.");
            $this->assertSame('image/webp', $info['mime'], "Turunan {$nama} bukan WebP.");
        }

        $og = getimagesizefromstring(Storage::disk('public')->get($hasil['paths']['og']));
        $this->assertSame(1200, $og[0]);
        $this->assertSame(630, $og[1], 'Turunan og harus dipotong ke 1200x630.');

        $thumb = getimagesizefromstring(Storage::disk('public')->get($hasil['paths']['thumb']));
        $this->assertLessThanOrEqual(320, $thumb[0]);
        $this->assertLessThanOrEqual(240, $thumb[1]);
    }

    public function test_gambar_besar_dikecilkan_ke_lebar_maksimum(): void
    {
        $berkas = $this->gambar(3000, 2000);

        $hasil = app(PengecilGambar::class)->proses($berkas->getRealPath(), 'products/uji', 'besar');

        $this->assertSame(1600, $hasil['width'], 'Lebar maksimum harus 1600 px.');
        $this->assertSame(1067, $hasil['height']);
    }

    public function test_gambar_kecil_tidak_diperbesar(): void
    {
        $berkas = $this->gambar(400, 300);

        $hasil = app(PengecilGambar::class)->proses($berkas->getRealPath(), 'products/uji', 'kecil');

        $this->assertSame(400, $hasil['width'], 'Gambar kecil tidak boleh diperbesar.');
        $this->assertSame(300, $hasil['height']);
    }

    public function test_metadata_exif_dibuang_dari_hasil(): void
    {
        $berkas = $this->gambar(800, 600);

        $hasil = app(PengecilGambar::class)->proses($berkas->getRealPath(), 'products/uji', 'exif');
        $isi = Storage::disk('public')->get($hasil['paths']['full']);

        // WebP tidak menyimpan EXIF sama sekali, dan berkas didekode ulang
        // sebelum dikodekan. Tidak boleh ada penanda EXIF maupun GPS.
        $this->assertStringNotContainsString('Exif', $isi);
        $this->assertStringNotContainsString('GPS', $isi);

        $pathSementara = tempnam(sys_get_temp_dir(), 'webp');
        file_put_contents($pathSementara, $isi);

        $exif = @exif_read_data($pathSementara);
        $this->assertEmpty($exif['GPSLatitude'] ?? null, 'Koordinat GPS tidak boleh tersisa.');
        $this->assertEmpty($exif['Make'] ?? null, 'Informasi kamera tidak boleh tersisa.');

        @unlink($pathSementara);
    }

    public function test_hash_konten_stabil_untuk_berkas_sama(): void
    {
        $berkas = $this->gambar(800, 600);

        $a = app(PengecilGambar::class)->proses($berkas->getRealPath(), 'products/uji', 'hash-a');
        $b = app(PengecilGambar::class)->proses($berkas->getRealPath(), 'products/uji', 'hash-b');

        $this->assertSame($a['hash'], $b['hash'], 'Isi yang sama harus menghasilkan hash yang sama.');
    }
}
