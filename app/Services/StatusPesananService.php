<?php

namespace App\Services;

use App\Enums\StatusPesanan;
use App\Jobs\KirimNotifikasiPesanan;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perpindahan status pesanan.
 *
 * Setiap perubahan status yang menyentuh stok dilakukan di sini, bukan di
 * komponen Livewire, supaya pemulihan dan pengurangan stok selalu berjalan
 * bersama perubahan status. Mengubah status langsung lewat model akan membuat
 * reserved_quantity dan stock_quantity tidak lagi seimbang.
 */
class StatusPesananService
{
    /**
     * Pindahkan pesanan ke status baru.
     *
     * - Dibayar: tidak menyentuh stok, hanya menandai pembayaran.
     * - Selesai: stok fisik dikurangi dan reserved dilepas, karena barang
     *   benar-benar keluar dari gudang.
     * - Dibatalkan: seluruh reserved dilepas, stok kembali dapat dijual.
     */
    public function pindah(
        Order $order,
        StatusPesanan $tujuan,
        ?User $oleh = null,
        ?string $catatan = null,
    ): Order {
        if (! $order->status->bisaKe($tujuan)) {
            throw new RuntimeException(sprintf(
                'Status tidak dapat berpindah dari %s ke %s.',
                $order->status->value,
                $tujuan->value,
            ));
        }

        $hasil = DB::transaction(function () use ($order, $tujuan, $oleh, $catatan): Order {
            $asal = $order->status;

            // Varian dimuat bersama produknya.
            //
            // Sejak hargaAkhir() mewajibkan relasi product terpasang, jalur
            // apa pun yang menyentuh varian harus memuatnya. Dilakukan sekali
            // di sini — bukan per baris di dalam perulangan — supaya tetap satu
            // kueri meski pesanannya berisi banyak barang.
            $order->load('items.variant.product');

            match ($tujuan) {
                StatusPesanan::Completed => $this->selesaikanStok($order),
                StatusPesanan::Cancelled => $this->lepaskanStok($order),
                default => null,
            };

            $order->status = $tujuan;

            if ($tujuan === StatusPesanan::Paid) {
                $order->payment_status = \App\Enums\StatusPembayaran::Paid;
                $order->paid_at = $order->paid_at ?? now();
            }

            if ($tujuan === StatusPesanan::Shipped) {
                $order->shipped_at = $order->shipped_at ?? now();
            }

            if ($tujuan === StatusPesanan::Completed) {
                $order->completed_at = now();
            }

            if ($tujuan === StatusPesanan::Cancelled) {
                $order->cancelled_at = now();
            }

            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $asal->value,
                'to_status' => $tujuan->value,
                'note' => $catatan,
                'changed_by' => $oleh?->id,
            ]);

            return $order->fresh(['items', 'statusHistory']);
        }, 3);

        // Notifikasi hanya untuk kejadian yang berarti bagi pembeli. Dikirim
        // setelah transaksi selesai: pekerjaan antrean di dalam transaksi dapat
        // berjalan sebelum datanya tersimpan.
        if ($tujuan === StatusPesanan::Shipped && config('iksan.notifikasi.kirim_saat_dikirim', true)) {
            KirimNotifikasiPesanan::dispatch($hasil->id, KirimNotifikasiPesanan::KEJADIAN_DIKIRIM);
        }

        return $hasil;
    }

    /**
     * Barang benar-benar keluar. Stok fisik berkurang, dan tahanan dilepas
     * dengan jumlah yang sama.
     */
    private function selesaikanStok(Order $order): void
    {
        foreach ($order->items as $item) {
            $varian = $item->variant;

            if ($varian === null) {
                continue;
            }

            $varian->increment('stock_quantity', -$item->quantity);
            $varian->decrement('reserved_quantity', $item->quantity);

            // Jaga-jaga terhadap pembulatan atau data lama: nilai tidak boleh
            // menjadi negatif.
            $varian->refresh();
            $varian->reserved_quantity = max(0, $varian->reserved_quantity);
            $varian->stock_quantity = max(0, $varian->stock_quantity);
            $varian->save();
        }
    }

    /** Pesanan batal: tahanan dilepas, stok fisik tidak pernah tersentuh. */
    private function lepaskanStok(Order $order): void
    {
        foreach ($order->items as $item) {
            $varian = $item->variant;

            if ($varian === null) {
                continue;
            }

            $varian->decrement('reserved_quantity', $item->quantity);
            $varian->refresh();
            $varian->reserved_quantity = max(0, $varian->reserved_quantity);
            $varian->save();
        }
    }
}
