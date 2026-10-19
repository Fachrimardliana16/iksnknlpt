<div>
    <div class="container-page py-8 lg:py-12">

        <nav class="part-no mb-6" aria-label="Breadcrumb">
            <a href="{{ route('front.home') }}" class="hover:text-accent">{{ __('site.nav.home') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('front.products.index') }}" class="hover:text-accent">{{ __('site.nav.products') }}</a>
            <span class="mx-2">/</span>
            <span>{{ $product->category?->nama() }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_1fr] lg:gap-12">

            {{-- Galeri --}}
            <div x-data="{ aktif: 0 }">
                @php $mediaUtama = $product->gambarUtama(); @endphp

                <div class="plate aspect-[4/3] overflow-hidden">
                    @if ($mediaUtama)
                        <img x-ref="utama"
                             src="{{ $mediaUtama->url('full') }}"
                             width="{{ $mediaUtama->width ?: 1600 }}" height="{{ $mediaUtama->height ?: 1200 }}"
                             alt="{{ $mediaUtama->alt() }}"
                             fetchpriority="high" decoding="async"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-dim">
                            <x-ikon name="image" :size="40" />
                        </div>
                    @endif
                </div>

                @if ($product->media->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($product->media as $i => $m)
                            <button type="button" @click="aktif = {{ $i }}; $refs.utama.src = '{{ $m->url('full') }}'"
                                    class="aspect-[4/3] overflow-hidden border {{ $i === 0 ? 'border-accent' : 'border-line-soft' }}">
                                <img src="{{ $m->url('thumb') }}" width="320" height="240"
                                     alt="{{ $m->alt() }}" loading="lazy" decoding="async"
                                     class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Informasi dan pembelian --}}
            <div class="lg:sticky lg:top-24 lg:self-start">
                <p class="part-no">{{ $product->category?->nama() }}</p>

                <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ $product->judul() }}</h1>

                <p class="tech mt-2 text-sm text-dim">
                    {{ __('site.product.sku') }}: {{ $product->sku }}
                </p>

                <p class="tech mt-5 text-3xl font-medium">{{ rupiah($harga) }}</p>

                @if ($tersedia > 0)
                    <p class="mt-1 text-sm text-ok">{{ __('site.product.in_stock', ['count' => $tersedia]) }}</p>
                @else
                    <p class="mt-1 text-sm text-danger">{{ __('site.product.out_of_stock') }}</p>
                @endif

                @if ($product->variants->where('is_active', true)->count() > 1)
                    <div class="measure-line my-6"></div>
                    <p class="label">{{ __('site.product.variant') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->variants->where('is_active', true)->sortBy('display_order') as $v)
                            <button type="button" wire:click="pilihVarian({{ $v->id }})"
                                    @disabled($v->habis())
                                    class="btn min-h-[40px] px-3 text-sm
                                        {{ $variantId === $v->id ? 'btn-primary' : 'btn-outline' }}
                                        {{ $v->habis() ? '!opacity-40' : '' }}">
                                {{ $v->nama() }}
                                @if ($v->price_adjustment != 0)
                                    <span class="tech text-xs">
                                        {{ $v->price_adjustment > 0 ? '+' : '' }}{{ number_format((float) $v->price_adjustment, 0, ',', '.') }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="measure-line my-6"></div>

                <div class="flex items-end gap-3">
                    <div>
                        <label class="label" for="jumlah">{{ __('site.product.quantity') }}</label>
                        <div class="flex items-center">
                            {{-- aria-label wajib: isinya hanya gambar, sehingga tanpa ini
                                 pembaca layar hanya membacakan "tombol". --}}
                            <button type="button" class="btn-icon !border !border-line"
                                    aria-label="{{ __('site.product.decrease_qty') }}"
                                    wire:click="$set('jumlah', {{ max(1, $jumlah - 1) }})">
                                <x-ikon name="minus" :size="16" />
                            </button>
                            <input id="jumlah" type="number" min="1" max="{{ max(1, $tersedia) }}"
                                   class="field !w-20 !min-h-[44px] text-center"
                                   wire:model.live="jumlah">
                            <button type="button" class="btn-icon !border !border-line"
                                    aria-label="{{ __('site.product.increase_qty') }}"
                                    wire:click="$set('jumlah', {{ min(max(1, $tersedia), $jumlah + 1) }})">
                                <x-ikon name="plus" :size="16" />
                            </button>
                        </div>
                    </div>
                </div>

                @if ($pesan)
                    <div class="plate mt-4 p-3 text-sm {{ $nada === 'danger' ? 'text-danger' : 'text-ok' }}">
                        {{ $pesan }}
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <button type="button" class="btn btn-primary flex-1" wire:click="tambah"
                            @disabled($tersedia < 1)>
                        {{ __('site.product.add_to_cart') }}
                    </button>
                    <button type="button" class="btn btn-outline flex-1" wire:click="tambah(true)"
                            @disabled($tersedia < 1)>
                        {{ __('site.product.buy_now') }}
                    </button>
                </div>

                <div class="measure-line my-6"></div>

                <div>
                    <p class="label">{{ __('site.product.description') }}</p>
                    <div class="mt-2 space-y-3 text-sm leading-relaxed text-muted">
                        @foreach (preg_split('/\n\s*\n/', trim($product->deskripsi())) as $paragraf)
                            <p>{{ trim($paragraf) }}</p>
                        @endforeach
                    </div>
                </div>

                <div class="measure-line my-6"></div>

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="part-no">{{ __('site.product.sku') }}</dt>
                        <dd class="tech mt-1">{{ $product->sku }}</dd>
                    </div>
                    <div>
                        <dt class="part-no">Berat</dt>
                        <dd class="tech mt-1">{{ number_format(($variant?->berat() ?? $product->weight_gram) / 1000, 2, ',', '.') }} kg</dd>
                    </div>
                    <div>
                        <dt class="part-no">Varian</dt>
                        <dd class="tech mt-1">{{ $variant?->nama() ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="part-no">{{ __('site.footer.categories') }}</dt>
                        <dd class="tech mt-1">{{ $product->category?->nama() }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Bar aksi lengket. Hanya di bawah 1024px, tempat keranjang paling
             mungkin terlewat karena tombol ikut tergulir keluar layar. --}}
        <div class="fixed inset-x-0 z-30 border-t border-line-soft bg-surface p-3 lg:hidden"
             style="bottom: calc(60px + env(safe-area-inset-bottom, 0px))">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="tech truncate text-lg font-medium">{{ rupiah($harga) }}</p>
                    <p class="truncate text-xs text-muted">{{ $product->judul() }}</p>
                </div>
                <button type="button" class="btn btn-primary flex-none" wire:click="tambah"
                        @disabled($tersedia < 1)>
                    {{ __('site.product.add_to_cart') }}
                </button>
            </div>
        </div>

        @if ($terkait->isNotEmpty())
            <section class="mt-24 pb-24 lg:pb-0">
                <span class="part-no">02 / {{ __('site.product.related') }}</span>
                <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
                    @foreach ($terkait as $p)
                        <x-kartu-produk :produk="$p" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
