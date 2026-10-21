<?php

namespace Tests\Feature;

use App\Http\Middleware\CacheHalaman;
use App\Models\Setting;
use App\Models\User;
use App\Support\CacheKatalog;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cache halaman publik (optimasi waktu muat).
 *
 * Yang diuji bukan "apakah cache menyimpan" — itu mudah — melainkan batasnya,
 * karena kesalahan di sini jauh lebih mahal daripada halaman lambat:
 *
 *   - keranjang yang tersimpan akan menampilkan isi keranjang orang lain;
 *   - halaman panel yang tersimpan dapat dibaca tanpa masuk;
 *   - halaman yang memuat token CSRF, bila tersimpan, menyajikan token MILIK
 *     SESI LAIN. Pengunjung berikutnya menekan tombol dan mendapat 419
 *     "CSRF token mismatch" — seluruh tombol Livewire mati. Itu pernah terjadi
 *     di proyek ini: tombol + dan − pada pemilih jumlah tidak berfungsi.
 */
class CacheHalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * Mekanisme simpan-sajikan.
     *
     * Diuji lewat middleware langsung, bukan lewat rute: SETIAP halaman di
     * situs ini memuat komponen Livewire (navbar keranjang, lonceng), sehingga
     * semuanya membawa token CSRF dan sengaja dilewati cache. Respons buatan
     * di bawah meniru halaman statis murni.
     */
    public function test_halaman_tanpa_token_disimpan_lalu_disajikan_dari_cache(): void
    {
        $m = new CacheHalaman;
        $permintaan = Request::create('/uji-cache', 'GET');

        $buat = fn () => $m->handle(
            $permintaan,
            fn () => response('<html><body>halaman statis</body></html>', 200, ['Content-Type' => 'text/html']),
        );

        $this->assertSame('miss', $buat()->headers->get('X-Cache-Halaman'));

        $kedua = $buat();
        $this->assertSame('hit', $kedua->headers->get('X-Cache-Halaman'));
        $this->assertStringContainsString('halaman statis', (string) $kedua->getContent());
    }

    public function test_halaman_bertoken_selalu_dilewati(): void
    {
        // Halaman ber-komponen Livewire membawa token CSRF dan snapshot sesi.
        // Menyimpannya berarti menyajikan token sesi lain ke pengunjung
        // berikutnya; setiap tombol mereka gagal 419.
        $this->get('/produk')->assertOk();

        $this->assertSame(
            'lewati-token',
            $this->get('/produk')->headers->get('X-Cache-Halaman'),
            'Halaman bertoken disimpan di cache: token sesi bocor ke pengunjung lain.',
        );
    }

    public function test_dua_sesi_menerima_token_csrf_berbeda(): void
    {
        $token1 = $this->tokenDari($this->get('/produk'));
        $this->flushSession();
        $token2 = $this->tokenDari($this->get('/produk'));

        $this->assertNotEmpty($token1, 'Halaman tanpa token CSRF: Livewire tidak akan berfungsi.');
        $this->assertNotSame($token1, $token2, 'Dua sesi menerima token CSRF yang sama.');
    }

    private function tokenDari($respons): ?string
    {
        if (preg_match('/data-csrf="([^"]+)"/', (string) $respons->getContent(), $m)) {
            return $m[1];
        }

        return null;
    }

    public function test_perubahan_versi_katalog_membatalkan_cache(): void
    {
        $m = new CacheHalaman;
        $permintaan = Request::create('/uji-cache', 'GET');
        $buat = fn () => $m->handle($permintaan, fn () => response('<html>isi</html>', 200));

        $buat();
        $this->assertSame('hit', $buat()->headers->get('X-Cache-Halaman'));

        // Panel admin menaikkan versi katalog setiap kali ada perubahan. Diuji
        // lewat mekanismenya, bukan nama kuncinya: kunci boleh berubah,
        // perilaku "versi naik = cache batal" tidak boleh.
        CacheKatalog::tandaiBerubah();

        $this->assertSame('miss', $buat()->headers->get('X-Cache-Halaman'));
    }

    public function test_keranjang_tidak_pernah_disimpan(): void
    {
        $this->get('/keranjang')->assertOk();

        $kedua = $this->get('/keranjang')->assertOk();

        $this->assertNull($kedua->headers->get('X-Cache-Halaman'), 'Halaman keranjang masuk cache.');
    }

    public function test_checkout_dan_lacak_tidak_disimpan(): void
    {
        foreach (['/checkout', '/lacak', '/pesanan/sukses/01abc'] as $jalur) {
            $this->get($jalur);

            $this->assertNull(
                $this->get($jalur)->headers->get('X-Cache-Halaman'),
                $jalur.' masuk cache.',
            );
        }
    }

    public function test_panel_admin_tidak_disimpan_dan_tetap_menolak_tamu(): void
    {
        $tamu = $this->get('/admin/pesanan');

        $tamu->assertRedirect('/admin/login');
        $this->assertNull($tamu->headers->get('X-Cache-Halaman'));
    }

    public function test_pengunjung_yang_sudah_masuk_tidak_mendapat_halaman_tersimpan(): void
    {
        $user = User::query()->create([
            'name' => 'Pelanggan',
            'email' => 'cache@contoh.test',
            'password' => 'rahasia123',
            'role' => 'customer',
        ]);

        $this->actingAs($user);

        $this->get('/produk')->assertOk();

        $this->assertNull(
            $this->get('/produk')->headers->get('X-Cache-Halaman'),
            'Pengunjung yang sudah masuk mendapat halaman dari cache bersama.',
        );
    }

    public function test_nonce_csp_ikut_tersimpan_sehingga_skrip_tidak_diblokir(): void
    {
        $m = new CacheHalaman;
        $permintaan = Request::create('/uji-cache', 'GET');

        // Nonce unik per permintaan; yang disimpan harus ikut.
        $nomor = 0;

        $buat = function () use ($m, $permintaan, &$nomor) {
            $nonce = 'nonce-'.(++$nomor);

            return $m->handle($permintaan, fn () => response(
                '<html nonce="'.$nonce.'">isi</html>',
                200,
                ['Content-Security-Policy' => "default-src 'self'; script-src 'nonce-".$nonce."'"],
            ));
        };

        $pertama = $buat();
        $kedua = $buat();

        preg_match("/'nonce-([^']+)'/", (string) $pertama->headers->get('Content-Security-Policy'), $m1);
        preg_match("/'nonce-([^']+)'/", (string) $kedua->headers->get('Content-Security-Policy'), $m2);

        $this->assertNotEmpty($m1[1] ?? '', 'Jawaban pertama tanpa nonce.');
        $this->assertSame(
            $m1[1] ?? null,
            $m2[1] ?? null,
            'Nonce pada jawaban dari cache berbeda dengan nonce di dalam HTML-nya.',
        );

        // Nonce di header harus benar-benar muncul di HTML yang disajikan.
        $this->assertStringContainsString(
            'nonce="'.($m2[1] ?? '').'"',
            (string) $kedua->getContent(),
            'HTML dari cache memuat nonce yang berbeda dari header CSP.',
        );
    }

    public function test_cache_dapat_dimatikan_lewat_pengaturan(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'cache_halaman'],
            ['value' => '0', 'group_name' => 'umum', 'type' => 'boolean'],
        );

        Pengaturan::lupakan();

        $this->get('/produk')->assertOk();

        $this->assertNull($this->get('/produk')->headers->get('X-Cache-Halaman'));
    }
}
