<div>
    <div class="container-page py-8 lg:py-12">

        <nav class="part-no mb-4" aria-label="Breadcrumb">
            <a href="{{ route('front.home') }}" class="hover:text-accent">{{ __('site.nav.home') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('front.products.index') }}" class="hover:text-accent">{{ __('site.nav.products') }}</a>
            @if ($kategoriAktif)
                <span class="mx-2">/</span>
                <span>{{ $kategoriAktif->nama() }}</span>
            @endif
        </nav>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">01 / {{ __('site.catalog.title') }}</span>
                <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">
                    {{ $kategoriAktif?->nama() ?? __('site.catalog.title') }}
                </h1>
                <p class="tech mt-2 text-sm text-muted">
                    {{ __('site.catalog.result_count', ['count' => $daftar->total()]) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                {{-- Filter di mobile dibuka sebagai panel, bukan disembunyikan. --}}
                <button type="button" class="btn btn-outline lg:hidden" wire:click="$toggle('filterTerbuka')">
                    <x-ikon name="filter" :size="16" />
                    {{ __('site.catalog.filter') }}
                </button>

                <label class="sr-only" for="urut">{{ __('site.catalog.sort') }}</label>
                <select id="urut" class="field !w-auto" wire:model.live="urut">
                    <option value="newest">{{ __('site.catalog.sort_newest') }}</option>
                    <option value="price_asc">{{ __('site.catalog.sort_price_asc') }}</option>
                    <option value="price_desc">{{ __('site.catalog.sort_price_desc') }}</option>
                    <option value="name">{{ __('site.catalog.sort_name') }}</option>
                </select>
            </div>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-[16rem_1fr]">

            {{-- Panel filter. Desktop: kolom tetap. Mobile: panel yang bisa dibuka. --}}
            <aside class="{{ $filterTerbuka ? 'block' : 'hidden' }} lg:block">
                <div class="plate p-4 lg:sticky lg:top-24">

                    <label class="label" for="kata">{{ __('common.search') }}</label>
                    <input id="kata" type="search" class="field" wire:model.live.debounce.400ms="kata"
                           placeholder="{{ __('site.nav.search') }}">

                    <div class="measure-line my-4"></div>

                    <p class="label">{{ __('site.nav.categories') }}</p>
                    <ul class="space-y-1">
                        <li>
                            <button type="button" wire:click="$set('kategoriSlug', '')"
                                    class="min-h-[36px] w-full text-left text-sm {{ $kategoriSlug === '' ? 'text-accent' : 'text-muted' }}">
                                {{ __('common.all') }}
                            </button>
                        </li>
                        @foreach ($semuaKategori as $kat)
                            <li>
                                <button type="button" wire:click="$set('kategoriSlug', '{{ $kat->slug }}')"
                                        class="min-h-[36px] w-full text-left text-sm {{ $kategoriSlug === $kat->slug ? 'text-accent' : 'text-muted' }}">
                                    {{ $kat->nama() }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="measure-line my-4"></div>

                    <p class="label">{{ __('site.catalog.price_range') }}</p>
                    <div class="flex items-center gap-2">
                        <input type="number" inputmode="numeric" class="field !min-h-[40px]" placeholder="{{ __('site.catalog.min') }}"
                               wire:model.live.debounce.600ms="hargaMin">
                        <span class="text-dim">-</span>
                        <input type="number" inputmode="numeric" class="field !min-h-[40px]" placeholder="{{ __('site.catalog.max') }}"
                               wire:model.live.debounce.600ms="hargaMaks">
                    </div>

                    <button type="button" class="btn btn-ghost mt-4 w-full" wire:click="bersihkan">
                        <x-ikon name="refresh" :size="16" />
                        {{ __('site.catalog.reset') }}
                    </button>
                </div>
            </aside>

            <div>
                @if ($daftar->isEmpty())
                    <div class="plate p-8 text-center">
                        <p class="font-semibold">{{ __('site.product.empty') }}</p>
                        <p class="mt-1 text-sm text-muted">{{ __('site.product.empty_hint') }}</p>
                        <button type="button" class="btn btn-outline mt-5" wire:click="bersihkan">
                            {{ __('site.catalog.reset') }}
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-6">
                        @foreach ($daftar as $produk)
                            <x-kartu-produk :produk="$produk" />
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $daftar->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
