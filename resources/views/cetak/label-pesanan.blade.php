{{-- Satu label 100x150 mm untuk cetak resi banyak.

     Dipisah dari cetak/resi.blade.php karena pemakaiannya berbeda, meski
     isinya mirip. Berkas itu satu pesanan per permintaan dengan tombol
     "Cetak"; berkas ini dipanggil dari model lewat view()->render() dan
     dirangkai dengan label lain dalam SATU PDF. Yang tidak boleh ada di sini:
     tombol cetak (label kedua sudah tidak terlihat), <html>/<head> penuh
     (satu dokumen hanya boleh punya satu), dan gaya yang bergantung pada
     halaman.

     Variabel:
       $order        — pesanan
       $toko         — pengaturan situs
       $alamat       — snapshot alamat pengiriman
       $salinan      — true bila pesanan dibatalkan (tanda air, F8-09)
       $nomorSeri    — label ke berapa dari seluruh rangkaian
       $totalLabel   — jumlah label dalam berkas itu
       $terakhir     — true untuk label paling akhir; memutus page-break-nya
                       supaya tidak ada halaman kosong sesudah label terakhir
       $gaya         — false bila pemanggil sudah menyediakan <style> sendiri
                       (dipakai ResiBanyak: satu berkas, banyak label) --}}
@if ($gaya ?? true)
    @include('cetak._gaya', ['margin' => '4mm'])
@endif

@php
    /** @var \App\Models\Order $order */
    /** @var \App\Support\Pengaturan $toko */

    // Kenapa $gaya ada: lihat catatan di ResiBanyak. Ringkasnya — <style> di
    // dalam <body> yang diulang untuk setiap label ikut dihitung sebagai isi
    // halaman, dan satu label pun menjadi dua halaman.
    $negaraLuar = ! \App\Support\Internasional::dalamNegeri((string) $order->shipping_country);
@endphp

<div class="label{{ ($terakhir ?? false) ? ' label-terakhir' : '' }}">

    {{-- Nomor seri. Dua puluh label yang sudah dilepas dari lembaran jasnya
         mustahil disusun ulang kalau tidak ada nomor; nomor ini yang
         menyelamatkan saat satu label terlepas dan harus ditempel manual. --}}
    @if ($totalLabel > 1)
        <p class="rapat kecil" style="text-align:right">
            Label {{ $nomorSeri }} dari {{ $totalLabel }}
        </p>
    @endif

    {{-- Pengirim --}}
    <p class="tengah rapat besar">{{ $toko->teks('site_name', 'Iksan Knalpot') }}</p>
    <p class="tengah rapat kecil">{{ $toko->teks('phone_number') }}</p>
    <p class="tengah rapat kecil">{{ $toko->teks('address_full') }}</p>

    <div class="tebal"></div>

    {{-- Penerima --}}
    <p class="rapat kecil">PENERIMA</p>
    <p class="rapat" style="font-weight:700">{{ $alamat['recipient_name'] ?? $order->namaPenerima() }}</p>
    <p class="rapat alamat">{{ $alamat['phone_number'] ?? $order->teleponPenerima() }}</p>
    <p class="rapat alamat">
        {{ $alamat['address_line_1'] ?? '' }}
        @if (filled($alamat['address_line_2'] ?? null))<br>{{ $alamat['address_line_2'] }}@endif
        <br>
        {{ $alamat['city'] ?? '' }}{{ filled($alamat['city'] ?? null) ? ', ' : '' }}{{ $alamat['state_province'] ?? '' }}
        {{ $alamat['postal_code'] ?? '' }}
        @if ($negaraLuar)
            <br><span style="font-weight:700">{{ \App\Support\Internasional::namaNegara((string) $order->shipping_country) }}</span>
        @endif
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

    @if ($negaraLuar)
        {{-- Luar negeri: penanda yang bisa dilihat kurir tanpa membaca isi
             halaman ini. Ongkos internasional disepakati terpisah, dan paket
             ini butuh penanganan bea cukai. --}}
        <div class="putus"></div>
        <p class="rapat kecil">PENGIRIMAN LUAR NEGERI — CARGO INTERNASIONAL</p>
    @endif

    {{-- Tanda air SALINAN untuk pesanan yang sudah dibatalkan (checklist F8-09).
         Memakai partial yang sama dengan resi tunggal: satu sumber, satu bentuk. --}}
    @include("cetak._salinan")

    <div class="putus"></div>

    {{-- Isi paket. Resi ikut menempel di paket, jadi harga tidak dicetak di sini:
         tidak perlu diketahui kurir, dan tidak perlu dilihat orang lain.

         Daftar dibatasi DUA baris. Terukur pada label 100x150 mm: tiga baris
         item sudah cukup untuk mendorong label meluap menjadi halaman kedua,
         apalagi bila pesanan juga membawa tanda air SALINAN dan catatan
         pembeli — dua blok yang menambah tinggi. Dua baris plus ringkasan
         "+N lainnya" muat di semua pesanan yang saya coba.

         Total barang di bawah tetap menyebut jumlah penuh, jadi kurir dapat
         menghitung isi paket tanpa membaca seluruh daftar. --}}
    <p class="rapat kecil">ISI PAKET</p>

    <table>
        @foreach ($order->items->take(2) as $item)
            <tr>
                <td style="width:34px" class="kanan">{{ $item->quantity }}×</td>
                <td>
                    {{ $item->product_name }}
                    @if ($item->variant_name)<br><span class="kecil">{{ $item->variant_name }}@endif</span>
                    @if ($item->sku)<br><span class="kecil">{{ $item->sku }}@endif</span>
                </td>
            </tr>
        @endforeach

        @if ($order->items->count() > 2)
            <tr>
                <td class="kanan kecil">+</td>
                <td class="kecil">+{{ $order->items->count() - 2 }} lainnya</td>
            </tr>
        @endif
    </table>

    <div class="putus"></div>

    <div class="baris">
        <span class="kecil">TOTAL BARANG</span>
        <span style="font-weight:700">{{ $order->jumlahItem() }} pcs</span>
    </div>

</div>
