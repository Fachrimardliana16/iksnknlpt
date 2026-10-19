<?php

namespace App\Livewire\Front;

use App\Models\ProductVariant;
use App\Services\Keranjang;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tombol tambah ke keranjang langsung dari kartu katalog.
 *
 * Sebelumnya satu-satunya jalan menambah barang adalah membuka halaman
 * produk. Pada ponsel itu berarti tiga ketukan untuk satu tindakan yang
 * paling sering dilakukan saat berbelanja. Tombol ini memangkasnya jadi satu.
 *
 * Komponennya kecil dan hidup per kartu, jadi perubahan pada satu kartu tidak
 * memaksa seluruh daftar dirender ulang.
 */
class TambahKeranjangCepat extends Component
{
    public int $varianId;

    /** Nada pesan singkat di bawah tombol: ok atau danger. */
    public string $nada = '';

    /** Kunci pesan terjemahan, bukan teksnya, supaya ikut bahasa aktif. */
    public string $pesanKunci = '';

    public int $jumlahDiKeranjang = 0;

    public function mount(int $varianId): void
    {
        $this->varianId = $varianId;
        $this->hitungUlang();
    }

    public function tambah(Keranjang $keranjang): void
    {
        $varian = ProductVariant::query()->with('product')->find($this->varianId);

        if ($varian === null || ! $varian->is_active) {
            $this->nada = 'danger';
            $this->pesanKunci = 'site.product.out_of_stock';

            return;
        }

        $akhir = $keranjang->tambah($varian, 1);

        if ($akhir <= 0) {
            $this->nada = 'danger';
            $this->pesanKunci = 'site.product.out_of_stock';

            return;
        }

        // Navbar dan bar navigasi bawah memperbarui badge tanpa muat ulang.
        $this->dispatch('keranjang-berubah');

        $this->nada = 'ok';
        $this->pesanKunci = 'site.product.added_to_cart';
        $this->jumlahDiKeranjang = $akhir;
    }

    /** Dijalankan ketika komponen lain mengubah isi keranjang. */
    #[On('keranjang-berubah')]
    public function hitungUlang(): void
    {
        $this->jumlahDiKeranjang = app(Keranjang::class)
            ->items()
            ->where('product_variant_id', $this->varianId)
            ->sum('quantity');
    }

    public function render(): View
    {
        return view('livewire.front.tambah-keranjang-cepat');
    }
}
