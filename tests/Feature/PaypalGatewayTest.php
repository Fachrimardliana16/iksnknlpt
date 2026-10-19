<?php

namespace Tests\Feature;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Enums\GrupPengaturan;
use App\Services\Pembayaran\PaypalGateway;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * PayPal: pembuatan pesanan, penangkapan dana, dan webhook (checklist F9-01
 * sampai F9-04).
 *
 * Semua panggilan ke PayPal dipalsukan (Http::fake), tetapi bentuk permintaan
 * dan balasan mengikuti dokumentasi PayPal — yang diuji adalah cara aplikasi
 * membaca dan memutuskan, bukan ketersediaan PayPal.
 *
 * Yang paling penting diuji di sini bukan jalur bahagia, melainkan tiga
 * kegagalan yang mahal:
 *
 *   - nominal diambil dari basis data, bukan dari permintaan (kalau tidak,
 *     pembeli dapat membayar Rp 1 untuk pesanan Rp 5 juta);
 *   - tanda tangan webhook yang tidak sah ditolak, dan pesanan tidak berubah;
 *   - webhook yang datang dua kali tidak menghasilkan dua baris pembayaran.
 */
class PaypalGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function pesanan(array $atribut = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'IK-20260101-PPAL01',
            'status' => StatusPesanan::Pending,
            'payment_status' => 'unpaid',
            'payment_method' => 'paypal',
            'guest_email' => 'pembeli@contoh.test',
            'subtotal' => 5000000,
            'grand_total' => 5000000,
            'currency' => 'IDR',
            'shipping_address_snapshot' => ['recipient_name' => 'Pembeli Uji', 'city' => 'Sleman'],
            'expires_at' => now()->addDay(),
        ], $atribut));
    }

    private function setel(array $nilai): void
    {
        foreach ($nilai as $kunci => $isi) {
            Setting::query()->updateOrCreate(
                ['key' => $kunci],
                ['value' => $isi, 'group' => GrupPengaturan::Kontak],
            );
        }

        Pengaturan::lupakan();
    }

    private function kredensial(): void
    {
        $this->setel([
            'paypal_client_id' => 'id-uji',
            'paypal_client_secret' => 'rahasia-uji',
            'paypal_mode' => 'sandbox',
            'paypal_webhook_id' => 'WH-UJI-1',
        ]);
    }

    // ── F9-01: membuat pesanan PayPal ───────────────────────────────

    public function test_pesanan_paypal_dibuat_dengan_nominal_dari_basis_data(): void
    {
        $this->kredensial();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'token-uji',
                'token_type' => 'Bearer',
                'expires_in' => 32400,
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PP-ORDER-1',
                'status' => 'CREATED',
                'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER-1'],
                ],
            ], 201),
        ]);

        $pesanan = $this->pesanan();

        $hasil = app(PaypalGateway::class)->buatPesanan($pesanan);

        $this->assertSame('PP-ORDER-1', $hasil['id']);
        $this->assertSame('CREATED', $hasil['status']);
        $this->assertStringContainsString('sandbox.paypal.com', (string) $hasil['setuju_url']);

        Http::assertSent(function (Request $r): bool {
            if ($r->url() !== 'https://api-m.sandbox.paypal.com/v2/checkout/orders') {
                return false;
            }

            return data_get($r->data(), 'purchase_units.0.amount.value') === '5000000.00'
                && data_get($r->data(), 'purchase_units.0.reference_id') === 'IK-20260101-PPAL01';
        });
    }

    public function test_kredensial_kosong_ditolak_sebelum_menghubungi_paypal(): void
    {
        Http::fake();

        $this->setel(['paypal_client_id' => '', 'paypal_client_secret' => '']);

        $this->expectException(RuntimeException::class);

        try {
            app(PaypalGateway::class)->buatPesanan($this->pesanan());
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kredensial PayPal belum diisi', $e->getMessage());
            Http::assertNothingSent();

            throw $e;
        }
    }

    // ── F9-02: menangkap dana ───────────────────────────────────────

    public function test_tangkap_dana_membuat_pesanan_lunas(): void
    {
        $this->kredensial();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-uji'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/PP-ORDER-1/capture' => Http::response([
                'id' => 'PP-ORDER-1',
                'status' => 'COMPLETED',
                'purchase_units' => [[
                    'payments' => ['captures' => [[
                        'id' => 'CAPTURE-99',
                        'status' => 'COMPLETED',
                        'amount' => ['value' => '5000000.00', 'currency_code' => 'IDR'],
                    ]]],
                ]],
            ], 201),
        ]);

        $pesanan = $this->pesanan();

        $payment = app(PaypalGateway::class)->tangkap($pesanan, 'PP-ORDER-1');

        $this->assertSame('CAPTURE-99', $payment->provider_transaction_id);
        $this->assertSame('paypal', $payment->provider instanceof \BackedEnum ? $payment->provider->value : $payment->provider);

        $segar = $pesanan->fresh();
        $this->assertSame(StatusPesanan::Paid, $segar->status);
        $this->assertSame('paid', $segar->payment_status instanceof \BackedEnum ? $segar->payment_status->value : $segar->payment_status);
        $this->assertNotNull($segar->paid_at);
    }

    public function test_status_paypal_bukan_completed_tidak_melunasi(): void
    {
        $this->kredensial();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-uji'], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/*' => Http::response(['id' => 'PP-ORDER-2', 'status' => 'PENDING'], 201),
        ]);

        $pesanan = $this->pesanan();

        try {
            app(PaypalGateway::class)->tangkap($pesanan, 'PP-ORDER-2');
            $this->fail('Pembayaran yang belum selesai tidak boleh dianggap lunas.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('PENDING', $e->getMessage());
        }

        $this->assertSame(StatusPesanan::Pending, $pesanan->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    // ── F9-03 dan F9-04: webhook ────────────────────────────────────

    public function test_webhook_dengan_tanda_tangan_palsu_ditolak_dan_pesanan_tidak_berubah(): void
    {
        $this->kredensial();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-uji'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'FAILURE',
            ], 200),
        ]);

        $pesanan = $this->pesanan();

        $peristiwa = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-PALSU',
                'amount' => ['value' => '1.00', 'currency_code' => 'IDR'],
                'purchase_units' => [['reference_id' => 'IK-20260101-PPAL01']],
            ],
        ];

        $gateway = app(PaypalGateway::class);
        $sah = $gateway->verifikasiTandaTangan($peristiwa);

        $this->assertFalse($sah, 'Tanda tangan yang ditolak PayPal harus dianggap tidak sah.');

        $hasil = $gateway->tanganiWebhook($peristiwa, $sah);

        $this->assertTrue($hasil['diabaikan']);
        $this->assertSame(StatusPesanan::Pending, $pesanan->fresh()->status, 'Pesanan berubah karena webhook palsu.');
        $this->assertSame(0, Payment::query()->count(), 'Webhook palsu membuat baris pembayaran.');
    }

    public function test_webhook_sah_melunasi_pesanan(): void
    {
        $this->kredensial();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token-uji'], 200),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);

        $pesanan = $this->pesanan();

        $peristiwa = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-77',
                'amount' => ['value' => '5000000.00', 'currency_code' => 'IDR'],
                'purchase_units' => [['reference_id' => 'IK-20260101-PPAL01']],
            ],
        ];

        $hasil = app(PaypalGateway::class)->tanganiWebhook($peristiwa, true);

        $this->assertFalse($hasil['diabaikan']);
        $this->assertSame(StatusPesanan::Paid, $pesanan->fresh()->status);
        $this->assertSame(1, Payment::query()->where('provider_transaction_id', 'CAPTURE-77')->count());
    }

    public function test_webhook_ganda_tidak_menghasilkan_dua_baris_pembayaran(): void
    {
        $this->kredensial();

        Http::fake(['api-m.sandbox.paypal.com/*' => Http::response(['verification_status' => 'SUCCESS'], 200)]);

        $pesanan = $this->pesanan();

        $peristiwa = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-88',
                'amount' => ['value' => '5000000.00', 'currency_code' => 'IDR'],
                'purchase_units' => [['reference_id' => 'IK-20260101-PPAL01']],
            ],
        ];

        $gateway = app(PaypalGateway::class);

        $pertama = $gateway->tanganiWebhook($peristiwa, true);
        $kedua = $gateway->tanganiWebhook($peristiwa, true);

        $this->assertFalse($pertama['diabaikan']);
        $this->assertTrue($kedua['diabaikan'], 'Kiriman kedua harus diabaikan.');
        $this->assertStringContainsString('sudah tercatat', $kedua['alasan']);

        $this->assertSame(
            1,
            Payment::query()->where('provider', 'paypal')->where('provider_transaction_id', 'CAPTURE-88')->count(),
            'Webhook ganda membuat dua baris pembayaran.',
        );
    }

    public function test_webhook_tanpa_id_webhook_di_pengaturan_ditolak(): void
    {
        $this->setel(['paypal_client_id' => 'id-uji', 'paypal_client_secret' => 'rahasia', 'paypal_webhook_id' => '']);

        $this->assertFalse(
            app(PaypalGateway::class)->verifikasiTandaTangan(['event_type' => 'X']),
            'Tanpa id webhook, tanda tangan tidak dapat diverifikasi dan harus ditolak.',
        );
    }

    public function test_peristiwa_yang_tidak_memengaruhi_status_diabaikan(): void
    {
        $this->kredensial();

        $this->pesanan();

        $hasil = app(PaypalGateway::class)->tanganiWebhook(['event_type' => 'CUSTOMER.DISPUTE.CREATED'], true);

        $this->assertTrue($hasil['diabaikan']);
        $this->assertSame(0, Payment::query()->count());
    }
}
