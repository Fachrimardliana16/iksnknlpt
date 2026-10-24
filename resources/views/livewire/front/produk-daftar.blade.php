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
                <p class="tech mt-2 text-sm text-muted" aria-live="polite">
                    {{ __('site.catalog.result_count', ['count' => $daftar->total()]) }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                {{-- Filter di mobile dibuka sebagai panel, bukan disembunyikan.
                     Angka pada tombol memberi tahu berapa saringan yang menyala,
                     karena panel yang tertutup tidak memperlihatkan apa pun. --}}
                <button type="button" class="btn btn-outline lg:hidden" wire:click="$toggle('filterTerbuka')"
                        aria-expanded="{{ $filterTerbuka ? 'true' : 'false' }}"
                        aria-controls="panel-filter">
                    <x-ikon name="filter" :size="16" />
                    {{ __('site.catalog.filter') }}
                    @if ($jumlahFilterAktif > 0)
                        <span class="badge badge-accent">{{ $jumlahFilterAktif }}</span>
                    @endif
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

        {{-- Saringan yang sedang menyala, dalam bentuk yang dapat dilepas satu
             per satu. Ini yang membuat keadaan hasil kosong dapat ditindaklanjuti:
             pembeli melihat sendiri penyebab daftarnya kosong. --}}
        @if ($jumlahFilterAktif > 0)
            <div class="mt-5 flex flex-wrap items-center gap-2">
                <span class="part-no">{{ __('site.catalog.active_filters') }}</span>

                @if (filled($kata))
                    <button type="button" wire:click="$set('kata', '')" class="badge badge-outline gap-1">
                        "{{ $kata }}"
                        <x-ikon name="close" :size="12" />
                        <span class="visually-hidden">{{ __('site.catalog.remove_filter') }}</span>
                    </button>
                @endif

                @if ($kategoriAktif)
                    <button type="button" wire:click="$set('kategoriSlug', '')" class="badge badge-outline gap-1">
                        {{ $kategoriAktif->nama() }}
                        <x-ikon name="close" :size="12" />
                        <span class="visually-hidden">{{ __('site.catalog.remove_filter') }}</span>
                    </button>
                @endif

                @if (filled($hargaMin) || filled($hargaMaks))
                    <button type="button" wire:click="$set('hargaMin', ''); $set('hargaMaks', '')" class="badge badge-outline gap-1">
                        {{ __('site.catalog.price_range') }}: {{ filled($hargaMin) ? rupiah($hargaMin) : '0' }} – {{ filled($hargaMaks) ? rupiah($hargaMaks) : '∞' }}
                        <x-ikon name="close" :size="12" />
                        <span class="visually-hidden">{{ __('site.catalog.remove_filter') }}</span>
                    </button>
                @endif

                <button type="button" class="text-xs text-muted underline hover:text-accent" wire:click="bersihkan">
                    {{ __('site.catalog.reset') }}
                </button>
            </div>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[16rem_1fr]">

            {{-- Panel filter. Desktop: kolom tetap. Mobile: panel yang bisa dibuka. --}}
            <aside id="panel-filter" class="{{ $filterTerbuka ? 'block' : 'hidden' }} lg:block">
                <div class="plate p-4 lg:sticky lg:top-24">

                    <label class="label" for="kata">{{ __('site.common.search') }}</label>
                    <input id="kata" type="search" class="field" wire:model.live.debounce.400ms="kata"
                           placeholder="{{ __('site.nav.search') }}">

                    @error('kata')
                        <p class="mt-2 text-xs text-danger">{{ $message }}</p>
                    @enderror

                    <div class="measure-line my-4"></div>

                    <p class="label">{{ __('site.nav.categories') }}</p>
                    <ul class="space-y-1">
                        <li>
                            <button type="button" wire:click="$set('kategoriSlug', '')"
                                    class="min-h-[36px] w-full text-left text-sm {{ $kategoriSlug === '' ? 'text-accent' : 'text-muted' }}">
                                {{ __('site.common.all') }}
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
                        <label class="sr-only" for="harga-min">{{ __('site.catalog.min') }}</label>
                        <input id="harga-min" type="number" inputmode="numeric" class="field !min-h-[40px]" placeholder="{{ __('site.catalog.min') }}"
                               wire:model.live.debounce.600ms="hargaMin">
                        <span class="text-dim">-</span>
                        <label class="sr-only" for="harga-maks">{{ __('site.catalog.max') }}</label>
                        <input id="harga-maks" type="number" inputmode="numeric" class="field !min-h-[40px]" placeholder="{{ __('site.catalog.max') }}"
                               wire:model.live.debounce.600ms="hargaMaks">
                    </div>

                    <button type="button" class="btn btn-ghost mt-4 w-full" wire:click="bersihkan">
                        <x-ikon name="refresh" :size="16" />
                        {{ __('site.catalog.reset') }}
                    </button>
                </div>
            </aside>

            <div>
                {{-- Keadaan memuat. Kerangka kartu, bukan pemintal di tengah layar:
                     kerangka menjaga tinggi halaman sehingga isi di bawahnya tidak
                     melompat saat hasilnya tiba, dan pembeli tetap melihat bentuk
                     hasil yang sedang menunggu. --}}
                <div wire:loading.delay.long wire:target="kata,kategoriSlug,hargaMin,hargaMaks,urut,gotoPage,nextPage,previousPage,bersihkan">
                    <p class="visually-hidden" role="status">{{ __('site.catalog.loading') }}</p>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 lg:gap-6" aria-hidden="true">
                        @foreach (range(1, 6) as $i)
                            <div class="plate overflow-hidden">
                                <div class="aspect-[4/3] animate-pulse bg-sunken"></div>
                                <div class="space-y-2 p-3 lg:p-4">
                                    <div class="h-3 w-1/3 animate-pulse rounded bg-sunken"></div>
                                    <div class="h-4 w-4/5 animate-pulse rounded bg-sunken"></div>
                                    <div class="h-3 w-1/4 animate-pulse rounded bg-sunken"></div>
                                    <div class="h-5 w-2/5 animate-pulse rounded bg-sunken"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div wire:loading.remove wire:target="kata,kategoriSlug,hargaMin,hargaMaks,urut,gotoPage,nextPage,previousPage,bersihkan">
                    @if ($daftar->isEmpty())
                        {{-- Keadaan kosong yang menjelaskan sebabnya, bukan kartu
                             yang cuma bilang "tidak ada". --}}
                        <div class="plate p-8 text-center">
                            <x-ikon name="search" :size="32" class="mx-auto text-dim" />
                            <p class="mt-4 font-semibold">{{ __('site.product.empty') }}</p>
                            <p class="mt-1 text-sm text-muted">
                                @if ($jumlahFilterAktif > 1)
                                    {{ __('site.catalog.empty_multi', ['count' => $jumlahFilterAktif]) }}
                                @elseif (filled($kata))
                                    {{ __('site.catalog.empty_keyword', ['kata' => $kata]) }}
                                @elseif ($kategoriAktif)
                                    {{ __('site.catalog.empty_category', ['kategori' => $kategoriAktif->nama()]) }}
                                @else
                                    {{ __('site.product.empty_hint') }}
                                @endif
                            </p>

                            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                                <button type="button" class="btn btn-outline" wire:click="bersihkan">
                                    <x-ikon name="refresh" :size="16" />
                                    {{ __('site.catalog.reset') }}
                                </button>
                                @if ($kategoriAktif)
                                    <button type="button" class="btn btn-ghost" wire:click="$set('kategoriSlug', '')">
                                        {{ __('site.catalog.see_all') }}
                                    </button>
                                @endif
                            </div>
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
</div>
