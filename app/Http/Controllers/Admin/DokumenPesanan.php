<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\DokumenPdf;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dokumen cetak pesanan: resi, invoice, dan surat jalan.
 *
 * Ketiganya dirender sebagai HTML lalu diubah menjadi PDF oleh dompdf
 * (checklist F8-06, F8-07, F8-08) — bukan HTML yang baru menjadi PDF kalau
 * admin menekan "Simpan sebagai PDF" di peramban. Ukuran lembarnya karena itu
 * ditentukan di sini dan dapat diperiksa dari berkasnya.
 *
 * Resi dan surat jalan sengaja TIDAK memuat harga: dokumen itu ikut menempel
 * di paket, dan harga beli tidak perlu diketahui kurir maupun tetangga yang
 * melihat paketnya. Invoice yang memuat harga.
 *
 * Pesanan yang dibatalkan memakai tanda air SALINAN pada ketiga dokumen
 * (checklist F8-09): setelah dibatalkan, dokumen yang beredar tidak boleh lagi
 * terbaca sebagai dokumen sah.
 */
class DokumenPesanan
{
    /** Resi / label pengiriman: 100x150 mm, ukuran label yang dicetak kurir. */
    private const KERTAS_RESI = '100mm 150mm';

    private const KERTAS_A4 = 'A4';

    public function resi(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.resi', $this->data($order, denganAlamat: false)),
            self::KERTAS_RESI,
            'resi-'.$order->order_number.'.pdf',
        );
    }

    public function invoice(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.invoice', $this->data($order, denganAlamat: true)),
            self::KERTAS_A4,
            'invoice-'.$order->order_number.'.pdf',
        );
    }

    public function suratJalan(string $ulid): Response
    {
        $order = $this->pesanan($ulid);

        return DokumenPdf::dari(
            view('cetak.surat-jalan', $this->data($order, denganAlamat: true)),
            self::KERTAS_A4,
            'surat-jalan-'.$order->order_number.'.pdf',
        );
    }

    /**
     * Pratinjau HTML di peramban.
     *
     * Dipertahankan karena admin kadang hanya ingin melihat isinya sebelum
     * mencetak, dan karena peramban dapat mencetak ke printer langsung tanpa
     * mengunduh berkas lebih dulu.
     */
    /**
     * Pratinjau HTML di peramban.
     *
     * Dipertahankan karena admin kadang hanya ingin melihat isinya sebelum
     * mencetak, dan karena peramban dapat mencetak ke printer langsung tanpa
     * mengunduh berkas lebih dulu.
     *
     * Urutan parameternya mengikuti urutan pada rute ({ulid} lalu {jenis}):
     * Laravel mengisi parameter skalar secara berurutan, bukan menurut nama,
     * jadi urutan yang tertukar menukar nilainya juga — dan pesanan dicari
     * dengan kata "invoice", yang tidak pernah ketemu.
     */
    public function pratinjau(string $ulid, string $jenis): View
    {
        $order = $this->pesanan($ulid);

        abort_unless(in_array($jenis, ['resi', 'invoice', 'surat-jalan'], true), 404);

        return view('cetak.'.$jenis, $this->data($order, denganAlamat: $jenis !== 'resi'));
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

    /**
     * @return array<string, mixed>
     */
    private function data(Order $order, bool $denganAlamat): array
    {
        return [
            'order' => $order,
            'toko' => Pengaturan::instance(),
            'alamat' => $denganAlamat ? $order->alamat() : [],
            'salinan' => $order->status === StatusPesanan::Cancelled,
        ];
    }

    private function pesanan(string $ulid): Order
    {
        return Order::query()
            ->with(['items', 'user', 'payments', 'statusHistory'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }
}
