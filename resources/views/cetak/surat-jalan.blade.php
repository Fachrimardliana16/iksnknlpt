@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */

    // Surat jalan ikut menempel di paket dan ditandatangani penerima.
    // Harga TIDAK dicetak di sini.
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Jalan {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #17171a;
            background: #fff;
        }

        .tajuk { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
        .nama-toko { font-size: 19px; font-weight: 700; }
        .kecil { font-size: 10px; color: #6b6b70; }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }

        .judul {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #c8102e;
            text-align: right;
            margin: 0;
        }

        .garis { border-top: 2px solid #17171a; margin: 10px 0; }

        .kotak-resi {
            border: 1.5px solid #17171a;
            padding: 6px 10px;
            display: inline-block;
        }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: 6px;
            border-bottom: 1.5px solid #17171a;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 9.5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        tbody td {
            padding: 7px 6px;
            border-bottom: 1px solid #e2e2de;
            vertical-align: top;
        }
        .kanan { text-align: right; }

        .tanda-tangan {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-top: 22px;
        }
        .kolom-ttd { flex: 1; text-align: center; }
        .garis-ttd {
            border-top: 1px solid #17171a;
            margin-top: 46px;
            padding-top: 4px;
            font-size: 10px;
            color: #6b6b70;
        }

        @media print {
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

@include('cetak._salinan')

<div class="tanpa-cetak" style="margin-bottom:10px">
    <button type="button" data-aksi="cetak" style="padding:7px 14px;font:inherit;cursor:pointer">Cetak</button>
    <span class="kecil">atau tekan Ctrl+P</span>
</div>

<div class="tajuk">
    <div>
        <p class="nama-toko">{{ $toko->teks('site_name', 'Iksan Knalpot') }}</p>
        <p class="kecil" style="margin:3px 0 0">
            {{ $toko->teks('address_full') }}<br>
            {{ $toko->teks('phone_number') }}
        </p>
    </div>

    <div>
        <p class="judul">SURAT JALAN</p>
        <p class="mono kecil" style="margin:4px 0 0;text-align:right">{{ $order->order_number }}</p>
        <p class="kecil" style="margin:2px 0 0;text-align:right">{{ $order->created_at->format('d F Y') }}</p>
    </div>
</div>

<div class="garis"></div>

<div style="display:flex;gap:24px;align-items:flex-start">
    <div style="flex:1.4">
        <p class="kecil" style="margin:0">KIRIM KEPADA</p>
        <p style="margin:4px 0 0;font-weight:700">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
        <p class="kecil" style="margin:3px 0 0;line-height:1.4">
            {{ $alamat['phone_number'] ?? $order->teleponPenerima() }}<br>
            {{ $alamat['address_line_1'] ?? '' }}
            @if (filled($alamat['address_line_2'] ?? null))<br>{{ $alamat['address_line_2'] }}@endif
            @if (filled($alamat['city'] ?? null))<br>{{ $alamat['city'] }}, {{ $alamat['state_province'] }} {{ $alamat['postal_code'] }}@endif
        </p>
    </div>

    <div style="flex:1">
        <p class="kecil" style="margin:0">KURIR</p>
        <p style="margin:4px 0 0">{{ $order->shipping_courier ?: '—' }} {{ $order->shipping_service ?: '' }}</p>

        <p class="kecil" style="margin:8px 0 0">NOMOR RESI</p>
        <div class="kotak-resi">
            <span class="mono" style="font-weight:700">{{ $order->tracking_number ?: '— belum diisi —' }}</span>
        </div>
    </div>

    <div style="flex:0.8">
        <p class="kecil" style="margin:0">BERAT TOTAL</p>
        <p class="mono" style="margin:4px 0 0">{{ number_format($order->beratTotal() / 1000, 2, ',', '.') }} kg</p>

        <p class="kecil" style="margin:8px 0 0">JUMLAH BARANG</p>
        <p class="mono" style="margin:4px 0 0">{{ $order->jumlahItem() }} pcs</p>
    </div>
</div>

<div style="height:10px"></div>

<table>
    <thead>
        <tr>
            <th style="width:8mm">#</th>
            <th>Nama barang</th>
            <th style="width:30mm">SKU</th>
            <th class="kanan" style="width:16mm">Jml</th>
            <th class="kanan" style="width:22mm">Berat</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($order->items as $i => $item)
            <tr>
                <td class="mono kecil">{{ $i + 1 }}</td>
                <td>
                    {{ $item->product_name }}
                    @if ($item->variant_name)
                        <br><span class="kecil">{{ $item->variant_name }}</span>
                    @endif
                </td>
                <td class="mono kecil">{{ $item->sku }}</td>
                <td class="kanan mono">{{ $item->quantity }}</td>
                <td class="kanan mono">
                    {{ number_format(($item->weight_gram * $item->quantity) / 1000, 2, ',', '.') }} kg
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@if (filled($order->customer_note))
    <p class="kecil" style="margin-top:10px">
        <strong>Catatan pembeli:</strong> {{ $order->customer_note }}
    </p>
@endif

<div class="tanda-tangan">
    <div class="kolom-ttd">
        <p class="kecil" style="margin:0">Diserahkan oleh</p>
        <div class="garis-ttd">Nama dan tanda tangan</div>
    </div>

    <div class="kolom-ttd">
        <p class="kecil" style="margin:0">Diterima oleh</p>
        <div class="garis-ttd">Nama dan tanda tangan penerima</div>
    </div>
</div>

<p class="kecil" style="margin-top:14px">
    Barang wajib diperiksa saat diterima. Kerusakan akibat pengiriman harus dilaporkan
    pada hari yang sama dengan bukti foto.
</p>

</body>
</html>
