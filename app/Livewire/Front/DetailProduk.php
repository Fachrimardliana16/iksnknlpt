<?php

namespace App\Livewire\Front;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Keranjang;
use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DetailProduk extends Component
{
    public Product $product;

    public ?int $variantId = null;

    public int $jumlah = 1;

    public ?string $pesan = null;

    public string $nada = 'ok';

    public function mount(Product $product): void
    {
        abort_unless($product->is_published, 404);

        $this->product = $product->load(['media', 'variants', 'category']);

        // Pilih varian pertama yang masih ada stoknya, supaya pembeli tidak
        // diarahkan ke varian habis secara default.
        $this->variantId = $this->product->variants
            ->where('is_active', true)
            ->sortBy('display_order')
            ->firstWhere(fn (ProductVariant $v) => $v->stokTersedia() > 0)
            ?->id
            ?? $this->product->variants->where('is_active', true)->first()?->id;

        // Dihitung sekali per tampilan, bukan per render. Penulisan per render
        // akan menghasilkan ribuan UPDATE per hari untuk satu produk populer.
        $this->product->increment('view_count');
    }

    public function pilihVarian(int $id): void
    {
        $this->variantId = $id;
        $this->jumlah = 1;
        $this->pesan = null;
    }

    public function tambah(Keranjang $keranjang, bool $langsungCheckout = false): void
    {
        $varian = $this->varian();

        if ($varian === null) {
            $this->pesan = __('site.product.out_of_stock');
            $this->nada = 'danger';

            return;
        }

        $akhir = $keranjang->tambah($varian, max(1, $this->jumlah));

        if ($akhir <= 0) {
            $this->pesan = __('site.product.out_of_stock');
            $this->nada = 'danger';

            return;
        }

        // Komponen lain (navbar, bar navigasi) memperbarui badge tanpa reload.
        $this->dispatch('keranjang-berubah');

        if ($langsungCheckout) {
            $this->redirect(route('front.checkout'), navigate: true);

            return;
        }

        $this->pesan = __('site.product.add_to_cart').' ('.$akhir.')';
        $this->nada = 'ok';
    }

    #[On('keranjang-berubah')]
    public function segarkan(): void
    {
        //
    }

    private function varian(): ?ProductVariant
    {
        if ($this->variantId === null) {
            return null;
        }

        return $this->product->variants
            ->firstWhere('id', $this->variantId);
    }

    public function render(): View
    {
        $toko = Pengaturan::instance();
        $varian = $this->varian();

        $terkait = Product::query()
            ->terbit()
            ->where('category_id', $this->product->category_id)
            ->whereKeyNot($this->product->id)
            ->with(['media', 'variants'])
            ->take(4)
            ->get();

        return view('livewire.front.detail-produk', [
            'varian' => $varian,
            'terkait' => $terkait,
            'tersedia' => $varian?->stokTersedia() ?? 0,
            'harga' => $varian?->hargaAkhir() ?? $this->product->hargaMulai(),
        ])->title($this->product->judul().' · '.$toko->teks('site_name'));
    }
}
