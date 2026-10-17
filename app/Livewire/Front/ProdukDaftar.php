<?php

namespace App\Livewire\Front;

use App\Models\Product;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProdukDaftar extends Component
{
    use WithPagination;

    #[Url(as: 'kategori', except: '')]
    public string $kategoriSlug = '';

    #[Url(as: 'q', except: '')]
    public string $kata = '';

    #[Url(as: 'urut', except: 'newest')]
    public string $urut = 'newest';

    #[Url(as: 'min', except: '')]
    public string $hargaMin = '';

    #[Url(as: 'max', except: '')]
    public string $hargaMaks = '';

    public int $perHalaman = 12;

    public bool $filterTerbuka = false;

    public function updated(string $properti): void
    {
        // Setiap perubahan filter mengembalikan ke halaman pertama. Tanpa ini,
        // mempersempit filter dari halaman 3 menghasilkan daftar kosong.
        if ($properti !== 'perHalaman') {
            $this->resetPage();
        }
    }

    public function bersihkan(): void
    {
        $this->reset(['kategoriSlug', 'kata', 'urut', 'hargaMin', 'hargaMaks']);
        $this->urut = 'newest';
        $this->resetPage();
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();

        $kategori = $toko && $this->kategoriSlug !== ''
            ? \App\Models\Category::query()->where('slug', $this->kategoriSlug)->first()
            : null;

        $daftar = Product::query()
            ->terbit()
            ->with(['media', 'variants', 'category'])
            ->when($kategori !== null, fn ($q) => $q->where('category_id', $kategori->id))
            ->cari($this->kata)
            ->when($this->hargaMin !== '' && is_numeric($this->hargaMin), fn ($q) => $q->where('base_price', '>=', (float) $this->hargaMin))
            ->when($this->hargaMaks !== '' && is_numeric($this->hargaMaks), fn ($q) => $q->where('base_price', '<=', (float) $this->hargaMaks))
            ->when($this->urut === 'price_asc', fn ($q) => $q->orderBy('base_price'))
            ->when($this->urut === 'price_desc', fn ($q) => $q->orderByDesc('base_price'))
            ->when($this->urut === 'name', fn ($q) => $q->orderBy('title_id'))
            ->when($this->urut === 'newest', fn ($q) => $q->orderByDesc('published_at'))
            ->paginate($this->perHalaman);

        return view('livewire.front.produk-daftar', [
            'daftar' => $daftar,
            'kategoriAktif' => $kategori,
            'semuaKategori' => \App\Models\Category::query()->aktif()->terurut()->get(),
        ])->title(__('site.catalog.title').' · '.$toko->teks('site_name'));
    }
}
