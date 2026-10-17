<?php

namespace App\Livewire\Front;

use App\Services\Keranjang;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lencana jumlah keranjang.
 *
 * Komponen terpisah, bukan angka yang dirender sekali di layout. Tanpa ini,
 * menambah item hanya memperbarui halaman yang sedang dibuka, dan badge di
 * navbar tetap menampilkan angka lama sampai halaman dimuat ulang.
 */
class JumlahKeranjang extends Component
{
    public int $jumlah = 0;

    public function mount(Keranjang $keranjang): void
    {
        $this->jumlah = $keranjang->jumlahItem();
    }

    #[On('keranjang-berubah')]
    public function segarkan(Keranjang $keranjang): void
    {
        // Paksa baca ulang dari basis data. Objek Keranjang dapat sudah
        // men-cache hasil sebelum item ditambahkan.
        $keranjang->lupakan();
        $this->jumlah = $keranjang->jumlahItem();
    }

    public function render(): View
    {
        return view('livewire.front.jumlah-keranjang');
    }
}
