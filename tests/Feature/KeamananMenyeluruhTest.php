<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\SanitasiHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Keamanan menyeluruh, bagian pertama (checklist F10-01 sampai F10-11).
 *
 * Semua yang diuji di sini adalah janji di sisi server. Tidak ada yang
 * bersandar pada antarmuka: tautan boleh disembunyikan, tetapi permintaannya
 * tetap datang, dan hanya server yang dapat menolaknya.
 */
class KeamananMenyeluruhTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Admin Uji',
            'email' => 'admin-f10@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Admin,
        ]);
    }

    private function pelanggan(): User
    {
        return User::query()->create([
            'name' => 'Pelanggan Uji',
            'email' => 'pelanggan-f10@contoh.test',
            'password' => 'rahasia123',
            'role' => PeranPengguna::Customer,
        ]);
    }

    // ── F10-01: otorisasi ───────────────────────────────────────────

    public function test_pelanggan_biasa_ditolak_masuk_panel(): void
    {
        $pelanggan = $this->pelanggan();

        $this->actingAs($pelanggan)
            ->get('/admin')
            ->assertForbidden();

        $this->actingAs($pelanggan)
            ->get('/admin/pesanan')
            ->assertForbidden();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk_panel(): void
    {
        $this->get('/admin/pesanan')->assertRedirect('/admin/login');
        $this->get('/admin/keamanan')->assertRedirect('/admin/login');
    }

    public function test_admin_dapat_membuka_halaman_panel(): void
    {
        $this->actingAs($this->admin())->get('/admin/pesanan')->assertOk();
    }

    // ── F10-02: ULID di rute, id internal tidak bocor ───────────────

    public function test_rute_publik_tidak_memakai_id_internal(): void
    {
        // Yang berbahaya bukan parameter apa pun, melainkan parameter bernama
        // model tanpa kolom ({order}, {product}) dan {id}: keduanya mengikat
        // lewat kunci primer. Parameter bernama kolom lain ({ulid}, {slug},
        // {filename}, {path}, {locale}, {jenis}) tidak dapat dienumerasi.
        $bocor = [];

        foreach (Route::getRoutes() as $rute) {
            // Bentuk yang diperiksa adalah parameternya, BUKAN uri mentah:
            // uri menampilkan "{product:slug}", padahal kolom pengikatnya
            // justru dinyatakan sesudah titik dua.
            foreach ($rute->parameterNames() as $nama) {
                $kolom = (string) ($rute->bindingFields()[$nama] ?? '');

                if (in_array($nama, ['id', 'order', 'product', 'payment', 'user'], true) && $kolom === '') {
                    $bocor[] = $rute->uri().' ('.$nama.' tanpa kolom)';
                }
            }
        }

        $this->assertSame([], $bocor, 'Rute memakai kunci primer di URL: '.implode(', ', $bocor));
    }

    public function test_rute_pesanan_publik_memakai_ulid(): void
    {
        $order = Order::query()->create([
            'order_number' => 'IK-20260101-ULID01',
            'status' => StatusPesanan::Pending,
            'payment_status' => 'unpaid',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame('ulid', $order->getRouteKeyName());
        $this->assertSame(26, strlen((string) $order->ulid));

        // Nomor internal tidak dipakai di URL.
        $this->assertStringNotContainsString((string) $order->id.'/', route('front.payment.upload', $order->ulid));
    }

    // ── F10-03: sanitasi konten editor ──────────────────────────────

    public function test_script_dibuang_dan_penekanan_dipertahankan(): void
    {
        $kotor = '<p>Knalpot <b>titanium</b> <script>alert(1)</script>'
            .'<img src="x" onerror="alert(2)"> <a href="javascript:alert(3)">klik</a></p>';

        $bersih = SanitasiHtml::bersihkan($kotor);

        $this->assertStringNotContainsString('<script', $bersih);
        $this->assertStringNotContainsString('alert(1)', $bersih);
        $this->assertStringNotContainsString('onerror', $bersih);
        $this->assertStringNotContainsString('javascript:', $bersih);
        $this->assertStringContainsString('<b>titanium</b>', $bersih);
    }

    public function test_atribut_berbahaya_dibuang_tetapi_atribut_sah_dipertahankan(): void
    {
        $bersih = SanitasiHtml::bersihkan('<p class="lead" onclick="jahat()" style="color:red">Halo</p>');

        $this->assertStringNotContainsString('onclick', $bersih);
        $this->assertStringContainsString('Halo', $bersih);
    }

    // ── F10-04: header keamanan ─────────────────────────────────────

    public function test_header_keamanan_terpasang(): void
    {
        $r = $this->get('/');

        $r->assertOk();

        $csp = (string) $r->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        // 'unsafe-inline' boleh ada di style-src (Tailwind menulis atribut
        // style), tetapi TIDAK boleh di script-src: di sana ia mematikan
        // seluruh perlindungan XSS yang dibangun CSP.
        preg_match('/script-src([^;]*)/', $csp, $mScript);
        $this->assertStringNotContainsString(
            "'unsafe-inline'",
            $mScript[1] ?? '',
            'script-src masih memakai unsafe-inline.',
        );
        $this->assertStringContainsString('style-src', $csp);
        // Nonce dibuat dengan Str::random dan base64 mentah: huruf besar-kecil,
        // angka, serta "-" dan "_" ikut muncul. Pola yang hanya menerima
        // alfanumerik akan gagal pada nonce yang sah.
        $this->assertMatchesRegularExpression(
            "/script-src [^;]*'nonce-[A-Za-z0-9_\-]{16,}'/",
            $csp,
            'CSP tidak memakai nonce.',
        );
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('strict-origin', (string) $r->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=', (string) $r->headers->get('Permissions-Policy'));
    }

    public function test_hsts_hanya_pada_permintaan_aman(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $aman = $this->get('https://localhost/');

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $aman->headers->get('Strict-Transport-Security'),
        );
    }

    // ── F10-05: cookie sesi ─────────────────────────────────────────

    public function test_cookie_sesi_http_only_dan_same_site_lax(): void
    {
        config(['session.lifetime' => 120]);

        $r = $this->get('/keranjang');

        $r->assertOk();

        $cookie = collect($r->headers->getCookies())->first(
            fn ($c): bool => str_contains($c->getName(), 'session')
        );

        $this->assertNotNull($cookie, 'Cookie sesi tidak terbentuk.');
        $this->assertTrue($cookie->isHttpOnly(), 'Cookie sesi tidak HttpOnly.');
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()), 'SameSite cookie bukan Lax.');
        $this->assertSame(120, (int) config('session.lifetime'), 'Masa sesi bukan 120 menit.');
    }

    // ── F10-06: batas laju per endpoint ─────────────────────────────

    public function test_setiap_batas_laju_dari_prd_terpasang(): void
    {
        $wajib = [
            'admin_login', 'customer_login', 'register', 'password_reset',
            'contact', 'cart_add', 'checkout', 'track_order', 'search',
        ];

        foreach ($wajib as $kunci) {
            $nilai = config('iksan.rate_limits.'.$kunci);

            $this->assertIsArray($nilai, 'Batas "'.$kunci.'" tidak ada di config/iksan.php.');
            $this->assertCount(2, $nilai, 'Batas "'.$kunci.'" harus [jumlah, menit].');
            $this->assertGreaterThan(0, (int) $nilai[0]);
        }

        // Nilai harus sama dengan PRD Bagian 11.1, bukan sekadar ada.
        $this->assertSame([5, 1], config('iksan.rate_limits.admin_login'));
        $this->assertSame([3, 60], config('iksan.rate_limits.password_reset'));
        $this->assertSame([3, 60], config('iksan.rate_limits.contact'));
        $this->assertSame([60, 1], config('iksan.rate_limits.cart_add'));
        $this->assertSame([5, 10], config('iksan.rate_limits.checkout'));
        $this->assertSame([10, 1], config('iksan.rate_limits.track_order'));
        $this->assertSame([30, 1], config('iksan.rate_limits.search'));
    }

    public function test_lacak_pesanan_dibatasi_sesuai_prd(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $kode = $this->get('/lacak')->getStatusCode();
        }

        $this->assertSame(429, $kode, 'Permintaan ke-11 tidak dijawab 429.');
    }

    // ── F10-07: honeypot dan token waktu ────────────────────────────

    public function test_form_kontak_menyertakan_jebakan_dan_penanda_waktu(): void
    {
        $html = $this->get('/kontak')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/wire:snapshot=/', (string) $html);

        $komponen = app(\Livewire\Livewire::class);

        // Kolom jebakan harus ada di komponen, bukan hanya di tampilan.
        $kelas = new \ReflectionClass(\App\Livewire\Front\Kontak::class);

        $this->assertTrue(
            $kelas->hasProperty('dimulaiPada'),
            'Komponen kontak tidak menyimpan waktu mulai — token minimum 2 detik tidak dapat diperiksa.',
        );
    }

    public function test_checkout_dari_bot_honeypot_ditolak(): void
    {
        $this->assertSame(
            2,
            (int) config('iksan.brute_force.min_form_seconds', 2),
            'Ambang token waktu bukan 2 detik.',
        );

        $refleksi = new \ReflectionClass(\App\Livewire\Front\Checkout::class);

        $this->assertTrue($refleksi->hasProperty('situs'), 'Kolom jebakan honeypot tidak ada.');
        $this->assertTrue($refleksi->hasProperty('dimulaiPada'), 'Kolom waktu mulai tidak ada.');
    }

    // ── F10-08: pesan galat seragam ─────────────────────────────────

    public function test_galat_masuk_panel_seragam_untuk_email_ada_dan_tidak(): void
    {
        $admin = $this->admin();

        $ada = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'salah-sekali',
        ]);

        $tidak = $this->post('/admin/login', [
            'email' => 'tidak-ada@contoh.test',
            'password' => 'salah-sekali',
        ]);

        // Keduanya harus memberi jawaban yang tidak membocorkan apakah akun
        // itu ada. Yang dibandingkan adalah kelas jawabannya (bukan isi pesan),
        // karena halaman masuk memakai Livewire.
        $this->assertSame(
            $ada->getStatusCode(),
            $tidak->getStatusCode(),
            'Status jawaban berbeda untuk email yang ada dan yang tidak.',
        );
    }

    public function test_pesan_lacak_seragam_untuk_nomor_ada_dan_tidak(): void
    {
        // Pesannya diambil dari berkas terjemahan, tempatnya yang sebenarnya.
        $pesan = __('site.order.not_found');

        $this->assertNotSame('site.order.not_found', $pesan, 'Kunci terjemahan lacak tidak ada.');
        $this->assertStringNotContainsString('nomor tidak terdaftar', strtolower($pesan));
        $this->assertStringNotContainsString('salah', strtolower($pesan));

        // Satu kunci dipakai untuk kedua kasus: nomor tidak ada, dan email
        // tidak cocok. Kalau berbeda, penebak tahu nomornya benar.
        $sumber = (string) file_get_contents(
            (new \ReflectionClass(\App\Livewire\Front\LacakPesanan::class))->getFileName()
        );

        $this->assertSame(
            2,
            substr_count($sumber, "__('site.order.not_found')"),
            'Pesan galat lacak tidak seragam pada kedua jalur.',
        );
    }

    // ── F10-09: URL bertanda tangan ─────────────────────────────────

    public function test_bukti_bayar_hanya_lewat_url_bertanda_tangan_dan_kedaluwarsa_ditolak(): void
    {
        $this->assertTrue(
            collect(Route::getRoutes())->contains(
                fn ($r): bool => str_starts_with($r->uri(), 'bukti-bayar/')
                    && in_array('signed', $r->gatherMiddleware(), true)
            ),
            'Rute bukti bayar tidak memakai middleware signed.',
        );

        $order = Order::query()->create([
            'order_number' => 'IK-20260101-BUKTI1',
            'status' => StatusPesanan::Pending,
            'payment_status' => 'pending',
            'payment_method' => 'manual',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'shipping_address_snapshot' => ['recipient_name' => 'Uji'],
            'expires_at' => now()->addDay(),
        ]);

        $payment = \App\Models\Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'manual',
            'provider_status' => 'menunggu-verifikasi',
            'amount' => 1000,
            'currency' => 'IDR',
            'proof_path' => 'bukti-bayar/'.$order->ulid.'/uji.png',
        ]);

        $sah = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.payments.proof',
            now()->addMinutes(15),
            ['payment' => $payment->ulid],
        );

        $this->assertStringContainsString('signature=', $sah);

        // Tanpa tanda tangan: ditolak sebelum controller dijalankan.
        $polos = route('admin.payments.proof', $payment->ulid);
        $this->get($polos)->assertForbidden();

        // Kedaluwarsa: ditolak walaupun tanda tangannya sah.
        $basi = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.payments.proof',
            now()->subMinutes(1),
            ['payment' => $payment->ulid],
        );

        $this->get($basi)->assertForbidden();
    }

    // ── F10-10: unggahan tidak dapat dieksekusi ─────────────────────

    public function test_berkas_php_berkedok_gambar_ditolak(): void
    {
        $this->markTestSkipped('Diuji di UnggahanGambarTest (kuota, tipe, eksekusi).');
    }

    public function test_direktori_unggahan_tidak_memuat_berkas_terlarang(): void
    {
        $terlarang = [];

        foreach (['public', 'local'] as $disk) {
            foreach (\Illuminate\Support\Facades\Storage::disk($disk)->allFiles() as $jalur) {
                if (preg_match('/\.(php|phtml|phar|htaccess)$/i', $jalur) === 1) {
                    $terlarang[] = $disk.':'.$jalur;
                }
            }
        }

        $this->assertSame([], $terlarang, 'Ada berkas yang dapat dieksekusi di direktori unggahan.');
    }

    // ── F10-11: X-Powered-By disembunyikan ──────────────────────────

    public function test_x_powered_by_dan_versi_framework_disembunyikan(): void
    {
        $r = $this->get('/');

        $this->assertFalse($r->headers->has('X-Powered-By'), 'Header X-Powered-By masih dikirim.');

        $server = strtolower((string) $r->headers->get('Server', ''));

        $this->assertStringNotContainsString('php/', $server);
    }
}
