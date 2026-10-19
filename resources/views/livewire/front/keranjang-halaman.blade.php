<div>
    <div class="container-page py-8 lg:py-12">

        <span class="part-no">01 / {{ __('site.cart.title') }}</span>
        <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.cart.title') }}</h1>

        @if ($pesanStok)
            <div class="plate mt-5 flex items-start gap-2 border-l-2 border-warn p-3 text-sm text-warn" role="status">
                <x-ikon name="alert" :size="16" class="mt-0.5 flex-none" />
                <span>{{ $pesanStok }}</span>
            </div>
        @endif

        @if ($pesanKupon)
            <div class="plate mt-5 p-3 text-sm {{ $nadaKupon === 'danger' ? 'text-danger' : ($nadaKupon === 'warn' ? 'text-warn' : 'text-ok') }}">
                {{ $pesanKupon }}
            </div>
        @endif

        @if ($items->isEmpty())
            <div class="plate mt-8 p-10 text-center">
                <p class="font-semibold">{{ __('site.cart.empty') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('site.cart.empty_hint') }}</p>
                <a href="{{ route('front.products.index') }}" class="btn btn-primary mt-6">
                    {{ __('site.nav.products') }}
                </a>
            </div>
        @else
            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">

                <div class="space-y-4">
                    @foreach ($items as $item)
                        @php
                            $media = $item->product?->gambarUtama();
                            $maks = $item->stokMaksimal();
                        @endphp

                        <div class="plate flex gap-4 p-3 lg:p-4" wire:key="item-{{ $item->id }}">
                            <a href="{{ route('front.products.show', $item->product) }}" wire:navigate
                               class="block h-20 w-24 flex-none overflow-hidden bg-sunken sm:h-24 sm:w-32">
                                @if ($media)
                                    <img src="{{ $media->url('thumb') }}" width="320" height="240"
                                         alt="{{ $media->alt() }}" loading="lazy" decoding="async"
                                         class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full items-center justify-center text-dim">
                                        <x-ikon name="image" :size="20" />
                                    </span>
                                @endif
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col">
                                <p class="part-no">{{ $item->product?->category?->nama() }}</p>

                                <a href="{{ route('front.products.show', $item->product) }}" wire:navigate
                                   class="mt-1 line-clamp-2 font-semibold leading-snug">
                                    {{ $item->product?->judul() }}
                                </a>

                                <p class="tech mt-0.5 text-xs text-dim">
                                    {{ $item->variant?->nama() }}
                                    @if ($item->variant) · {{ $item->variant->sku_variant }} @endif
                                </p>

                                <p class="tech mt-2 text-sm">{{ rupiah($item->hargaSatuan()) }}</p>

                                @if ($maks <= 0)
                                    <p class="mt-1 text-xs text-danger">{{ __('site.product.out_of_stock') }}</p>
                                @elseif ($item->melebihiStok())
                                    <p class="mt-1 text-xs text-warn">
                                        {{ __('site.cart.stock_reduced', ['name' => $item->product?->judul(), 'count' => $maks]) }}
                                    </p>
                                @endif

                                <div class="mt-auto flex items-end justify-between gap-3 pt-3">
                                    {{-- Tombol jumlah 40x40px. Sebelumnya 36x36
                                         karena kelas !h-9 !w-9 menimpa ukuran
                                         bawaan btn-icon. --}}
                                    <div class="flex items-center">
                                        <button type="button" class="btn-icon !h-10 !w-10 !border !border-line"
                                                wire:click="ubahJumlah({{ $item->id }}, {{ $item->quantity - 1 }})">
                                            <x-ikon name="minus" :size="15" />
                                            <span class="visually-hidden">Kurangi</span>
                                        </button>

                                        <span class="tech w-12 text-center">{{ $item->quantity }}</span>

                                        <button type="button" class="btn-icon !h-10 !w-10 !border !border-line"
                                                wire:click="ubahJumlah({{ $item->id }}, {{ $item->quantity + 1 }})"
                                                @disabled($item->quantity >= $maks)>
                                            <x-ikon name="plus" :size="15" />
                                            <span class="visually-hidden">Tambah</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <p class="tech font-medium">{{ rupiah($item->subtotal()) }}</p>

                                        {{-- Hapus sengaja dipisah dari tombol
                                             jumlah: menekannya keliru tidak
                                             dapat dibatalkan, jadi jaraknya
                                             dijauhkan dan ukurannya dibuat
                                             penuh, bukan 30x36. --}}
                                        <button type="button" class="btn-icon !h-10 !w-10 text-danger"
                                                wire:click="hapusItem({{ $item->id }})"
                                                wire:confirm="{{ __('site.cart.remove') }}?">
                                            <x-ikon name="trash" :size="17" />
                                            <span class="visually-hidden">{{ __('site.cart.remove') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <a href="{{ route('front.products.index') }}" class="btn btn-ghost">
                        <x-ikon name="chevron-left" :size="16" />
                        {{ __('site.cart.continue_shopping') }}
                    </a>
                </div>

                {{-- Ringkasan. Di mobile ikut mengalir, bukan melayang menutupi konten. --}}
                <aside class="plate h-fit p-4 lg:sticky lg:top-24 lg:p-6">
                    <p class="part-no">02 / {{ __('site.checkout.summary') }}</p>

                    <div class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-muted">{{ __('site.cart.subtotal') }}</span>
                            <span class="tech">{{ rupiah($ringkasan['subtotal']) }}</span>
                        </div>

                        @if ($ringkasan['diskon'] > 0)
                            <div class="flex justify-between text-ok">
                                <span>{{ __('site.cart.discount') }}</span>
                                <span class="tech">-{{ rupiah($ringkasan['diskon']) }}</span>
                            </div>
                        @endif

                        @if ($ringkasan['ongkir'] > 0)
                            <div class="flex justify-between">
                                <span class="text-muted">{{ __('site.cart.shipping') }}</span>
                                <span class="tech">{{ rupiah($ringkasan['ongkir']) }}</span>
                            </div>
                        @endif

                        @if ($ringkasan['pajak'] > 0)
                            <div class="flex justify-between">
                                <span class="text-muted">{{ __('site.cart.tax') }}</span>
                                <span class="tech">{{ rupiah($ringkasan['pajak']) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="measure-line my-4"></div>

                    <div class="flex items-baseline justify-between">
                        <span class="font-semibold">{{ __('site.cart.total') }}</span>
                        <span class="tech text-2xl font-medium">{{ rupiah($ringkasan['total']) }}</span>
                    </div>

                    <div class="measure-line my-4"></div>

                    <label class="label" for="kupon">{{ __('site.cart.coupon') }}</label>
                    <div class="flex gap-2">
                        <input id="kupon" type="text" class="field uppercase" wire:model="kodeKupon"
                               autocomplete="off" placeholder="IKSAN10">
                        <button type="button" class="btn btn-outline flex-none" wire:click="terapkanKupon">
                            {{ __('site.cart.apply_coupon') }}
                        </button>
                    </div>

                    {{-- Kupon yang sedang menempel diberi jalan keluar sendiri.
                         Tanpa ini, kupon yang tidak lagi diinginkan hanya dapat
                         dilepas dengan mengosongkan kolom lalu menekan "Pakai"
                         — dan pengguna yang mengosongkan kolom lalu langsung
                         menekan checkout tetap membawa kuponnya. --}}
                    @if ($ringkasan['kupon'])
                        <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                            <span class="text-ok">{{ $ringkasan['kupon']->code }}</span>
                            <button type="button" class="text-muted underline hover:text-danger" wire:click="lepasKupon">
                                {{ __('site.cart.coupon_remove') }}
                            </button>
                        </div>
                    @endif

                    <div class="measure-line my-4"></div>

                    {{-- Catatan pembeli (checklist F7-03). Disimpan saat
                         meninggalkan kolom, dan diteruskan ke checkout. --}}
                    <label class="label" for="catatan">{{ __('site.cart.note') }}</label>
                    <textarea id="catatan" rows="2" class="field mt-1"
                              wire:model.blur="catatan" maxlength="500"
                              placeholder="{{ __('site.cart.note_hint') }}"></textarea>

                    <button type="button" class="btn btn-primary mt-6 w-full" wire:click="lanjutCheckout">
                        {{ __('site.cart.checkout') }}
                        <x-ikon name="arrow-right" :size="16" />
                    </button>
                </aside>
            </div>
        @endif
    </div>
</div>
