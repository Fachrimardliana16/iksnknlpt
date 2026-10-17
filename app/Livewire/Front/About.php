<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class About extends Component
{
    public function render(): View
    {
        $toko = Pengaturan::instance();

        return view('livewire.front.about', [
            'angka' => [
                'produk' => Product::query()->terbit()->count(),
                'pesanan' => Order::query()->where('status', 'completed')->count(),
                'kategori' => Category::query()->aktif()->count(),
            ],
        ])->title(__('site.nav.about').' · '.$toko->teks('site_name'));
    }
}
