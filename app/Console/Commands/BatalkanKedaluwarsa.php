<?php

namespace App\Console\Commands;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\StatusPesananService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Membatalkan pesanan yang belum dibayar sampai lewat batas waktu (checklist
 * F7-12).
 *
 * Stok dikembalikan lewat StatusPesananService, bukan dengan menulis kolom
 * reserved_quantity langsung di sini. Hanya ada satu tempat yang boleh
 * menyentuh tahanan stok — kalau tidak, cepat atau lambat ada jalur yang lupa
 * melepasnya, dan barang yang sudah dibatalkan tetap tampak terikat.
 *
 * Alasan pembatalan dicatat di order_status_history supaya pesanan yang hilang
 * dari daftar tidak menjadi misteri bagi admin yang mencarinya.
 */
class BatalkanKedaluwarsa extends Command
{
    protected $signature = 'iksan:batalkan-kedaluwarsa
                            {--uji : Tampilkan yang akan dibatalkan tanpa mengubah apa pun}';

    protected $description = 'Batalkan pesanan pending yang belum dibayar sampai batas waktunya';

    public function handle(StatusPesananService $status): int
    {
        $uji = (bool) $this->option('uji');

        $daftar = Order::query()
            ->where('status', StatusPesanan::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('items')
            ->orderBy('expires_at')
            ->get();

        if ($daftar->isEmpty()) {
            $this->info('Tidak ada pesanan kedaluwarsa.');

            return self::SUCCESS;
        }

        foreach ($daftar as $order) {
            $lewat = $order->expires_at?->diffForHumans(now(), true) ?? '-';

            if ($uji) {
                $this->line(sprintf('%s · kedaluwarsa %s lalu · %d barang', $order->order_number, $lewat, $order->items->count()));

                continue;
            }

            try {
                $status->pindah(
                    $order,
                    StatusPesanan::Cancelled,
                    null,
                    'Dibatalkan otomatis: belum dibayar sampai batas waktu.',
                );
            } catch (RuntimeException|Throwable $e) {
                // Satu pesanan yang gagal tidak boleh menghentikan sisanya.
                $this->warn(sprintf('%s gagal dibatalkan: %s', $order->order_number, $e->getMessage()));
            }
        }

        $this->info(sprintf(
            $uji ? '%d pesanan kedaluwarsa (uji, tidak ada yang diubah).' : '%d pesanan dibatalkan, tahanan stok dilepas.',
            $daftar->count(),
        ));

        return self::SUCCESS;
    }
}
