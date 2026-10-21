<?php

namespace App\Livewire\Front;

use App\Models\Category;
use App\Models\Product;
use App\Support\CacheKatalog;
use App\Support\Pengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman kategori: /kategori/{slug}.
 *
 * Kenapa halaman tersendiri, bukan hanya `/produk?kategori=...`:
 *
 * Parameter pencarian mengaburkan topik. "knalpot vario" yang dijawab oleh satu
 * URL dengan judul, deskripsi, dan remah sendiri lebih mudah dipahami mesin
 * pencari daripada satu halaman katalog raksasa yang isinya berubah menurut
 * parameter. Halaman arahan per kategori juga satu-satunya tempat yang wajar
 * untuk menulis pengantar singkat — dan pengantar itulah yang dikutip mesin
 * jawaban saat orang bertanya "knalpot apa yang cocok untuk Vario 160".
 *
 * Isi pengantar dibangun dari data kategori + jumlah produk yang benar-benar
 * ada. Tidak ada teks karangan: kategori kosong tidak mendapat halaman.
 */
class Kategori extends Component
{
    use WithPagination;

    public string $slug = '';

    #[Url(as: 'urut', except: '')]
    public string $urut = 'terbaru';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function updatedUrut(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $toko = app(Pengaturan::class);

        $kategori = Category::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->first();

        // Kategori tidak ada atau tidak aktif: 404 sungguhan, bukan halaman
        // kosong. Halaman kosong yang diindeks menurunkan mutu domain.
        abort_if($kategori === null, 404);

        $produk = Product::query()
            ->terbit()
            ->where('category_id', $kategori->id)
            ->with(['media', 'variants', 'category'])
            ->when($this->urut === 'termurah', fn ($q) => $q->orderBy('base_price'))
            ->when($this->urut === 'termahal', fn ($q) => $q->orderByDesc('base_price'))
            ->when($this->urut === 'populer', fn ($q) => $q->orderByDesc('sold_count'))
            ->when($this->urut === 'terbaru', fn ($q) => $q->orderByDesc('published_at'))
            ->paginate(12);

        $jumlah = $produk->total();

        $namaKategori = $kategori->nama();

        // Deskripsi: memakai deskripsi kategori bila ada. Bila kosong, disusun
        // dari fakta yang pasti — jumlah produk dan nama toko. Tidak mengarang
        // keunggulan yang tidak dapat dibuktikan.
        $deskripsi = trim((string) $kategori->deskripsi());

        if ($deskripsi === '') {
            $deskripsi = $jumlah > 0
                ? "{$jumlah} pilihan {$namaKategori} di {$toko->teks('site_name', 'Iksan Knalpot')}, "
                    .'dirakit di bengkel sendiri dan dikirim ke seluruh Indonesia.'
                : "{$namaKategori} di {$toko->teks('site_name', 'Iksan Knalpot')}.";
        }

        $ringkasan = "{$namaKategori} dari {$toko->teks('site_name', 'Iksan Knalpot')}: "
            ."{$jumlah} produk tersedia, mulai ".rupiah($this->hargaTerendah($kategori)).'. '.$deskripsi;

        return view('livewire.front.kategori', [
            'kategori' => $kategori,
            'produk' => $produk,
            'jumlah' => $jumlah,
            'deskripsi' => $deskripsi,
        ])
            ->title($namaKategori.' · '.$toko->teks('site_name'))
            ->layoutData([
                'kanonikal' => route('front.category', $kategori->slug),
                'deskripsi' => $deskripsi,
                'ringkasanAi' => $ringkasan,

                // Remah kategori: mesin pencari memakainya untuk menampilkan
                // jalur, dan mesin jawaban memakainya untuk tahu posisi
                // halaman ini dalam struktur situs.
                'remah' => [
                    ['nama' => 'Beranda', 'tautan' => route('front.home')],
                    ['nama' => 'Produk', 'tautan' => route('front.products.index')],
                    ['nama' => $namaKategori, 'tautan' => route('front.category', $kategori->slug)],
                ],

                'metaOg' => [
                    'tipe' => 'website',
                    'judul' => $namaKategori,
                    'deskripsi' => $deskripsi,
                    'gambar' => $kategori->image_path
                        ? url(\App\Models\ProductMedia::urlMedia($kategori->image_path))
                        : null,
                    'alt' => $namaKategori,
                ],
            ]);
    }

    private function hargaTerendah(Category $kategori): float
    {
        return (float) (Product::query()
            ->terbit()
            ->where('category_id', $kategori->id)
            ->min('base_price') ?? 0);
    }
}
