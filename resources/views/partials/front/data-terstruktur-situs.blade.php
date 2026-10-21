{{--
    Data terstruktur tingkat situs: identitas toko + jalur navigasi.

    Dipisahkan dari partial data-terstruktur (yang khusus produk) supaya
    tanggung jawabnya jelas: partial itu mengurus SATU produk dan penawarannya,
    partial ini mengurus SIAPA penjualnya. Mesin jawaban sering mengutip satu
    halaman produk tanpa pernah membuka beranda, jadi identitas toko harus ikut
    di setiap halaman produk.

    Pengamanan yang sama seperti partial produk: setiap "</" di dalam data
    dilolosikan agar tidak ada nilai yang menutup tag script lebih awal.
--}}
@php
    $grafik = \App\Support\DataTerstruktur::untuk([], $produk ?? null);

    $json = $grafik === null
        ? null
        : json_encode($grafik, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    $json = $json === null ? null : str_replace(['</', '<!--'], ['<\/', '<\!--'], $json);
@endphp

@if ($json !== null)
<script type="application/ld+json">
{!! $json !!}
</script>
@endif
