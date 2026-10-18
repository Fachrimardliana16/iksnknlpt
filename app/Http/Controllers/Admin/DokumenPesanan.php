<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Dokumen cetak pesanan: resi, invoice, dan surat jalan.
 *
 * Ketiganya memakai halaman penuh tanpa panel admin — saat mencetak, tata
 * letak navigasi hanya memakan kertas. Tautannya dibuka di tab baru sehingga
 * admin tidak kehilangan posisi kerjanya di panel.
 *
 * Resi dan surat jalan sengaja TIDAK memuat harga: dokumen itu ikut menempel
 * di paket, dan harga beli tidak perlu diketahui kurir maupun tetangga yang
 * melihat paketnya. Invoice yang memuat harga.
 */
class DokumenPesanan
{
    public function resi(string $ulid): View
    {
        return view('cetak.resi', [
            'order' => $this->pesanan($ulid),
            'toko' => Pengaturan::instance(),
            'alamat' => [],
        ]);
    }

    public function invoice(string $ulid): View
    {
        $order = $this->pesanan($ulid);

        return view('cetak.invoice', [
            'order' => $order,
            'toko' => Pengaturan::instance(),
            'alamat' => $order->alamat(),
        ]);
    }

    public function suratJalan(string $ulid): View
    {
        $order = $this->pesanan($ulid);

        return view('cetak.surat-jalan', [
            'order' => $order,
            'toko' => Pengaturan::instance(),
            'alamat' => $order->alamat(),
        ]);
    }

    /**
     * Ekspor CSV daftar pesanan mengikuti saringan yang sedang aktif.
     *
     * Dipakai untuk rekap penjualan di luar aplikasi. Kutipan ganda pada nilai
     * digandakan supaya pemisah kolom tidak rusak saat nama produk memuat
     * karakter itu.
     */
    public function eksporCsv(Request $request)
    {
        $status = $request->query('status', 'semua');
        $bayar = $request->query('bayar', 'semua');
        $cari = trim((string) $request->query('cari', ''));

        $pesanan = Order::query()
            ->with(['items', 'user'])
            ->when($status !== 'semua' && $status !== '', fn ($q) => $q->where('status', $status))
            ->when($bayar !== 'semua' && $bayar !== '', fn ($q) => $q->where('payment_status', $bayar))
            ->when($cari !== '', fn ($q) => $q->where('order_number', 'like', '%'.$cari.'%'))
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        $namaBerkas = 'pesanan-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($pesanan): void {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel di Windows membaca aksen dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'Nomor', 'Tanggal', 'Status', 'Pembayaran', 'Metode',
                'Pembeli', 'Kontak', 'Kota', 'Jumlah barang', 'Subtotal',
                'Diskon', 'Ongkir', 'Pajak', 'Total', 'Resi',
            ]);

            foreach ($pesanan as $o) {
                $alamat = $o->alamat();

                fputcsv($keluaran, [
                    $o->order_number,
                    $o->created_at->format('Y-m-d H:i'),
                    $o->status->label(),
                    $o->payment_status->label(),
                    $o->payment_method?->label() ?? '',
                    $o->namaPenerima(),
                    $o->emailPenerima() ?? $o->user?->email ?? '',
                    $alamat['city'] ?? '',
                    $o->jumlahItem(),
                    number_format((float) $o->subtotal, 2, '.', ''),
                    number_format((float) $o->discount_amount, 2, '.', ''),
                    number_format((float) $o->shipping_cost, 2, '.', ''),
                    number_format((float) $o->tax_amount, 2, '.', ''),
                    number_format((float) $o->grand_total, 2, '.', ''),
                    $o->tracking_number ?? '',
                ]);
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function pesanan(string $ulid): Order
    {
        return Order::query()
            ->with(['items', 'user', 'payments', 'statusHistory'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }
}
