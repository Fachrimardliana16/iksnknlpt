<?php

namespace App\Livewire\Front;

use App\Models\Order;
use App\Models\Product;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Overlay pencarian. Hasil diambil dari server, bukan disaring di peramban,
 * supaya katalog besar tetap ringan.
 */
class Pencarian extends Component
{
    public string $kata = '';

    #[On('buka-pencarian')]
    public function fokus(): void
    {
        $this->kata = '';
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        $produk = collect();
        $kategori = collect();

        if (mb_strlen(trim($this->kata)) >= 2) {
            $produk = Product::query()
                ->terbit()
                ->cari($this->kata)
                ->with(['media'])
                ->take(5)
                ->get();

            $kategori = \App\Models\Category::query()
                ->aktif()
                ->where('name_id', 'like', '%'.trim($this->kata).'%')
                ->take(4)
                ->get();
        }

        return view('livewire.front.pencarian', [
            'produk' => $produk,
            'kategori' => $kategori,
            'jumlahKeranjang' => app(\App\Services\Keranjang::class)->jumlahItem(),
        ]);
    }
}
