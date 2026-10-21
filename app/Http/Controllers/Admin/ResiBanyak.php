<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPesanan;
use App\Models\Order;
use App\Support\CatatanAktivitas;
use App\Support\DokumenPdf;
use App\Support\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Cetak resi untuk BANYAK pesanan sekaligus.
 *
 * Rute resi tunggal sudah ada (DokumenPesanan::resi). Yang belum: kurir datang
 * pukul sembilan, ada dua puluh paket, dan admin harus menekan dua puluh tautan
 * "resi", dua puluh unduhan, lalu menempel dua puluh label. Skrip ini
 * menyatukan semuanya menjadi satu unduhan.
 *
 * Bentuk hasilnya: SATU PDF, dua puluh label 100x150 mm berturut-turut, satu
 * halaman per label (bukan 4-per-A4). Alasannya format yang diminta: label
 * dicetak pada printer label, dan satu label meleset dari kotaknya berarti
 * menempel di paket yang salah.
 *
 * Yang dicatat: `orders.printed_at` (kolom yang sudah disiapkan) dan catatan
 * aktivitas per pesanan, sehingga admin dapat melihat mana yang sudah dicetak
 * tanpa harus mengingat.
 */
class ResiBanyak
{
    /** Bentuk yang diterima: satu atau beberapa ULID, dipisah koma. */
    private const KERTAS = '100mm 150mm';

    /**
     * Batas jumlah label dalam satu berkas.
     *
     * Publik karena dipakai di dua tempat: controller memakainya untuk menolak,
     * dan app.js memakainya untuk memberi tahu admin SEBELUM berkas gagal
     * dibuat. Angka yang diulang di dua tempat harus punya satu sumber.
     */
    public const BATAS = 100;

    /**
     * Return type harus Response|RedirectResponse, bukan Response saja.
     *
     * Pesan galat di halaman daftar pesanan dibuat dengan back(), yang
     * mengembalikan RedirectResponse. Menyatakan Response saja membuat PHP
     * melempar TypeError — galat 500 — padahal yang dimaksud justru
     * mengembalikan pesan yang bisa dibaca admin.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $bersih = $request->validate([
            // "ulid" adalah nama yang dipakai rute maupun tombol di daftar;
            // satu untuk satu label, dipisah koma untuk banyak.
            'ulid' => ['required', 'string'],
        ], [
            'ulid.required' => 'Tidak ada pesanan yang dipilih untuk dicetak.',
        ]);

        $kunci = array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $bersih['ulid'])),
            fn (string $u): bool => $u !== '',
        )));

        if ($kunci === []) {
            return back()->with('galat-admin', 'Tidak ada pesanan yang dipilih untuk dicetak.');
        }

        if (count($kunci) > self::BATAS) {
            return back()->with(
                'galat-admin',
                'Maksimal '.self::BATAS.' label sekali cetak. Pilih lebih sedikit, atau cetak per halaman.'
            );
        }

        // whereIn dengan daftar tidak dikenal TIDAK menghasilkan galat SQL,
        // tetapi menghubungkan ke halaman 500 di tengah proses: dompdf masih
        // dipanggil dengan koleksi kosong dan melempar berkas kosong. Dijaga
        // lebih dulu, dengan jawaban yang bisa dibaca admin.
        //
        // Urutan label MENGIKUTI urutan ULID yang dikirim, bukan urutan basis
        // data. Admin memilih pesanan satu per satu menurut tumpukan paket di
        // mejanya; kalau hasilnya diurutkan ulang menurut id, label tercetak
        // terbalik dan ia menempelkannya pada paket yang salah.
        //
        // CASE WHEN hanya mengelompokkan "cocok" di atas "tidak cocok" — ia
        // tidak mempertahankan urutan di dalam kelompok. Yang mempertahankan
        // urutan adalah nilai CASE itu sendiri, jadi setiap posisi diberi
        // nomor posisinya.
        $pesanan = Order::query()
            ->with(['items', 'user'])
            ->whereIn('ulid', $kunci)
            ->get()
            // Urutan dipulihkan DI PHP, bukan di SQL.
            //
            // Urutan label harus mengikuti urutan ULID yang dikirim: admin
            // memilih pesanan satu per satu menurut tumpukan paket di mejanya,
            // dan label yang tercetak terbalik membuatnya menempel di paket
            // yang salah.
            //
            // Bukan karena SQL tidak bisa — "case when ulid = ? then 0 ..."
            // bisa. Tapi setiap jalan itu mengikat ULID sebagai literal, dan
            // ULID memuat huruf yang harus dikutip; binding yang dijalankan
            // lewat orderByRaw dijalankan sebagai teks polos dan menghasilkan
            // "syntax error near ','" (terbukti). Koleksinya paling banyak
            // BATAS (100) baris yang SUDAH diambil, jadi menyusunnya di PHP
            // tidak menambah kueri dan tidak bisa salah kutip.
            ->sortBy(fn (Order $order): int => array_search($order->ulid, $kunci, true))
            ->values();

        if ($pesanan->isEmpty()) {
            return back()->with('galat-admin', 'Pesanan yang dipilih tidak ditemukan.');
        }

        // Pesanan yang sudah dibatalkan tetap dicetak, tetapi dengan tanda
        // air SALINAN — sama seperti resi tunggal (checklist F8-09). Setelah
        // dibatalkan, label yang beredar tidak boleh lagi terbaca sah.
        //
        // Gaya @page dan kelas label ada di partial _gaya.blade.php, yang
        // disertakan oleh view label itu sendiri. TIDAK ada baris
        // "page-break-after: always;" yang ditambahkan di sini: wadah .label
        // sudah memuat break-nya, dan menambahkannya lagi membuat setiap label
        // menghasilkan dua halaman — satu berisi label, satu kosong. Itu yang
        // membuat "tiga pesanan dicetak jadi sembilan halaman".
        $html = '';
        $nomorSeri = 0;

        foreach ($pesanan as $order) {
            $nomorSeri++;

            $html .= view('cetak.label-pesanan', [
                'order' => $order,
                'toko' => Pengaturan::instance(),
                'alamat' => $order->alamat(),
                // Status sudah di-cast menjadi enum StatusPesanan, jadi dibandingkan
                // dengan enum — bukan string. (status === 'cancelled' selalu
                // false, dan tanda air SALINAN tidak akan pernah muncul.)
                'salinan' => $order->status === StatusPesanan::Cancelled,
                // Nomor seri = posisi dalam koleksi yang SUDAH terurut sesuai
                // pilihan admin.
                'nomorSeri' => $nomorSeri,
                'totalLabel' => $pesanan->count(),
                // Label terakhir tidak memaksa pindah halaman. Tanpa ini, tiap
                // berkas berakhir satu halaman kosong — 4 label jadi 5 halaman.
                'terakhir' => $nomorSeri === $pesanan->count(),
                // Gaya disertakan sekali saja, di <head> lihat di bawah.
                'gaya' => false,
            ])->render();
        }

        // Gaya masuk SEKALI, di <head>.
        //
        // Bila <style> ikut tercetak di dalam <body> untuk setiap label, tiap
        // blok gaya dihitung sebagai isi halaman oleh dompdf. Terukur: empat
        // label dengan gaya berulang menghasilkan lima halaman; lima label
        // menghasilkan lebih lagi. Satu blok di <head> menghasilkan tepat satu
        // halaman per label.
        $html = '<!DOCTYPE html><html lang="id"><head>'
            .view('cetak._gaya', ['margin' => '4mm'])->render()
            .'</head><body>'
            .$html
            .'</body></html>';

        $namaBerkas = $pesanan->count() === 1
            ? 'resi-'.$pesanan->first()->order_number.'.pdf'
            : 'resi-'.$pesanan->count().'-pesanan-'.now()->format('Ymd-His').'.pdf';

        // Dicatat SEBELUM berkas dikembalikan, bukan sesudah: kalau pencatatan
        // gagal, admin sudah punya berkasnya dan tidak perlu mencetak ulang.
        foreach ($pesanan as $order) {
            if ($order->printed_at === null) {
                $order->forceFill(['printed_at' => now()])->save();
            }

            CatatanAktivitas::catat(
                'resi.dicetak',
                ['nomor' => $order->order_number, 'jumlah_label' => $pesanan->count()],
                subjek: $order,
            );
        }

        return DokumenPdf::dariHtml(
            $html,
            self::KERTAS,
            $namaBerkas,
        );
    }
}
