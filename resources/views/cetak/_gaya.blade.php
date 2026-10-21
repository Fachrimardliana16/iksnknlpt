{{--
    Gaya dasar untuk dokumen cetak (label, resi, invoice, surat jalan).

    Dipisah sebagai partial agar SATU sumber untuk semua dokumen cetak, dan
    supaya dokumen yang dirangkai dalam satu PDF juga memakai gaya yang sama.

    Variabel:
      $lebar  — lebar halaman dalam CSS (mis. "100mm" untuk label, "210mm" A4)
      $tinggi — tinggi halaman dalam CSS (mis. "150mm" untuk label, "297mm" A4)
      $margin — margin halaman dalam CSS (mis. "4mm")

    Kenapa tidak memuat CSS aplikasi (resources/css/app.css): berkas itu
    berisi aturan untuk layar — variabel tema, media query, utility Tailwind.
    Dompdf memprosesnya sebagian, mengabaikan sisanya tanpa pesan, dan hasilnya
    berubah setiap kali CSS aplikasi disunting. Gaya di sini sengaja sedikit
    dan mandiri.
--}}
<style>
    @page { size: {{ $lebar ?? '100mm' }} {{ $tinggi ?? '150mm' }}; margin: {{ $margin ?? '4mm' }}; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-family: 'DejaVu Sans', ui-monospace, monospace;
        font-size: 11px;
        line-height: 1.35;
        color: #000;
        background: #fff;
    }

    /*
     * Satu label per halaman. Ditulis pada wadah, bukan pada baris pemisah
     * terpisah: dua page-break untuk satu label menghasilkan halaman kosong
     * di tengah rangkaian, dan label yang dicetak jadi dua kali lipat
     * jumlah pesanan yang dipilih.
     *
     * .label-terakhir memutus break untuk label paling akhir. Dipakai kelas
     * eksplisit, bukan :last-child: dompdf mengabaikan :last-child pada aturan
     * ini tanpa pesan, dan label terakhir yang tetap memaksa break menghasilkan
     * satu halaman kosong sesudahnya. Terukur: 4 label -> 5 halaman, dan
     * halaman kelima kosong (646 byte, hanya latar).
     */
    .label {
        page-break-after: always;
        break-after: page;
    }

    .label-terakhir {
        page-break-after: auto !important;
        break-after: auto !important;
    }

    .tengah { text-align: center; }
    .kanan { text-align: right; }
    .rapat { margin: 0; }
    .besar { font-size: 14px; font-weight: 700; }
    .kecil { font-size: 9px; }
    .alamat { font-size: 10px; line-height: 1.3; }

    .putus { border-top: 1px dashed #000; margin: 6px 0; }
    .tebal { border-top: 2px solid #000; margin: 6px 0; }

    .baris { display: flex; justify-content: space-between; gap: 8px; }

    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }

    .kotak-resi {
        border: 1.5px solid #000;
        padding: 4px;
        margin: 5px 0;
        text-align: center;
    }
    .nomor-resi { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; }
</style>
