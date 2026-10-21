@props(['nominal'])

{{--
    Perkiraan harga dalam mata uang asing.

    Ditampilkan kecil di bawah harga rupiah, dengan label "perkiraan". Rupiah
    tetap harga yang ditagih; dolar hanya alat bantu bagi pembeli luar negeri.
    Tanpa label, pembeli mengira itulah harga pasti, lalu merasa tertipu ketika
    kurs bergerak saat pembayaran.

    Bila kurs tidak tersedia (sumber tidak dapat dihubungi), komponen ini tidak
    merender apa pun — halaman tidak boleh rusak hanya karena API kurs mati.
--}}
@php
    $perkiraan = \App\Support\Kurs::perkiraan($nominal ?? 0);
@endphp

@if ($perkiraan !== null)
    <p class="mt-0.5 text-xs tabular-nums text-dim"
       title="Perkiraan pada kurs Rp {{ number_format($perkiraan['kurs'], 0, ',', '.') }} per USD">
        ≈ {{ $perkiraan['teks'] }}
        <span class="text-[0.625rem]">USD (perkiraan)</span>
    </p>
@endif
