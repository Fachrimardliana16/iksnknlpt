<div>
    <div class="container-page py-8 lg:py-12">
        <div class="mx-auto max-w-2xl">

            <span class="part-no">01 / {{ __('site.order.track') }}</span>
            <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.order.track') }}</h1>
            <p class="mt-2 text-sm text-muted">{{ __('site.order.track_hint') }}</p>

            <form wire:submit="lacak" class="plate mt-8 p-4 lg:p-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="nomor">{{ __('site.order.number') }}</label>
                        <input id="nomor" type="text" class="field uppercase" wire:model="nomor"
                               placeholder="IK-20261017-XXXXXX">
                        @error('nomor') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="label" for="email">{{ __('site.checkout.email') }}</label>
                        <input id="email" type="email" class="field" wire:model="email">
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-5 w-full sm:w-auto" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="lacak">{{ __('site.order.track_submit') }}</span>
                    <span wire:loading wire:target="lacak">{{ __('site.common.loading') }}</span>
                </button>

                @if ($galat)
                    <p class="field-error mt-3">{{ $galat }}</p>
                @endif
            </form>

            @foreach ($hasil as $data)
                <div class="plate mt-6 p-4 lg:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="tech text-lg font-medium">{{ $data['nomor'] }}</p>
                        <x-lencana :nada="$data['status']->nada()" :teks="$data['status']->label()" />
                    </div>

                    <div class="measure-line my-4"></div>

                    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                        <div>
                            <dt class="part-no">{{ __('site.cart.total') }}</dt>
                            <dd class="tech mt-1">{{ rupiah($data['total']) }}</dd>
                        </div>
                        <div>
                            <dt class="part-no">Item</dt>
                            <dd class="tech mt-1">{{ $data['item'] }}</dd>
                        </div>
                        <div>
                            <dt class="part-no">{{ __('site.checkout.courier') }}</dt>
                            <dd class="tech mt-1">{{ $data['kurir'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="part-no">Resi</dt>
                            <dd class="tech mt-1">{{ $data['resi'] ?? '-' }}</dd>
                        </div>
                    </dl>

                    @if (count($data['riwayat']) > 0)
                        <div class="measure-line my-5"></div>
                        <p class="part-no">Riwayat</p>

                        <ol class="mt-3 space-y-3">
                            @foreach ($data['riwayat'] as $riwayat)
                                <li class="flex gap-3 text-sm">
                                    <span class="tech flex-none text-xs text-dim">
                                        {{ $riwayat->created_at?->format('d/m H:i') }}
                                    </span>
                                    <span>
                                        <span class="font-medium">{{ $riwayat->to_status->label() }}</span>
                                        @if ($riwayat->note)
                                            <span class="block text-muted">{{ $riwayat->note }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <a href="{{ $data['url'] }}" class="btn btn-outline mt-5">{{ __('site.order.print_invoice') }}</a>
                </div>
            @endforeach
        </div>
    </div>
</div>
