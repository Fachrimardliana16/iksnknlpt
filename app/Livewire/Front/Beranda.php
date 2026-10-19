<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Order;
use App\Models\Product;
use App\Support\CacheKatalog;
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

        // Produk unggulan diambil lewat cache (checklist F11-01).
        //
        // Halaman depan adalah halaman yang paling sering dibuka dan paling
        // jarang berubah, jadi seluruh isinya diingat selama 10 menit. Kunci
        // cache memuat nomor versi katalog, sehingga menyimpan produk dari
        // panel admin langsung membuat kunci lama tidak cocok — tanpa perlu
        // menghapus apa pun.
        //
        // Eager loading wajib ada DI DALAM cache: tanpa ini, kartu produk
        // melakukan kueri per media dan per varian, dan anggaran 15 kueri per
        // halaman terlampaui. Hasil yang tersimpan adalah model beserta
        // relasinya, sehingga halaman berikutnya tidak menyentuh basis data.
        // Ketiganya disimpan dalam SATU entri cache, bukan tiga.
        //
        // Penyimpan cache bawaan adalah basis data, jadi setiap kunci berarti
        // satu kueri pembacaan tersendiri. Tiga kunci = tiga kueri; satu entri
        // berisi ketiganya = satu kueri. Isinya tetap diingat 10 menit.
        $isi = CacheKatalog::ingat('home:isi', [], 600, fn () => [
            'unggulan' => Product::query()
                ->terbit()
                ->unggulan()
                ->with(['media', 'variants', 'category'])
                ->orderByDesc('published_at')
                ->take((int) $toko->angka('featured_count', 8))
                ->get(),

            // Hero memakai slide dari basis data. Bila tidak ada yang aktif,
            // home memakai slide bawaan berbasis teks, bukan gagal render.
            'slides' => HeroSlide::query()->tayang()->get(),

            'kategori' => Category::query()->aktif()->terurut()->take(6)->get(),
        ]);

        return view('livewire.front.beranda', [
            'slides' => $isi['slides'],
            'unggulan' => $isi['unggulan'],
            'batasKatalog' => $this->batasKatalogPonsel(),
            'kategori' => $isi['kategori'],
            'angka' => $this->angkaNyata(),
        ])->title($toko->teks('site_name').' · '.$toko->teks('site_tagline'));
    }

    /**
     * Jumlah kartu unggulan yang dirender sebelum "lihat semua" di ponsel.
     *
     * Delapan kartu dalam dua kolom berarti empat baris dan hampir dua layar
     * penuh hanya untuk katalog — halaman depan menjadi 6 layar panjangnya.
     * Tampilan penuh tetap tersedia lewat tombol, jadi memangkasnya di sini
     * tidak menghilangkan apa pun selain menggulir.
     */
    private function batasKatalogPonsel(): int
    {
        return max(2, (int) config('iksan.home.featured_mobile', 4));
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
