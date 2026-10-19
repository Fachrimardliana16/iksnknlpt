<?php

namespace App\Services\Pembayaran;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\Payment;
use App\Services\StatusPesananService;
use App\Support\CatatanAktivitas;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pesanan PayPal dan penanganan kembaliannya (checklist F9-01, F9-02, F9-03,
 * F9-04).
 *
 * Tiga hal yang menentukan benar atau tidaknya modul pembayaran, dan ketiganya
 * dikerjakan di sini:
 *
 * 1. Nominal TIDAK diambil dari permintaan. Yang dipakai adalah `grand_total`
 *    dari basis data. Kalau nominalnya ikut dari sisi klien, pembeli dapat
 *    membayar Rp 1 untuk pesanan Rp 5 juta dan pesanan tetap menjadi lunas.
 * 2. Webhook diverifikasi tanda tangannya ke PayPal sebelum dipercaya. Tanpa
 *    itu siapa pun yang tahu alamat webhook dapat menandai pesanannya lunas
 *    dengan satu permintaan POST.
 * 3. Webhook yang datang dua kali tidak boleh menghasilkan dua baris
 *    pembayaran. Indeks unik `provider + provider_transaction_id` yang
 *    menjaga; di sini pengecekannya dilakukan lebih dulu supaya balasannya
 *    tetap 200 (PayPal mengulang kiriman bila tidak menerima 200, dan
 *    pengulangan tanpa akhir akan tercatat sebagai gangguan).
 */
class PaypalGateway
{
    private const TIMEOUT = 20;

    /** Cache token selama satu permintaan saja: PayPal memberi 9 jam. */
    private ?string $token = null;

    public function __construct(private readonly ?KlienPaypal $klien = null) {}

    private function klien(): KlienPaypal
    {
        return $this->klien ?? new KlienPaypal;
    }

    /**
     * Membuat pesanan PayPal untuk satu pesanan toko (checklist F9-01).
     *
     * @return array{id: string, status: string, setuju_url: ?string}
     */
    public function buatPesanan(Order $pesanan): array
    {
        $token = $this->aksesToken();

        $muatan = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $pesanan->order_number,
                // Nominal dari basis data, bukan dari permintaan.
                'amount' => [
                    'currency_code' => strtoupper((string) ($pesanan->currency ?: 'IDR')),
                    'value' => number_format((float) $pesanan->grand_total, 2, '.', ''),
                ],
                'description' => mb_substr('Pesanan '.$pesanan->order_number, 0, 127),
            ]],
            'application_context' => [
                'brand_name' => mb_substr((string) Pengaturan::instance()->teks('site_name', 'Iksan Knalpot'), 0, 127),
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->post($this->klien()->dasar().'/v2/checkout/orders', $muatan);

        if (! $respons->successful()) {
            Log::warning('PayPal menolak pembuatan pesanan', [
                'pesanan' => $pesanan->order_number,
                'status' => $respons->status(),
                'balasan' => mb_substr((string) $respons->body(), 0, 300),
            ]);

            throw new RuntimeException('PayPal menolak pembuatan pesanan (HTTP '.$respons->status().').');
        }

        $setuju = collect((array) $respons->json('links', []))
            ->firstWhere('rel', 'approve');

        return [
            'id' => (string) $respons->json('id'),
            'status' => (string) $respons->json('status'),
            'setuju_url' => is_array($setuju) ? (string) ($setuju['href'] ?? '') : null,
        ];
    }

    /**
     * Menangkap dana setelah pembeli menyetujui pembayaran (checklist F9-02).
     *
     * Status PayPal yang diterima adalah COMPLETED. Yang ditangkap adalah
     * transaction id hasil capture, bukan id pesanan PayPal — id itu yang
     * dipakai PayPal di webhook, sehingga baris pembayaran dan webhook yang
     * datang kemudian merujuk ke transaksi yang sama.
     */
    public function tangkap(Order $pesanan, string $paypalOrderId): Payment
    {
        $token = $this->aksesToken();

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->withBody('{}', 'application/json')
            ->post($this->klien()->dasar().'/v2/checkout/orders/'.$paypalOrderId.'/capture');

        if (! $respons->successful()) {
            throw new RuntimeException('PayPal gagal menangkap pembayaran (HTTP '.$respons->status().').');
        }

        $status = strtoupper((string) $respons->json('status'));

        if ($status !== 'COMPLETED') {
            throw new RuntimeException('PayPal melaporkan status '.$status.' — pembayaran belum selesai.');
        }

        $transaksiId = (string) (
            $respons->json('purchase_units.0.payments.captures.0.id')
            ?: $paypalOrderId
        );

        return $this->catatPembayaran(
            $pesanan,
            $transaksiId,
            (float) $respons->json('purchase_units.0.payments.captures.0.amount.value', $pesanan->grand_total),
            strtoupper((string) $respons->json('purchase_units.0.payments.captures.0.amount.currency_code', $pesanan->currency ?: 'IDR')),
            ['sumber' => 'capture', 'status_paypal' => $status],
        );
    }

    /**
     * Memverifikasi tanda tangan webhook ke PayPal (checklist F9-03).
     *
     * Verifikasi dilakukan dengan memanggil endpoint PayPal, bukan dengan
     * menghitung ulang HMAC di sisi kita: kunci yang dipakai PayPal adalah
     * kunci mereka, dan menebaknya berarti menganggap semua kiriman sah.
     *
     * @param  array<string, mixed>  $peristiwa
     * @return bool true bila PayPal menyatakan kiriman ini asli
     */
    public function verifikasiTandaTangan(array $peristiwa): bool
    {
        $idTransmisi = (string) (getenv('PAYPAL_TRANSMISI_ID') ?: '');
        $tanggal = (string) (getenv('PAYPAL_TRANSMISI_WAKTU') ?: '');
        $tandaTangan = (string) (getenv('PAYPAL_TANDA_TANGAN') ?: '');
        $idWebhook = (string) (Pengaturan::instance()?->teks('paypal_webhook_id') ?? '');

        // Tanpa id webhook, PayPal tidak dapat memastikan kiriman ini milik
        // toko ini. Menolak lebih baik daripada menerima tanpa dasar.
        if ($idWebhook === '') {
            return false;
        }

        $token = $this->aksesToken();

        $respons = Http::withToken($token)
            ->timeout(self::TIMEOUT)
            ->acceptJson()
            ->post($this->klien()->dasar().'/v1/notifications/verify-webhook-signature', [
                'transmission_id' => $idTransmisi,
                'transmission_time' => $tanggal,
                'cert_url' => (string) (getenv('PAYPAL_SERTIFIKAT_URL') ?: ''),
                'auth_algo' => (string) (getenv('PAYPAL_ALGO') ?: 'SHA256withRSA'),
                'transmission_sig' => $tandaTangan,
                'webhook_id' => $idWebhook,
                'webhook_event' => $peristiwa,
            ]);

        if (! $respons->successful()) {
            return false;
        }

        return strtoupper((string) $respons->json('verification_status')) === 'SUCCESS';
    }

    /**
     * Menangani satu peristiwa webhook (checklist F9-03, F9-04).
     *
     * @param  array<string, mixed>  $peristiwa
     * @return array{diabaikan: bool, alasan: string}
     */
    public function tanganiWebhook(array $peristiwa, bool $tandaTanganSah): array
    {
        if (! $tandaTanganSah) {
            CatatanAktivitas::catat('paypal.webhook-tanda-tangan-palsu', [
                'jenis' => $peristiwa['event_type'] ?? null,
            ]);

            return ['diabaikan' => true, 'alasan' => 'Tanda tangan tidak sah.'];
        }

        $jenis = (string) ($peristiwa['event_type'] ?? '');

        if (! in_array($jenis, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
            return ['diabaikan' => true, 'alasan' => 'Peristiwa '.$jenis.' tidak memengaruhi status pesanan.'];
        }

        $sumber = (array) ($peristiwa['resource'] ?? []);

        $transaksiId = (string) (
            $sumber['id']
            ?? data_get($sumber, 'supplementary_data.related_ids.order_id')
            ?? ''
        );

        $nomorPesanan = (string) (
            data_get($sumber, 'purchase_units.0.reference_id')
            ?? data_get($sumber, 'custom_id')
            ?? ''
        );

        $pesanan = $this->cariPesanan($nomorPesanan, $sumber);

        if ($pesanan === null) {
            return ['diabaikan' => true, 'alasan' => 'Pesanan untuk peristiwa ini tidak ditemukan.'];
        }

        if ($transaksiId === '') {
            return ['diabaikan' => true, 'alasan' => 'Peristiwa tanpa id transaksi.'];
        }

        // Kiriman ganda: PayPal mengulang webhook bila tidak menerima 200.
        // Yang penting bukan menolaknya, tetapi tidak mencatatnya dua kali.
        $sudahAda = Payment::query()
            ->where('provider', 'paypal')
            ->where('provider_transaction_id', $transaksiId)
            ->exists();

        if ($sudahAda) {
            return ['diabaikan' => true, 'alasan' => 'Transaksi '.$transaksiId.' sudah tercatat.'];
        }

        $this->catatPembayaran(
            $pesanan,
            $transaksiId,
            (float) ($sumber['amount']['value'] ?? $pesanan->grand_total),
            strtoupper((string) ($sumber['amount']['currency_code'] ?? ($pesanan->currency ?: 'IDR'))),
            ['sumber' => 'webhook', 'jenis' => $jenis],
        );

        return ['diabaikan' => false, 'alasan' => 'Pembayaran dicatat.'];
    }

    /**
     * Mencatat pembayaran dan memindahkan pesanan ke lunas.
     *
     * Dipakai jalur capture maupun webhook, supaya keduanya menghasilkan baris
     * yang sama persis. Perpindahan status lewat StatusPesananService agar
     * stok tertahan tetap seimbang.
     */
    private function catatPembayaran(
        Order $pesanan,
        string $transaksiId,
        float $nominal,
        string $mataUang,
        array $muatan,
    ): Payment {
        $pembayaran = DB::transaction(function () use ($pesanan, $transaksiId, $nominal, $mataUang, $muatan): Payment {
            $payment = Payment::query()->create([
                'order_id' => $pesanan->id,
                'provider' => 'paypal',
                'provider_transaction_id' => $transaksiId,
                'provider_status' => 'completed',
                'amount' => $nominal,
                'currency' => $mataUang,
                'payload_json' => $muatan,
                'verified_at' => now(),
            ]);

            $pesanan->update(['payment_status' => StatusPembayaran::Paid]);

            return $payment;
        });

        if ($pesanan->fresh()->status === StatusPesanan::Pending) {
            app(StatusPesananService::class)->pindah(
                $pesanan->fresh(),
                StatusPesanan::Paid,
                null,
                'Pembayaran PayPal diterima',
            );

            KirimNotifikasiPesanan::dispatch($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBAYAR);
        }

        CatatanAktivitas::catat('paypal.pembayaran-diterima', [
            'order_number' => $pesanan->order_number,
            'transaksi' => $transaksiId,
            'nominal' => $nominal,
        ], null, $pesanan);

        return $pembayaran;
    }

    /**
     * Pesanan untuk sebuah peristiwa.
     *
     * Urutannya: reference_id (nomor pesanan yang kita kirim sendiri), lalu
     * id PayPal yang pernah tercatat di baris pembayaran.
     *
     * @param  array<string, mixed>  $sumber
     */
    private function cariPesanan(string $nomorPesanan, array $sumber): ?Order
    {
        if ($nomorPesanan !== '') {
            $pesanan = Order::query()->where('order_number', $nomorPesanan)->first();

            if ($pesanan !== null) {
                return $pesanan;
            }
        }

        $idPaypal = (string) (($sumber['id'] ?? '') ?: '');

        if ($idPaypal === '') {
            return null;
        }

        $pembayaran = Payment::query()
            ->where('provider', 'paypal')
            ->where('provider_transaction_id', $idPaypal)
            ->first();

        return $pembayaran?->order()->first();
    }

    /** Token OAuth PayPal, diminta sekali per objek. */
    private function aksesToken(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $id = (string) (\App\Support\KomponenPengaturan::mentah('paypal_client_id') ?? '');
        $rahasia = (string) (\App\Support\KomponenPengaturan::mentah('paypal_client_secret') ?? '');

        if ($id === '' || $rahasia === '') {
            throw new RuntimeException('Kredensial PayPal belum diisi di pengaturan.');
        }

        try {
            $respons = Http::asForm()
                ->withBasicAuth($id, $rahasia)
                ->timeout(self::TIMEOUT)
                ->post($this->klien()->dasar().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (Throwable $e) {
            throw new RuntimeException('PayPal tidak dapat dihubungi: '.$e->getMessage());
        }

        if (! $respons->successful()) {
            throw new RuntimeException('PayPal menolak kredensial (HTTP '.$respons->status().').');
        }

        $token = (string) $respons->json('access_token');

        if ($token === '') {
            throw new RuntimeException('PayPal tidak mengembalikan access_token.');
        }

        return $this->token = $token;
    }
}
