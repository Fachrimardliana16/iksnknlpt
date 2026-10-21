<div>
    <div class="container-page py-8 lg:py-12">

        {{-- Remah: juga jalur bagi pembaca layar, bukan hiasan. --}}
        <nav class="part-no mb-6" aria-label="Breadcrumb">
            <a href="{{ route('front.home') }}" wire:navigate class="hover:text-accent">Beranda</a>
            <span class="mx-2">/</span>
            <a href="{{ route('front.products.index') }}" wire:navigate class="hover:text-accent">Produk</a>
            <span class="mx-2">/</span>
            <span aria-current="page">{{ $kategori->nama() }}</span>
        </nav>

        {{-- Kepala kategori.
             H1 di sini satu-satunya <h1> di halaman; sebelumnya judul produk
             di kartu memakai h3 sehingga halaman kategori tidak punya judul
             utama sama sekali dan mesin pencari harus menebak topiknya. --}}
        <header class="max-w-3xl">
            <h1 class="text-2xl font-semibold leading-tight lg:text-4xl">{{ $kategori->nama() }}</h1>

            <p class="mt-3 text-sm leading-relaxed text-muted lg:text-base">{{ $deskripsi }}</p>

            <p class="part-no mt-3">
                {{ $jumlah }} {{ __('site.product.variant') === 'Varian' ? 'produk' : 'produk' }}
            </p>
        </header>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            <label class="part-no" for="urut">Urutkan</label>

            <select id="urut" class="field !w-auto !min-h-[44px]" wire:model.live="urut">
                <option value="terbaru">Terbaru</option>
                <option value="termurah">Termurah</option>
                <option value="termahal">Termahal</option>
                <option value="populer">Terpopuler</option>
            </select>

            <a href="{{ route('front.products.index') }}" wire:navigate
               class="ml-auto text-sm text-muted underline underline-offset-4 hover:text-accent">
                Lihat semua produk
            </a>
        </div>

        @if ($produk->isEmpty())
            <div class="plate mt-8 p-8 text-center">
                <p class="text-muted">Belum ada produk pada kategori ini.</p>
                <a href="{{ route('front.products.index') }}" wire:navigate class="btn btn-outline mt-4 inline-flex">
                    Lihat katalog
                </a>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-6">
                @foreach ($produk as $p)
                    @include('components.kartu-produk', ['produk' => $p])
                @endforeach
            </div>

            <div class="mt-8">
                {{ $produk->links() }}
            </div>
        @endif
    </div>
</div>
