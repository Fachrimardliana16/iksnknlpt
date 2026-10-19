<?php

namespace App\Livewire\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Services\StatusPesananService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Detail satu pesanan.
 *
 * Di sinilah pesanan benar-benar diproses: status dipindahkan, nomor resi
 * diisi, lalu dokumennya dicetak. Seluruh perpindahan status melewati
 * StatusPesananService supaya stok fisik dan stok tertahan tetap seimbang —
 * mengubah kolom status langsung dari sini akan merusak keduanya.
 */
#[Layout('layouts.admin')]
class PesananDetail extends Component
{
    /** Alasan penolakan bukti transfer, diisi admin sebelum menolak. */
    public string $alasanTolak = '';

    public string $ulid = '';

    /** Status tujuan yang dipilih admin, belum diterapkan. */
    public string $statusTujuan = '';

    public string $catatanStatus = '';

    /** Data pengiriman yang dapat diisi admin. */
    public string $kurir = '';

    public string $layanan = '';

    public string $nomorResi = '';

    public string $catatanAdmin = '';

    public function mount(string $ulid): void
    {
        $order = $this->cari($ulid);

        $this->ulid = $order->ulid;
        $this->kurir = $order->shipping_courier ?? '';
        $this->layanan = $order->shipping_service ?? '';
        $this->nomorResi = $order->tracking_number ?? '';
        $this->catatanAdmin = $order->admin_note ?? '';
    }

    private function cari(string $ulid): Order
    {
        return Order::query()
            ->with(['items.variant', 'user', 'statusHistory', 'payments', 'coupon'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    /** Pindahkan status pesanan ke tujuan yang dipilih. */
    public function pindahStatus(StatusPesananService $layanan): void
    {
        $order = $this->cari($this->ulid);

        $tujuan = StatusPesanan::tryFrom($this->statusTujuan);

        if ($tujuan === null) {
            session()->flash('galat-admin', 'Pilih status tujuan lebih dulu.');

            return;
        }

        try {
            $layanan->pindah(
                $order,
                $tujuan,
                auth()->user(),
                filled($this->catatanStatus) ? $this->catatanStatus : null,
            );

            $this->catatanStatus = '';
            $this->statusTujuan = '';

            session()->flash('pesan-admin', 'Status berpindah ke '.$tujuan->label().'.');
        } catch (RuntimeException $e) {
            session()->flash('galat-admin', $e->getMessage());
        }
    }

    /** Simpan data pengiriman tanpa mengubah status. */
    public function simpanPengiriman(): void
    {
        $this->validate([
            'kurir' => ['nullable', 'string', 'max:60'],
            'layanan' => ['nullable', 'string', 'max:60'],
            'nomorResi' => ['nullable', 'string', 'max:60'],
            'catatanAdmin' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'kurir' => 'Kurir',
            'layanan' => 'Layanan',
            'nomorResi' => 'Nomor resi',
            'catatanAdmin' => 'Catatan admin',
        ]);

        $order = $this->cari($this->ulid);

        $order->update([
            'shipping_courier' => filled($this->kurir) ? $this->kurir : null,
            'shipping_service' => filled($this->layanan) ? $this->layanan : null,
            'tracking_number' => filled($this->nomorResi) ? $this->nomorResi : null,
            'admin_note' => filled($this->catatanAdmin) ? $this->catatanAdmin : null,
        ]);

        session()->flash('pesan-admin', 'Data pengiriman disimpan.');
    }

    /**
     * Memverifikasi bukti transfer dan menandai pesanan lunas.
     *
     * Perpindahan status diserahkan kepada PembayaranManual, yang meneruskannya
     * ke StatusPesananService. Menulis kolom status di sini akan melewati
     * pengelolaan stok: pesanan lunas masih menahan stok, dan melewati service
     * membuat stok fisik berkurang dua kali atau tidak sama sekali.
     */
    public function verifikasiPembayaran(int $pembayaranId): void
    {
        $bayar = \App\Models\Payment::query()
            ->where('order_id', $this->cari($this->ulid)->id)
            ->findOrFail($pembayaranId);

        try {
            app(\App\Services\Pembayaran\PembayaranManual::class)
                ->verifikasi($bayar, auth()->id());

            session()->flash('pesan-admin', 'Pembayaran diverifikasi. Pesanan ditandai lunas.');
        } catch (\Throwable $e) {
            $this->addError('pembayaran', 'Gagal memverifikasi: '.$e->getMessage());

            return;
        }

        $this->redirectRoute('admin.orders.show', ['ulid' => $this->ulid], navigate: true);
    }

    public function tolakPembayaran(int $pembayaranId): void
    {
        if (trim($this->alasanTolak) === '') {
            $this->addError('alasanTolak', 'Alasan penolakan wajib diisi supaya pembeli tahu apa yang harus diperbaiki.');

            return;
        }

        $bayar = \App\Models\Payment::query()
            ->where('order_id', $this->cari($this->ulid)->id)
            ->findOrFail($pembayaranId);

        try {
            app(\App\Services\Pembayaran\PembayaranManual::class)
                ->tolak($bayar, $this->alasanTolak, auth()->id());

            session()->flash('pesan-admin', 'Bukti ditolak. Pembeli dapat mengunggah ulang.');
        } catch (\Throwable $e) {
            $this->addError('pembayaran', 'Gagal menolak: '.$e->getMessage());

            return;
        }

        $this->alasanTolak = '';
        $this->redirectRoute('admin.orders.show', ['ulid' => $this->ulid], navigate: true);
    }

    public function render(): View
    {
        $order = $this->cari($this->ulid);

        return view('livewire.admin.pesanan-detail', [
            'judul' => 'Pesanan '.$order->order_number,
            'bagian' => 'Penjualan',
            'order' => $order,
            'alamat' => $order->alamat(),
            'tujuanSah' => $order->status->transisiSah(),
        ])->layout('layouts.admin', ['judul' => 'Pesanan '.$order->order_number, 'bagian' => 'Penjualan']);
    }
}
