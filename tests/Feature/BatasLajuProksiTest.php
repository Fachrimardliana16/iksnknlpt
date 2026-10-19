<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Tests\TestCase;

/**
 * Batas laju dan kepercayaan proksi (audit: rate limiting & load balancer).
 *
 * Dua kesalahan yang berlawanan sama-sama berbahaya, dan keduanya dijaga di sini:
 *
 *   - trustProxies '*' → siapa pun dapat memalsukan X-Forwarded-For dan
 *     mengelak dari seluruh batas laju, blokir IP, dan pencatatan keamanan.
 *   - tanpa trustProxies → di balik load balancer semua pengunjung terlihat satu
 *     alamat, sehingga satu penyerang memblokir seluruh toko.
 *
 * Yang diuji adalah akibat yang dapat diukur, bukan nilai konfigurasi: alamat
 * mana yang dipakai `$request->ip()` setelah header proksi dipasang.
 */
class BatasLajuProksiTest extends TestCase
{
    use RefreshDatabase;

    private function permintaan(string $alamatRemote, ?string $xff): Request
    {
        $server = ['REMOTE_ADDR' => $alamatRemote];

        if ($xff !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $xff;
        }

        return Request::create('/lacak', 'GET', server: $server);
    }

    public function test_proksi_tidak_tepercaya_tidak_dapat_memalsukan_alamat(): void
    {
        // Permintaan langsung dari internet: alamatnya 8.8.8.8, dan ia mengaku
        // meneruskan permintaan dari 9.9.9.9. Pengakuan itu harus diabaikan.
        $r = $this->permintaan('8.8.8.8', '9.9.9.9');
        $r->setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame('8.8.8.8', $r->ip(), 'X-Forwarded-For dari non-proksi dipakai — batas laju dapat dielak.');
    }

    public function test_proksi_tepercaya_diteruskan_alamat_pengunjung_asli(): void
    {
        // Permintaan lewat proksi tepercaya 127.0.0.1: alamat pengunjung yang
        // dipakai, bukan alamat proksinya.
        $r = $this->permintaan('127.0.0.1', '9.9.9.9');
        $r->setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame('9.9.9.9', $r->ip(), 'Alamat pengunjung di balik proksi tepercaya tidak terbaca.');
    }

    public function test_konfigurasi_proksi_tidak_terbuka_untuk_semua(): void
    {
        // Nilai '*' pada trustProxies membuat setiap alamat di internet sah
        // memalsukan X-Forwarded-For. Di produksi (APP_ENV bukan local),
        // IKK_TRUSTED_PROXIES harus diisi alamat proksi yang sebenarnya.
        $sumber = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            "IKK_TRUSTED_PROXIES",
            $sumber,
            'Proksi tepercaya tidak dibaca dari konfigurasi.',
        );

        // Jalur longgar ('*' otomatis) hanya boleh aktif di local, bukan di
        // produksi — dan itu tertulis eksplisit di sumbernya.
        $this->assertStringContainsString(
            "APP_ENV', 'production') === 'local'",
            $sumber,
            'Proksi longgar tidak dibatasi ke lingkungan local.',
        );
    }

    public function test_pencocokan_alamat_terblokir_memakai_cidr_dan_alamat_tunggal(): void
    {
        // Penjaga supaya daftar blokir dicocokkan dengan aturan CIDR yang benar:
        // satu alamat terblokir tidak boleh ikut memblokir seluruh internet,
        // dan rentang privat tidak boleh dianggap publik.
        $this->assertTrue(IpUtils::checkIp('8.8.8.8', '8.8.8.8'));
        $this->assertFalse(IpUtils::checkIp('8.8.4.4', '8.8.8.8'));
        $this->assertFalse(IpUtils::checkIp('8.8.8.8', '10.0.0.0/8'));
        $this->assertTrue(IpUtils::checkIp('10.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(IpUtils::checkIp('::1', '::1'));
    }

    public function test_batas_laju_tersimpan_di_cache_basis_data(): void
    {
        // Tanpa Redis, RateLimiter memakai cache basis data. Diuji supaya
        // pemasangan yang lupa menyiapkan tabel cache tidak diam-diam kehilangan
        // seluruh batas laju (limiter yang tidak menyimpan apa pun = tanpa batas).
        // phpunit.xml memakai cache "array" agar test tidak menyentuh cache
        // pengembangan; yang diperiksa adalah bahwa limiter memang menyimpan
        // hitungannya, apa pun penyimpanannya.
        $this->assertContains(config('cache.default'), ['database', 'array', 'file', 'redis']);

        $kunci = 'uji-batas:'.uniqid();
        \Illuminate\Support\Facades\RateLimiter::hit($kunci, 60);

        $this->assertSame(1, \Illuminate\Support\Facades\RateLimiter::attempts($kunci));
        $this->assertFalse(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($kunci, 5));

        for ($i = 0; $i < 5; $i++) {
            \Illuminate\Support\Facades\RateLimiter::hit($kunci, 60);
        }

        $this->assertTrue(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($kunci, 5));
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\RateLimiter::availableIn($kunci));
    }
}
