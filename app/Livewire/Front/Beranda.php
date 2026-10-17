<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Order;
use App\Models\Product;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.front')]
class Beranda extends Component
{
    public function render(): View
    {
        $toko = Pengaturan::instance();

        // Eager loading wajib: tanpa ini, kartu produk melakukan kueri per
        // media dan per varian, dan anggaran 15 kueri per halaman terlampaui.
        $unggulan = Product::query()
            ->terbit()
            ->unggulan()
            ->with(['media', 'variants', 'category'])
            ->orderByDesc('published_at')
            ->take((int) $toko->angka('featured_count', 8))
            ->get();

        // Hero memakai slide dari basis data. Bila tidak ada yang aktif, home
        // memakai slide bawaan berbasis teks, bukan gagal render.
        $slides = HeroSlide::query()->tayang()->get();

        return view('livewire.front.beranda', [
            'slides' => $slides,
            'unggulan' => $unggulan,
            'kategori' => Category::query()->aktif()->terurut()->take(6)->get(),
            'angka' => $this->angkaNyata(),
        ])->title($toko->teks('site_name').' · '.$toko->teks('site_tagline'));
    }

    /**
     * Angka pada section About. Diambil dari basis data, bukan ditulis di
     * template. Bila datanya nol, bagian itu tidak ditampilkan sama sekali
     * daripada menampilkan nol yang menyesatkan.
     *
     * @return array<string, int>
     */
    private function angkaNyata(): array
    {
        return [
            'produk' => Product::query()->terbit()->count(),
            'pesanan' => Order::query()->where('status', 'completed')->count(),
            'kategori' => Category::query()->aktif()->count(),
        ];
    }
}
