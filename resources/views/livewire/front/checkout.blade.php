<div>
    <div class="container-page py-8 lg:py-12">

        <span class="part-no">01 / {{ __('site.checkout.title') }}</span>
        <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.checkout.title') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('site.checkout.guest_note') }}</p>

        @if ($galat)
            <div class="plate mt-6 border-l-2 border-l-danger p-4 text-sm text-danger">
                {{ $galat }}
            </div>
        @endif

        <form wire:submit="buatPesanan" class="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">

            <div class="space-y-8">

                <section class="plate p-4 lg:p-6">
                    <p class="part-no">02 / {{ __('site.checkout.contact') }}</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="nama">{{ __('site.checkout.name') }}</label>
                            <input id="nama" type="text" class="field" wire:model="nama" autocomplete="name">
                            @error('nama') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="email">{{ __('site.checkout.email') }}</label>
                            <input id="email" type="email" class="field" wire:model="email" autocomplete="email">
                            @error('email') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label" for="telepon">{{ __('site.checkout.phone') }}</label>
                            <input id="telepon" type="tel" inputmode="tel" class="field" wire:model="telepon"
                                   autocomplete="tel" placeholder="62812xxxxxxx">
                            @error('telepon') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="plate p-4 lg:p-6">
                    <p class="part-no">03 / {{ __('site.checkout.shipping') }}</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="label" for="alamat1">{{ __('site.checkout.address_1') }}</label>
                            <textarea id="alamat1" class="field" wire:model="alamat1" rows="2"></textarea>
                            @error('alamat1') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label" for="alamat2">{{ __('site.checkout.address_2') }}</label>
                            <input id="alamat2" type="text" class="field" wire:model="alamat2">
                        </div>

                        <div>
                            <label class="label" for="kota">{{ __('site.checkout.city') }}</label>
                            <input id="kota" type="text" class="field" wire:model="kota">
                            @error('kota') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="provinsi">{{ __('site.checkout.province') }}</label>
                            <input id="provinsi" type="text" class="field" wire:model="provinsi">
                            @error('provinsi') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="kodePos">{{ __('site.checkout.postal') }}</label>
                            <input id="kodePos" type="text" inputmode="numeric" class="field" wire:model="kodePos">
                            @error('kodePos') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label" for="kurir">{{ __('site.checkout.courier') }}</label>
                            <select id="kurir" class="field" wire:model="kurir">
                                @foreach ($daftarKurir as $k)
                                    <option value="{{ $k }}">{{ $k }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label" for="catatan">{{ __('site.checkout.note') }}</label>
                            <textarea id="catatan" class="field" wire:model="catatan" rows="2"></textarea>
                        </div>
                    </div>
                </section>

                <section class="plate p-4 lg:p-6">
                    <p class="part-no">04 / {{ __('site.checkout.payment_method') }}</p>

                    <div class="mt-4 space-y-3">
                        @if (in_array($modePembayaran, ['manual', 'both'], true))
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="radio" name="metode" value="manual" wire:model="metode"
                                       class="mt-1 h-4 w-4 accent-[var(--c-accent)]">
                                <span>
                                    <span class="font-semibold">{{ __('site.orders.payment_manual') ?? 'Transfer Manual' }}</span>
                                    <span class="mt-0.5 block text-sm text-muted">
                                        Transfer ke rekening toko, lalu unggah bukti. Pesanan diproses setelah diverifikasi.
                                    </span>
                                </span>
                            </label>
                        @endif

                        @if (in_array($modePembayaran, ['paypal', 'both'], true))
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="radio" name="metode" value="paypal" wire:model="metode"
                                       class="mt-1 h-4 w-4 accent-[var(--c-accent)]">
                                <span>
                                    <span class="font-semibold">PayPal</span>
                                    <span class="mt-0.5 block text-sm text-muted">
                                        Anda dialihkan ke PayPal untuk menyelesaikan pembayaran.
                                    </span>
                                </span>
                            </label>
                        @endif
                    </div>

                    @error('metode') <p class="field-error">{{ $message }}</p> @enderror
                </section>

                {{-- Jebakan bot. Tidak terlihat, tidak boleh diisi. --}}
                <div class="visually-hidden" aria-hidden="true">
                    <label for="situs">Situs</label>
                    <input id="situs" type="text" wire:model="situs" tabindex="-1" autocomplete="off">
                </div>
            </div>

            <aside class="plate h-fit p-4 lg:sticky lg:top-24 lg:p-6">
                <p class="part-no">05 / {{ __('site.checkout.summary') }}</p>

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

                <p class="text-sm text-muted">
                    {{ $ringkasan['item'] }} item · {{ __('site.checkout.courier') }}: {{ $kurir }}
                </p>

                <button type="submit" class="btn btn-primary mt-5 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="buatPesanan">{{ __('site.checkout.place_order') }}</span>
                    <span wire:loading wire:target="buatPesanan">{{ __('common.loading') }}</span>
                </button>

                <a href="{{ route('front.cart') }}" class="btn btn-ghost mt-2 w-full" wire:navigate>
                    {{ __('site.cart.title') }}
                </a>
            </aside>
        </form>
    </div>
</div>
