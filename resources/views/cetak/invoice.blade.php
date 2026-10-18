@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \App\Models\Order $order */
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page { size: A4; margin: 14mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #17171a;
            background: #fff;
        }

        .tajuk { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .nama-toko { font-size: 22px; font-weight: 700; letter-spacing: -0.01em; }
        .kecil { font-size: 10px; color: #6b6b70; }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }

        .judul-dokumen { text-align: right; }
        .judul-dokumen p { margin: 0; }
        .judul-dokumen .judul {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #c8102e;
        }

        .garis { border-top: 2px solid #17171a; margin: 12px 0; }
        .garis-tipis { border-top: 1px solid #e2e2de; margin: 10px 0; }

        .dua-kolom { display: flex; gap: 28px; }
        .dua-kolom > div { flex: 1; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: 7px 6px;
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

        .ringkasan { margin-left: auto; width: 62mm; margin-top: 8px; }
        .ringkasan tr td { padding: 3px 6px; border: 0; }
        .ringkasan .total td {
            border-top: 1.5px solid #17171a;
            font-size: 14px;
            font-weight: 700;
            padding-top: 6px;
        }

        .cap {
            display: inline-block;
            padding: 4px 10px;
            border: 2px solid;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
        }
        .cap-lunas { color: #2f6b3f; border-color: #2f6b3f; }
        .cap-belum { color: #a51c2c; border-color: #a51c2c; }

        .catatan { margin-top: 14px; padding: 10px; background: #f5f5f4; font-size: 10.5px; }

        @media print {
            .tanpa-cetak { display: none !important; }
        }
    </style>
</head>
<body>

<div class="tanpa-cetak" style="margin-bottom:12px">
    <button type="button" data-aksi="cetak" style="padding:7px 14px;font:inherit;cursor:pointer">Cetak</button>
    <span class="kecil">atau tekan Ctrl+P</span>
</div>

<div class="tajuk">
    <div>
        <p class="nama-toko">{{ $toko->teks('site_name', 'Iksan Knalpot') }}</p>
        <p class="kecil" style="margin:4px 0 0">{{ $toko->teks('site_tagline') }}</p>
        <p class="kecil" style="margin:6px 0 0">
            {{ $toko->teks('address_full') }}<br>
            {{ $toko->teks('phone_number') }}
            @if ($toko->teks('contact_email')) · {{ $toko->teks('contact_email') }} @endif
        </p>
    </div>

    <div class="judul-dokumen">
        <p class="judul">INVOICE</p>
        <p class="mono" style="font-size:13px;margin-top:4px">{{ $order->order_number }}</p>
        <p class="kecil" style="margin-top:4px">
            Diterbitkan {{ $order->created_at->format('d F Y') }}
        </p>
        <p style="margin-top:8px">
            @if ($order->payment_status === \App\Enums\StatusPembayaran::Paid)
                <span class="cap cap-lunas">LUNAS</span>
            @else
                <span class="cap cap-belum">BELUM DIBAYAR</span>
            @endif
        </p>
    </div>
</div>

<div class="garis"></div>

<div class="dua-kolom">
    <div>
        <p class="kecil" style="margin:0">DITAGIHKAN KEPADA</p>
        <p style="margin:4px 0 0;font-weight:700">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
        <p class="kecil" style="margin:2px 0 0">
            {{ $order->emailPenerima() ?? $order->user?->email ?? '' }}<br>
            {{ $alamat['phone_number'] ?? $order->teleponPenerima() }}
        </p>
    </div>

    <div>
        <p class="kecil" style="margin:0">ALAMAT PENGIRIMAN</p>
        <p class="kecil" style="margin:4px 0 0;line-height:1.4">
            {{ $alamat['address_line_1'] ?? '' }}
            @if (filled($alamat['address_line_2'] ?? null))<br>{{ $alamat['address_line_2'] }}@endif
            @if (filled($alamat['city'] ?? null))<br>{{ $alamat['city'] }}, {{ $alamat['state_province'] }} {{ $alamat['postal_code'] }}@endif
        </p>
    </div>

    <div>
        <p class="kecil" style="margin:0">PENGIRIMAN</p>
        <p class="kecil" style="margin:4px 0 0;line-height:1.4">
            Kurir: {{ $order->shipping_courier ?: '—' }}<br>
            Layanan: {{ $order->shipping_service ?: '—' }}<br>
            Resi: <span class="mono">{{ $order->tracking_number ?: '—' }}</span>
        </p>
    </div>
</div>

<div class="garis-tipis"></div>

<table>
    <thead>
        <tr>
            <th style="width:8mm">#</th>
            <th>Barang</th>
            <th style="width:24mm">SKU</th>
            <th class="kanan" style="width:14mm">Jml</th>
            <th class="kanan" style="width:26mm">Harga</th>
            <th class="kanan" style="width:28mm">Jumlah</th>
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
                <td class="kanan mono">{{ rupiah($item->unit_price) }}</td>
                <td class="kanan mono">{{ rupiah($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="ringkasan">
    <tr>
        <td class="kecil">Subtotal</td>
        <td class="kanan mono">{{ rupiah($order->subtotal) }}</td>
    </tr>

    @if ((float) $order->discount_amount > 0)
        <tr>
            <td class="kecil">
                Diskon @if ($order->coupon) <span class="mono">({{ $order->coupon->code }})</span> @endif
            </td>
            <td class="kanan mono">−{{ rupiah($order->discount_amount) }}</td>
        </tr>
    @endif

    @if ((float) $order->shipping_cost > 0)
        <tr>
            <td class="kecil">Ongkos kirim</td>
            <td class="kanan mono">{{ rupiah($order->shipping_cost) }}</td>
        </tr>
    @endif

    @if ((float) $order->tax_amount > 0)
        <tr>
            <td class="kecil">Pajak</td>
            <td class="kanan mono">{{ rupiah($order->tax_amount) }}</td>
        </tr>
    @endif

    <tr class="total">
        <td>Total</td>
        <td class="kanan mono">{{ rupiah($order->grand_total) }}</td>
    </tr>
</table>

<div style="clear:both;height:14px"></div>

@if ((float) $order->shipping_cost <= 0)
    <div class="catatan">
        Ongkos kirim belum ditetapkan pada pesanan ini. Bila pengiriman dikenakan biaya,
        nominalnya akan muncul di invoice terbitan berikutnya.
    </div>
@endif

<div class="catatan">
    <strong>Ketentuan.</strong>
    Pesanan dianggap sah setelah pembayaran diterima. Pesanan yang belum dibayar dalam
    24 jam dibatalkan otomatis dan stok dikembalikan. Garansi berlaku untuk cacat
    produksi, bukan kerusakan akibat pemasangan yang salah.
</div>

<p class="kecil" style="margin-top:14px;text-align:center">
    Invoice ini diterbitkan secara otomatis oleh sistem. Tidak memerlukan tanda tangan basah.
</p>

</body>
</html>
