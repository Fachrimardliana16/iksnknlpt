@php
    /** @var \App\Support\Pengaturan $toko */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Order> $pesanan */
    /** @var array<string, mixed> $ringkas */
    /** @var \Illuminate\Support\Carbon $mulai */
    /** @var \Illuminate\Support\Carbon $sampai */
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Penjualan {{ $mulai->format('d M Y') }} – {{ $sampai->format('d M Y') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #17171a;
            background: #fff;
        }

        .nama-toko { font-size: 20px; font-weight: 700; letter-spacing: -0.01em; }
        .kecil { font-size: 9.5px; color: #6b6b70; }
        .mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; }

        .tajuk { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .judul-dokumen { text-align: right; }
        .judul-dokumen p { margin: 0; }
        .judul-dokumen .judul {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #c8102e;
        }

        .garis { border-top: 2px solid #17171a; margin: 12px 0; }

        .angka { display: flex; gap: 10px; margin-top: 12px; }
        .angka > div {
            flex: 1;
            border: 1px solid #e2e2de;
            padding: 8px 10px;
        }
        .angka .label {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 8.5px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b6b70;
        }
        .angka .nilai { font-size: 15px; font-weight: 700; margin-top: 3px; }

        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        thead th {
            text-align: left;
            padding: 6px 5px;
            border-bottom: 1.5px solid #17171a;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: 8.5px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        tbody td {
            padding: 5px;
            border-bottom: 1px solid #e2e2de;
            vertical-align: top;
            font-size: 10px;
        }
        tbody tr:nth-child(even) { background: #fafaf9; }
        .kanan { text-align: right; }
        .total-baris td {
            border-top: 1.5px solid #17171a;
            border-bottom: 0;
            font-weight: 700;
            font-size: 11px;
        }
        .catatan { margin-top: 12px; font-size: 9.5px; color: #6b6b70; }

        /* Dompdf tidak memotong baris tabel antar halaman, jadi thead diulang
           lewat <thead> biasa — cukup, tanpa thead { display: table-header-group }. */
    </style>
</head>
<body>

<div class="tajuk">
    <div>
        <p class="nama-toko">{{ $toko->teks('site_name', 'Iksan Knalpot') }}</p>
        <p class="kecil" style="margin:4px 0 0">{{ $toko->teks('site_tagline') }}</p>
    </div>

    <div class="judul-dokumen">
        <p class="judul">LAPORAN PENJUALAN</p>
        <p class="mono" style="font-size:12px;margin-top:4px">
            {{ $mulai->format('d M Y') }} – {{ $sampai->format('d M Y') }}
        </p>
        <p class="kecil" style="margin-top:4px">
            Dikeluarkan {{ now()->format('d M Y H:i') }}
        </p>
    </div>
</div>

<div class="garis"></div>

<div class="angka">
    <div>
        <p class="label">Pesanan</p>
        <p class="nilai">{{ number_format($ringkas['jumlah'], 0, ',', '.') }}</p>
    </div>
    <div>
        <p class="label">Selesai</p>
        <p class="nilai">{{ number_format($ringkas['selesai'], 0, ',', '.') }}</p>
    </div>
    <div>
        <p class="label">Dibatalkan</p>
        <p class="nilai">{{ number_format($ringkas['batal'], 0, ',', '.') }}</p>
    </div>
    <div>
        <p class="label">Pendapatan</p>
        <p class="nilai">{{ rupiah($ringkas['pendapatan']) }}</p>
    </div>
    <div>
        <p class="label">Belum masuk</p>
        <p class="nilai">{{ rupiah($ringkas['nilaiBelumMasuk']) }}</p>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Nomor</th>
            <th>Tanggal</th>
            <th>Pembeli</th>
            <th>Status</th>
            <th class="kanan">Barang</th>
            <th class="kanan">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pesanan as $o)
            <tr>
                <td class="mono">{{ $o->order_number }}</td>
                <td>{{ $o->created_at->format('d M Y') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($o->namaPenerima() ?: $o->user?->name ?: '—', 28) }}</td>
                <td>{{ $o->status->label() }} · {{ $o->payment_status->label() }}</td>
                <td class="kanan">{{ $o->jumlahItem() }}</td>
                <td class="kanan">{{ number_format((float) $o->grand_total, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="padding:16px 5px;text-align:center;color:#6b6b70">
                    Tidak ada pesanan pada rentang ini.
                </td>
            </tr>
        @endforelse

        @if ($pesanan->isNotEmpty())
            <tr class="total-baris">
                <td colspan="5">Total pendapatan (lunas + diproses + dikirim + selesai)</td>
                <td class="kanan">{{ rupiah($ringkas['pendapatan']) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<p class="catatan">
    Rata-rata nilai pesanan {{ rupiah($ringkas['rataRata']) }}.
    Pesanan yang dibatalkan tidak dihitung sebagai pendapatan.
</p>

</body>
</html>
