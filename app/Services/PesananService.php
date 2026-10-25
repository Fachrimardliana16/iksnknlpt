<?php

namespace App\Services;

use App\Enums\MetodePembayaran;
use App\Jobs\KirimNotifikasiPesanan;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembuatan pesanan.
 *
 * Robagai hal harus terjadi bersamaan atau tidak sama sekali:
 *   1. harga dan stok divalidasi ulang dari basis data, bukan dari keranjang;
 *   2. stok dikurangi dan reserved_quantity diisi, supaya tidak oversell;
 *   3. nomor pesanan dibuat unik;
 *   4. item disimpan sebagai snapshot, karena harga produk dapat berubah;
 *   5. riwayat status pertama dicatat.
 *
 * Seluruhnya dibungkus transaksi pendek. SQLite menulis secara berurutan, jadi
 * transaksi yang panjang akan memblokir keranjang pengguna lain.
 */
class PesananService
{
    /**
     * @param  array<string, string|null>  $data
     */
    public function buatDariKeranjang(
        Keranjang $keranjang,
        array $data,
        MetodePembayaran $metode,
        ?User $user = null,
    ): Order {
        $items = $keranjang->items();

        if ($items->isEmpty()) {
            throw new RuntimeException('Keranjang kosong.');
        }

        $pesanan = DB::transaction(function () use ($keranjang, $items, $data, $metode, $user): Order {
            $subtotal = 0.0;
            $berat = 0;
            $baris = [];

            foreach ($items as $item) {
                // Muat ulang dengan penguncian baca di dalam transaksi. Harga
                // dari keranjang tidak dipercaya.
                /** @var ProductVariant|null $varian */
                $varian = ProductVariant::query()
                    ->with('product')
                    ->whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($varian === null || ! $varian->is_active) {
                    throw new RuntimeException(
                        'Produk "'.($item->product?->judul() ?? 'tidak dikenal').'" sudah tidak dijual.'
                    );
                }

                $tersedia = $varian->stokTersedia();

                if ($tersedia < $item->quantity) {
                    throw new RuntimeException(sprintf(
                        'Stok %s tersisa %d, sedangkan keranjang meminta %d. Perbarui keranjang Anda.',
                        $varian->product->judul(),
                        $tersedia,
                        $item->quantity,
                    ));
                }

                $harga = $varian->hargaAkhir();
                $totalBaris = round($harga * $item->quantity, 2);
                $beratBaris = $varian->berat();

                $subtotal += $totalBaris;
                $berat += $beratBaris * $item->quantity;

                $baris[] = [
                    'product_id' => $varian->product_id,
                    'product_variant_id' => $varian->id,
                    'product_name' => $varian->product->judul(),
                    'variant_name' => $varian->nama(),
                    'sku' => $varian->sku_variant,
                    'unit_price' => $harga,
                    'quantity' => $item->quantity,
                    'subtotal' => $totalBaris,
                    'weight_gram' => $beratBaris,
                ];

                // Stok fisik TIDAK dikurangi di sini. Mengurangi stok fisik dan
                // menambah reserved sekaligus akan menghitung barang yang sama
                // dua kali, dan membuat stok dapat dijual lebih kecil dari yang
                // seharusnya. Stok fisik baru berkurang saat barang benar-benar
                // keluar, yaitu saat pesanan selesai (lihat PesananService::selesaikan).
                $varian->increment('reserved_quantity', $item->quantity);
            }

            $toko = Pengaturan::instance();
            $ongkir = (float) $toko->angka('shipping_flat_rate', 0);
            $persenPajak = (float) $toko->angka('tax_percent', 0);

            $kupon = $keranjang->kupon();
            $diskonBelanja = $kupon?->potonganBelanja($subtotal, $ongkir) ?? 0.0;
            $diskonOngkir = $kupon?->potonganOngkir($subtotal, $ongkir) ?? 0.0;
            $dasarPajak = max(0.0, $subtotal - $diskonBelanja);
            $pajak = round($dasarPajak * ($persenPajak / 100), 2);
            $ongkirAkhir = max(0.0, $ongkir - $diskonOngkir);
            $total = max(0.0, $dasarPajak + $pajak + $ongkirAkhir);

            $order = Order::query()->create([
                'order_number' => $this->nomorPesanan(),
                'user_id' => $user?->id,
                'guest_email' => $user === null ? $data['email'] : null,
                'guest_phone' => $user === null ? $data['telepon'] : null,
                'status' => StatusPesanan::Pending,
                'payment_status' => StatusPembayaran::Unpaid,
                'payment_method' => $metode,
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $diskonBelanja,
                'tax_amount' => $pajak,
                'shipping_cost' => $ongkirAkhir,
                'grand_total' => $total,
                'currency' => $toko->teks('paypal_currency', 'IDR'),
                'coupon_id' => $kupon?->id,
                'shipping_address_snapshot' => [
                    'label' => 'Alamat pengiriman',
                    'recipient_name' => $data['nama'],
                    'phone_number' => $data['telepon'],
                    'address_line_1' => $data['alamat1'],
                    'address_line_2' => $data['alamat2'] ?? null,
                    'city' => $data['kota'],
                    'state_province' => $data['provinsi'],
                    'postal_code' => $data['kodePos'],
                    'country' => $data['negara'] ?? 'ID',
                ],
                'shipping_courier' => $data['kurir'] ?? null,

                // Negara dalam huruf latin yang seragam. Ongkir internasional
                // dihitung manual oleh admin setelah pesanan masuk (lihat
                // catatan di bawah), jadi biayanya belum masuk di sini.
                'shipping_country' => $data['negara'] ?? 'ID',
                'shipping_currency' => $data['mataUang'] ?? 'IDR',
                'shipping_is_intl' => ! \App\Support\Internasional::dalamNegeri((string) ($data['negara'] ?? 'ID')),
                'customer_note' => $data['catatan'] ?? null,
                // Pesanan yang belum dibayar dalam 24 jam dibatalkan penjadwal,
                // dan stok dikembalikan.
                'expires_at' => now()->addDay(),
            ]);

            foreach ($baris as $b) {
                $order->items()->create($b);
            }

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => StatusPesanan::Pending->value,
                'note' => 'Pesanan dibuat dari halaman checkout.',
            ]);

            if ($kupon !== null) {
                $kupon->increment('used_count');
            }

            $keranjang->kosongkan();

            return $order->fresh(['items']);
        }, 3);

        // Notifikasi dikirim SETELAH transaksi terselesaikan.
        //
        // Pekerjaan antrean yang dikirim di dalam transaksi dapat berjalan
        // sebelum datanya benar-benar tersimpan, terutama pada antrean
        // database yang diproses proses lain. Pada antrean sinkron, ia bahkan
        // memblokir permintaan checkout sampai surel terkirim.
        if (\App\Support\SakelarNotifikasi::kirimAktif('kirim_saat_dibuat')) {
            KirimNotifikasiPesanan::dispatch($pesanan->id, KirimNotifikasiPesanan::KEJADIAN_DIBUAT);
        }

        return $pesanan;
    }

    /**
     * Nomor publik. Acak pada bagian akhir, sehingga nomor tidak berurutan dan
     * tidak dapat dipakai untuk menebak jumlah pesanan toko.
     */
    public function nomorPesanan(): string
    {
        $prefiks = Pengaturan::instance()->teks('order_prefix', config('iksan.order_prefix', 'IK'));
        $tanggal = now()->format('Ymd');

        for ($i = 0; $i < 10; $i++) {
            $nomor = sprintf('%s-%s-%s', $prefiks, $tanggal, Str::upper(Str::random(6)));

            if (! Order::query()->where('order_number', $nomor)->exists()) {
                return $nomor;
            }
        }

        return sprintf('%s-%s-%s', $prefiks, $tanggal, (string) Str::ulid());
    }
}
