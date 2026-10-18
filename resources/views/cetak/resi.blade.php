@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */

    // Lebar kertas 80 mm. Ini ukuran kertas resi yang paling umum dipakai
    // kurir di Indonesia, dan dicetak dari printer termal maupun printer biasa.
    $tujuan = $order->alamat();
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resi {{ $order->order_number }}</title>

    {{-- Halaman cetak tidak memuat CSS aplikasi penuh: hanya gaya yang perlu.
         Ukuran kertas dinyatakan langsung supaya peramban tidak menyisipkan
         margin bawaan yang membuat label meleset dari kertas. --}}
    <style>
        @page { size: 80mm auto; margin: 3mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 4mm;
            font-family: 'IBM Plex Mono', ui-monospace, 'Cascadia Mono', monospace;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            background: #fff;
            width: 80mm;
        }

        .tengah { text-align: center; }
        .besar { font-size: 14px; font-weight: 700; }
        .kecil { font-size: 9px; }
        .putus { border-top: 1px dashed #000; margin: 6px 0; }
        .tebal { border-top: 2px solid #000; margin: 6px 0; }
        .baris { display: flex; justify-content: space-between; gap: 8px; }
        .rapat { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .kanan { text-align: right; }
        .alamat { font-size: 10px; line-height: 1.3; }

        .kotak-resi {
            border: 1.5px solid #000;
            padding: 4px;
            margin: 5px 0;
            text-align: center;
        }
        .nomor-resi { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; }

        @media print {
            body { padding: 0; width: auto; }
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

<div class="tanpa-cetak" style="margin-bottom:8px">
    <button type="button" data-aksi="cetak"
            style="padding:6px 12px;font:inherit;cursor:pointer">
        Cetak
    </button>
    <span class="kecil">atau tekan Ctrl+P</span>
</div>

{{-- Pengirim --}}
<p class="tengah rapat besar">{{ $toko->teks('site_name', 'Iksan Knalpot') }}</p>
<p class="tengah rapat kecil">{{ $toko->teks('phone_number') }}</p>
<p class="tengah rapat kecil">{{ $toko->teks('address_full') }}</p>

<div class="tebal"></div>

{{-- Penerima --}}
<p class="rapat kecil">PENERIMA</p>
<p class="rapat" style="font-weight:700">{{ $tujuan['recipient_name'] ?? $order->namaPenerima() }}</p>
<p class="rapat alamat">{{ $tujuan['phone_number'] ?? $order->teleponPenerima() }}</p>
<p class="rapat alamat">
    {{ $tujuan['address_line_1'] ?? '' }}
    @if (filled($tujuan['address_line_2'] ?? null))<br>{{ $tujuan['address_line_2'] }}@endif
    <br>
    {{ $tujuan['city'] ?? '' }}{{ filled($tujuan['city'] ?? null) ? ', ' : '' }}{{ $tujuan['state_province'] ?? '' }}
    {{ $tujuan['postal_code'] ?? '' }}
</p>

@if (filled($order->customer_note))
    <div class="putus"></div>
    <p class="rapat kecil">CATATAN PEMBELI</p>
    <p class="rapat alamat">{{ $order->customer_note }}</p>
@endif

<div class="tebal"></div>

{{-- Nomor pesanan dan resi --}}
<div class="baris">
    <span class="kecil">NO. PESANAN</span>
    <span style="font-weight:700">{{ $order->order_number }}</span>
</div>

<div class="kotak-resi">
    <p class="rapat kecil">NOMOR RESI</p>
    <p class="rapat nomor-resi">{{ $order->tracking_number ?: '— belum diisi —' }}</p>
</div>

<div class="baris kecil">
    <span>{{ $order->shipping_courier ?: 'kurir belum dipilih' }}</span>
    <span>{{ $order->shipping_service ?: '' }}</span>
</div>

<div class="putus"></div>

{{-- Isi paket. Resi ikut menempel di paket, jadi harga tidak dicetak di sini:
     tidak perlu diketahui kurir, dan tidak perlu dilihat orang lain. --}}
<p class="rapat kecil">ISI PAKET</p>

<table>
    @foreach ($order->items as $item)
        <tr>
            <td style="width:34px" class="kanan">{{ $item->quantity }}×</td>
            <td>
                {{ $item->product_name }}
                @if ($item->variant_name)<br><span class="kecil">{{ $item->variant_name }}</span>@endif
                @if ($item->sku)<br><span class="kecil">{{ $item->sku }}</span>@endif
            </td>
        </tr>
    @endforeach
</table>

<div class="putus"></div>

<div class="baris">
    <span class="kecil">TOTAL BARANG</span>
    <span>{{ $order->jumlahItem() }} pcs</span>
</div>

<div class="baris">
    <span class="kecil">BERAT</span>
    <span>{{ number_format($order->beratTotal() / 1000, 2, ',', '.') }} kg</span>
</div>

<div class="baris">
    <span class="kecil">TANGGAL</span>
    <span>{{ $order->created_at->format('d/m/Y') }}</span>
</div>

<div class="tebal"></div>

<p class="tengah rapat kecil">Terima kasih telah berbelanja.</p>
<p class="tengah rapat kecil">
    Barang wajib diperiksa saat diterima. Klaim cacat produksi
    maksimal 3 hari sejak paket diterima.
</p>

</body>
</html>
