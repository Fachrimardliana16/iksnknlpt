<div class="relative">
    <label class="visually-hidden" for="cari-produk">{{ __('site.nav.search') }}</label>

    <div class="mt-4 flex items-center gap-2 border-b border-line pb-2">
        <x-ikon name="search" :size="20" class="flex-none text-dim" />
        <input id="cari-produk" type="search" autofocus
               class="w-full border-0 bg-transparent text-2xl font-display focus:outline-none sm:text-3xl"
               placeholder="{{ __('site.nav.search') }}"
               wire:model.live.debounce.300ms="kata">
    </div>

    @if (mb_strlen(trim($kata)) >= 2)
        <div class="mt-6">

            @if ($kategori->isNotEmpty())
                <p class="part-no">{{ __('site.nav.categories') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($kategori as $kat)
                        <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                           class="btn btn-outline min-h-[36px] px-3 text-sm">
                            <x-ikon name="tag" :size="14" />
                            {{ $kat->nama() }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($produk->isNotEmpty())
                <p class="part-no mt-6">{{ __('site.nav.products') }}</p>
                <ul class="mt-2 divide-y divide-line-soft">
                    @foreach ($produk as $p)
                        @php $m = $p->gambarUtama(); @endphp
                        <li>
                            <a href="{{ route('front.products.show', $p) }}" wire:navigate
                               class="flex items-center gap-3 py-3 hover:text-accent">
                                <span class="block h-12 w-16 flex-none overflow-hidden bg-sunken">
                                    @if ($m)
                                        <img src="{{ $m->url('thumb') }}" width="320" height="240"
                                             alt="{{ $m->alt() }}" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $p->judul() }}</span>
                                    <span class="tech block text-xs text-dim">{{ $p->sku }}</span>
                                </span>
                                <span class="tech flex-none text-sm">{{ rupiah($p->hargaMulai()) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('front.products.index', ['q' => $kata]) }}" class="btn btn-ghost mt-4">
                    {{ __('site.home.view_all_products') }}
                    <x-ikon name="arrow-right" :size="16" />
                </a>
            @else
                <div class="mt-6">
                    <p class="font-semibold">{{ __('site.product.empty') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('site.product.empty_hint') }}</p>
                </div>
            @endif
        </div>
    @else
        <p class="mt-6 text-sm text-dim">Ketik minimal 2 huruf untuk mencari.</p>
    @endif
</div>
