<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\DokumenPdf;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report penjualan dengan filter waktu, keluar sebagai CSV atau PDF.
 *
 * Ekspor lama (DokumenPesanan::eksporCsv) tidak punya batas waktu sama
 * sekali: membukanya selalu mengirim seluruh riwayat pesanan. Untuk toko
 * dengan dua ribu pesanan, berkas itu berisi satu setengah tahun data
 * sementara yang dibutuhkan cuma bulan ini.
 *
 * Rentangnya diterima sebagai tanggal awal dan akhir, bukan "7 hari
 * terakhir" sebagai pilihan tetap — admin yang menutup laporan akhir bulan
 * butuh rentang yang sama setiap kali, dan menuliskannya sekali lebih cepat
 * daripada memilih dari daftar.
 */
class LaporanPenjualan
{
    /**
     * Batas rentang, dipakai untuk menolak permintaan yang jelas salah.
     *
     * Sengaja LEBAR (sepuluh tahun) dan TIDAK memangkas rentang yang diminta.
     * Versi pertama memotong `sampai` menjadi `mulai + 366 hari`, dan itu
     * menghasilkan laporan yang terlihat berhasil tetapi berisi nol baris:
     * rentang 2020-2026 dipotong menjadi 2020-2021, seluruh pesanan 2026
     * hilang tanpa satu pun pesan galat. Admin membaca berkas itu sebagai
     * "tidak ada penjualan", bukan "rentang saya dipotong".
     *
     * Batas yang tetap ada hanya menolak tanggal yang bukan tanggal, dan
     * menolak rentang di luar sepuluh tahun — permintaan yang jelas salah
     * ketik, bukan laporan yang sah.
     */
    private const MAKS_HARI = 3650;

    public function csv(Request $request): StreamedResponse
    {
        [$mulai, $sampai] = $this->rentang($request);

        $pesanan = $this->pesanan($mulai, $sampai, $request);

        return $this->unduhCsv($pesanan, $this->labelRentang($mulai, $sampai));
    }

    public function pdf(Request $request): Response
    {
        [$mulai, $sampai] = $this->rentang($request);

        $pesanan = $this->pesanan($mulai, $sampai, $request);

        $toko = Pengaturan::instance();

        return DokumenPdf::dari(
            view('cetak.laporan-penjualan', [
                'pesanan' => $pesanan,
                'mulai' => $mulai,
                'sampai' => $sampai,
                'ringkas' => $this->ringkasan($pesanan),
                'toko' => $toko,
            ]),
            'A4',
            'laporan-penjualan-'.$mulai->format('Ymd').'-'.$sampai->format('Ymd').'.pdf',
        );
    }

    /**
     * Rentang tanggal yang sudah dipastikan wajar.
     *
     * Tanggal yang tidak dapat dibaca dianggap "awal bulan ini" — bukan
     * galat. Satu parameter yang salah ketik pada tautan ekspor seharusnya
     * tidak membuat halaman laporan mati, apalagi membuat admin menyangka
     * datanya hilang.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function rentang(Request $request): array
    {
        $mulai = $this->tanggal($request->query('mulai')) ?? now()->startOfMonth();
        $sampai = $this->tanggal($request->query('sampai')) ?? now();

        if ($sampai->lt($mulai)) {
            [$mulai, $sampai] = [$sampai, $mulai];
        }

        // Rentang yang jelas salah ketik ditolak dengan pesan, bukan
        // dipangkas diam-diam. Lihat MAKS_HARI untuk alasannya.
        if ($mulai->diffInDays($sampai) > self::MAKS_HARI) {
            abort(422, 'Rentang laporan terlalu panjang. Maksimal sepuluh tahun.');
        }

        return [$mulai->startOfDay(), $sampai->endOfDay()];
    }

    private function tanggal(mixed $nilai): ?\Illuminate\Support\Carbon
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    private function labelRentang(\Illuminate\Support\Carbon $mulai, \Illuminate\Support\Carbon $sampai): string
    {
        return $mulai->format('Ymd').'-'.$sampai->format('Ymd');
    }

    /**
     * Pesanan dalam rentang, mengikuti saringan status yang sedang aktif.
     *
     * Batas 5000 baris dipertahankan: ekspor yang lebih besar dari itu lebih
     * baik diminta per periode daripada membuat proses ekspor menahan memori
     * sampai batas PHP.
     */
    private function pesanan(\Illuminate\Support\Carbon $mulai, \Illuminate\Support\Carbon $sampai, Request $request)
    {
        $status = (string) $request->query('status', 'semua');
        $bayar = (string) $request->query('bayar', 'semua');
        $cari = trim((string) $request->query('cari', ''));

        return Order::query()
            ->with(['items', 'user'])
            ->whereBetween('created_at', [$mulai, $sampai])
            ->when($status !== 'semua' && $status !== '' && in_array($status, StatusPesanan::values(), true),
                fn ($q) => $q->where('status', $status))
            ->when($bayar !== 'semua' && $bayar !== '' && in_array($bayar, StatusPembayaran::values(), true),
                fn ($q) => $q->where('payment_status', $bayar))
            ->when($cari !== '', fn ($q) => $q->where('order_number', 'like', '%'.$cari.'%'))
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $pesanan
     * @return array<string, mixed>
     */
    private function ringkasan($pesanan): array
    {
        $selesai = $pesanan->where('status', StatusPesanan::Completed)->count();

        return [
            'jumlah' => $pesanan->count(),
            'selesai' => $selesai,
            'batal' => $pesanan->where('status', StatusPesanan::Cancelled)->count(),
            'pendapatan' => (float) $pesanan
                ->whereIn('status', [StatusPesanan::Paid, StatusPesanan::Processing, StatusPesanan::Shipped, StatusPesanan::Completed])
                ->sum('grand_total'),
            'nilaiBelumMasuk' => (float) $pesanan
                ->where('payment_status', '!=', StatusPembayaran::Paid->value)
                ->whereNotIn('status', [StatusPesanan::Cancelled->value])
                ->sum('grand_total'),
            'rataRata' => $pesanan->count() > 0
                ? (float) $pesanan->avg('grand_total')
                : 0.0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Order>  $pesanan
     */
    private function unduhCsv($pesanan, string $labelRentang): StreamedResponse
    {
        $namaBerkas = 'laporan-penjualan-'.$labelRentang.'.csv';

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
}
