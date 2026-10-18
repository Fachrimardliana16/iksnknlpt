<?php

namespace Database\Seeders;

use App\Enums\MetodePembayaran;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 30 pesanan contoh tersebar pada seluruh status dan 90 hari terakhir.
 *
 * Distribusi status diambil dari daftar tetap, bukan acak, supaya jumlah per
 * status dapat diperiksa ulang persis.
 */
class OrderSeeder extends Seeder
{
    /** Status per urutan pesanan, deterministik. */
    private const STATUS = [
        StatusPesanan::Pending, StatusPesanan::Pending, StatusPesanan::Pending,
        StatusPesanan::Pending, StatusPesanan::Pending,
        StatusPesanan::Paid, StatusPesanan::Paid, StatusPesanan::Paid,
        StatusPesanan::Paid, StatusPesanan::Paid,
        StatusPesanan::Processing, StatusPesanan::Processing, StatusPesanan::Processing,
        StatusPesanan::Processing, StatusPesanan::Processing,
        StatusPesanan::Shipped, StatusPesanan::Shipped, StatusPesanan::Shipped,
        StatusPesanan::Shipped, StatusPesanan::Shipped,
        StatusPesanan::Completed, StatusPesanan::Completed, StatusPesanan::Completed,
        StatusPesanan::Completed, StatusPesanan::Completed, StatusPesanan::Completed,
        StatusPesanan::Completed,
        StatusPesanan::Cancelled, StatusPesanan::Cancelled, StatusPesanan::Cancelled,
    ];

    private const KURIR = [
        ['JNE', 'REG'],
        ['J&T Express', 'EZ'],
        ['SiCepat', 'REG'],
        ['Pos Indonesia', 'Biasa'],
    ];

    public function run(): void
    {
        $produk = Product::query()->with('variants')->get();

        if ($produk->isEmpty()) {
            return;
        }

        $pelanggan = User::query()->where('role', 'customer')->get();
        $kupon = Coupon::query()->where('code', 'IKSAN10')->first();

        foreach (self::STATUS as $i => $status) {
            $hariLalu = 89 - (int) round($i * (89 / max(1, count(self::STATUS) - 1)));
            $dibuat = now()->startOfDay()->subDays($hariLalu)->setTime(9 + ($i % 9), ($i * 7) % 60);

            $nomor = sprintf('IK-%s-%04d', $dibuat->format('Ymd'), $i + 1);

            if (Order::query()->where('order_number', $nomor)->exists()) {
                continue;
            }

            $tamu = $i % 4 === 0;
            $user = $tamu ? null : $pelanggan->get($i % max(1, $pelanggan->count()));

            $itemCount = 1 + ($i % 4);

            [$subtotal, $itemBaris, $berat] = $this->buatItem($produk, $itemCount, $i);

            $ongkir = $subtotal >= 1_000_000 ? 0 : 25_000 + (($i % 5) * 5_000);
            $diskon = ($status !== StatusPesanan::Cancelled && $i % 5 === 0 && $kupon !== null)
                ? min(500_000, round($subtotal * 0.10, 2))
                : 0.0;

            $pajak = 0.0;
            $total = max(0.0, $subtotal - $diskon + $pajak + $ongkir);

            $alamat = [
                'label' => $tamu ? 'Alamat pengiriman' : 'Rumah',
                'recipient_name' => $user?->name ?? 'Pembeli Tamu '.($i + 1),
                'phone_number' => $user?->phone_number ?? '62812999'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'address_line_1' => 'Jl. Contoh No. '.($i + 10),
                'address_line_2' => $i % 3 === 0 ? 'RT 04 / RW 02' : null,
                'city' => ['Sleman', 'Yogyakarta', 'Bantul', 'Klaten'][$i % 4],
                'state_province' => 'DI Yogyakarta',
                'postal_code' => (string) (55000 + $i),
            ];

            $pesanan = Order::query()->create([
                'order_number' => $nomor,
                'user_id' => $user?->id,
                'guest_email' => $tamu ? 'tamu'.$i.'@example.test' : null,
                'guest_phone' => $tamu ? $alamat['phone_number'] : null,
                'status' => $status,
                'payment_status' => $this->statusPembayaran($status),
                'payment_method' => $status === StatusPesanan::Cancelled && $i % 2 === 0
                    ? null
                    : ($i % 3 === 0 ? MetodePembayaran::Manual : MetodePembayaran::Paypal),
                'subtotal' => $subtotal,
                'discount_amount' => $diskon,
                'tax_amount' => $pajak,
                'shipping_cost' => $ongkir,
                'grand_total' => $total,
                'currency' => 'IDR',
                'coupon_id' => $diskon > 0 ? $kupon?->id : null,
                'shipping_address_snapshot' => $alamat,
                'shipping_courier' => $status === StatusPesanan::Shipped || $status === StatusPesanan::Completed
                    ? self::KURIR[$i % 4][0] : null,
                'shipping_service' => $status === StatusPesanan::Shipped || $status === StatusPesanan::Completed
                    ? self::KURIR[$i % 4][1] : null,
                'tracking_number' => $status === StatusPesanan::Completed
                    ? strtoupper(substr(self::KURIR[$i % 4][0], 0, 2)).'00'.str_pad((string) ($i * 137 + 1000), 8, '0', STR_PAD_LEFT)
                    : null,
                'customer_note' => $i % 7 === 0 ? 'Tolong dikemas rapi, untuk hadiah.' : null,
                'paid_at' => in_array($status, [StatusPesanan::Paid, StatusPesanan::Processing, StatusPesanan::Shipped, StatusPesanan::Completed], true)
                    ? $dibuat->copy()->addHours(2) : null,
                'shipped_at' => in_array($status, [StatusPesanan::Shipped, StatusPesanan::Completed], true)
                    ? $dibuat->copy()->addDays(1) : null,
                'completed_at' => $status === StatusPesanan::Completed
                    ? $dibuat->copy()->addDays(4) : null,
                'cancelled_at' => $status === StatusPesanan::Cancelled
                    ? $dibuat->copy()->addDays(1) : null,
                'expires_at' => $status === StatusPesanan::Pending
                    ? $dibuat->copy()->addDay() : null,
                'created_at' => $dibuat,
                'updated_at' => $dibuat,
            ]);

            foreach ($itemBaris as $baris) {
                $pesanan->items()->create($baris);

                // Stok yang ditahan harus dicatat.
                //
                // Aturannya sudah ditetapkan di StatusPesananService: pesanan
                // pada status pending, paid, processing, dan shipped MENAHAN
                // stok lewat reserved_quantity. Seeder sebelumnya tidak pernah
                // mengisi kolom itu, sehingga data contoh menampilkan stok yang
                // seolah-olah masih bebas padahal barangnya sudah terikat
                // pesanan — persis keadaan yang memicu oversell.
                //
                // Harga dan jumlah tetap memakai snapshot pada baris pesanan,
                // hanya kolom tahanan yang disesuaikan.
                if ($status->menahanStok() && $baris['product_variant_id'] !== null) {
                    ProductVariant::query()
                        ->whereKey($baris['product_variant_id'])
                        ->whereRaw('(stock_quantity - reserved_quantity) >= ?', [$baris['quantity']])
                        ->increment('reserved_quantity', $baris['quantity']);
                }
            }

            $this->catatRiwayat($pesanan, $status, $dibuat);
            $this->catatPembayaran($pesanan, $status, $dibuat, $user);
        }

        $this->command?->info(sprintf(
            'OrderSeeder: %d pesanan, %d item, %d riwayat, %d pembayaran.',
            Order::query()->count(),
            OrderItem::query()->count(),
            OrderStatusHistory::query()->count(),
            Payment::query()->count(),
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Product>  $produk
     * @return array{0: float, 1: array<int, array<string, mixed>>, 2: int}
     */
    private function buatItem($produk, int $jumlah, int $seed): array
    {
        $subtotal = 0.0;
        $berat = 0;
        $baris = [];

        for ($n = 0; $n < $jumlah; $n++) {
            /** @var Product $p */
            $p = $produk->get(($seed * 3 + $n * 5) % $produk->count());

            /** @var ProductVariant|null $v */
            $v = $p->variants->where('is_active', true)->sortBy('id')->values()->get($n % max(1, $p->variants->count()));

            if ($v === null) {
                continue;
            }

            $qty = 1 + (($seed + $n) % 2);
            $harga = (float) $p->base_price + (float) $v->price_adjustment;
            $harga = max(0.0, $harga);
            $totalBaris = round($harga * $qty, 2);
            $beratBaris = ($v->weight_gram ?? $p->weight_gram);

            $berat += $beratBaris * $qty;
            $subtotal += $totalBaris;

            $baris[] = [
                'product_id' => $p->id,
                'product_variant_id' => $v->id,
                'product_name' => $p->title_id,
                'variant_name' => $v->variant_name_id,
                'sku' => $v->sku_variant,
                'unit_price' => $harga,
                'quantity' => $qty,
                'subtotal' => $totalBaris,
                'weight_gram' => $beratBaris,
            ];
        }

        return [round($subtotal, 2), $baris, $berat];
    }

    private function statusPembayaran(StatusPesanan $status): StatusPembayaran
    {
        return match ($status) {
            StatusPesanan::Pending => StatusPembayaran::Unpaid,
            StatusPesanan::Cancelled => StatusPembayaran::Failed,
            StatusPesanan::Paid, StatusPesanan::Processing => StatusPembayaran::Paid,
            StatusPesanan::Shipped, StatusPesanan::Completed => StatusPembayaran::Paid,
        };
    }

    private function catatRiwayat(Order $pesanan, StatusPesanan $status, \Illuminate\Support\Carbon $dibuat): void
    {
        $alur = match ($status) {
            StatusPesanan::Pending => [StatusPesanan::Pending],
            StatusPesanan::Paid => [StatusPesanan::Pending, StatusPesanan::Paid],
            StatusPesanan::Processing => [StatusPesanan::Pending, StatusPesanan::Paid, StatusPesanan::Processing],
            StatusPesanan::Shipped => [StatusPesanan::Pending, StatusPesanan::Paid, StatusPesanan::Processing, StatusPesanan::Shipped],
            StatusPesanan::Completed => [StatusPesanan::Pending, StatusPesanan::Paid, StatusPesanan::Processing, StatusPesanan::Shipped, StatusPesanan::Completed],
            StatusPesanan::Cancelled => [StatusPesanan::Pending, StatusPesanan::Cancelled],
        };

        $sebelum = null;

        foreach ($alur as $i => $tahap) {
            OrderStatusHistory::query()->create([
                'order_id' => $pesanan->id,
                'from_status' => $sebelum?->value,
                'to_status' => $tahap->value,
                'note' => match ($tahap) {
                    StatusPesanan::Pending => 'Pesanan dibuat dari halaman checkout.',
                    StatusPesanan::Paid => 'Pembayaran diterima.',
                    StatusPesanan::Processing => 'Pesanan sedang disiapkan di bengkel.',
                    StatusPesanan::Shipped => 'Paket diserahkan ke kurir.',
                    StatusPesanan::Completed => 'Paket diterima pelanggan.',
                    StatusPesanan::Cancelled => 'Dibatalkan karena pembayaran tidak diterima dalam 24 jam.',
                },
                'created_at' => $dibuat->copy()->addHours($i * 6),
            ]);

            $sebelum = $tahap;
        }
    }

    private function catatPembayaran(Order $pesanan, StatusPesanan $status, \Illuminate\Support\Carbon $dibuat, ?User $user): void
    {
        if ($pesanan->payment_status === StatusPembayaran::Unpaid || $pesanan->payment_method === null) {
            return;
        }

        Payment::query()->create([
            'order_id' => $pesanan->id,
            'provider' => $pesanan->payment_method,
            'provider_transaction_id' => $pesanan->payment_method === MetodePembayaran::Paypal
                ? 'PAYPAL-DEMO-'.$pesanan->order_number
                : null,
            'provider_status' => match ($pesanan->payment_status) {
                StatusPembayaran::Paid => 'COMPLETED',
                StatusPembayaran::Failed => 'FAILED',
                default => 'PENDING',
            },
            'amount' => $pesanan->grand_total,
            'currency' => 'IDR',
            'proof_path' => $pesanan->payment_method === MetodePembayaran::Manual
                ? null : null,
            'payload_json' => ['sumber' => 'seeder', 'nomor_pesanan' => $pesanan->order_number],
            'verified_by' => $pesanan->payment_status === StatusPembayaran::Paid ? $user?->id : null,
            'verified_at' => $pesanan->paid_at,
            'created_at' => $dibuat->copy()->addHours(1),
            'updated_at' => $dibuat->copy()->addHours(1),
        ]);
    }
}
