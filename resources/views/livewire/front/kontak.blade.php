<div>
    <div class="container-page py-8 lg:py-12">
        <div class="mx-auto max-w-3xl">

            <span class="part-no">01 / {{ __('site.contact.title') }}</span>
            <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.contact.title') }}</h1>
            <p class="mt-2 text-muted">{{ __('site.contact.subtitle') }}</p>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_18rem]">

                @if ($terkirim)
                    <div class="plate border-l-2 border-l-ok p-6">
                        <p class="font-semibold text-ok">{{ __('site.contact.sent') }}</p>
                        <button type="button" class="btn btn-ghost mt-4" wire:click="$set('terkirim', false)">
                            Kirim pesan lain
                        </button>
                    </div>
                @else
                    <form wire:submit="kirim" class="plate p-4 lg:p-6">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="label" for="nama">{{ __('site.contact.name') }}</label>
                                <input id="nama" type="text" class="field" wire:model="nama" autocomplete="name">
                                @error('nama') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label" for="email">{{ __('site.contact.email') }}</label>
                                <input id="email" type="email" class="field" wire:model="email" autocomplete="email">
                                @error('email') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label" for="telepon">{{ __('site.contact.phone') }}</label>
                                <input id="telepon" type="tel" class="field" wire:model="telepon" autocomplete="tel">
                                @error('telepon') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="label" for="subjek">{{ __('site.contact.subject') }}</label>
                                <input id="subjek" type="text" class="field" wire:model="subjek">
                                @error('subjek') <p class="field-error">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label class="label" for="pesan">{{ __('site.contact.message') }}</label>
                                <textarea id="pesan" class="field" rows="6" wire:model="pesan"></textarea>
                                @error('pesan') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- Jebakan bot. --}}
                        <div class="visually-hidden" aria-hidden="true">
                            <label for="perusahaan">Perusahaan</label>
                            <input id="perusahaan" type="text" wire:model="perusahaan" tabindex="-1" autocomplete="off">
                        </div>

                        <button type="submit" class="btn btn-primary mt-5 w-full sm:w-auto" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="kirim">{{ __('site.contact.send') }}</span>
                            <span wire:loading wire:target="kirim">{{ __('site.common.loading') }}</span>
                        </button>

                        @if ($galat)
                            <p class="field-error mt-3">{{ $galat }}</p>
                        @endif
                    </form>
                @endif

                <aside class="space-y-4">
                    <div class="plate p-4">
                        <p class="part-no">{{ __('site.contact.call') }}</p>

                        <ul class="mt-3 space-y-3 text-sm">
                            @if ($wa = $toko->teks('whatsapp_number'))
                                <li>
                                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank"
                                       rel="noopener noreferrer" class="flex items-center gap-2 hover:text-accent">
                                        <x-ikon name="whatsapp" :size="18" />
                                        <span class="tech">+{{ preg_replace('/\D/', '', $wa) }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($email = $toko->teks('contact_email'))
                                <li>
                                    <a href="mailto:{{ $email }}" class="flex items-center gap-2 hover:text-accent">
                                        <x-ikon name="mail" :size="18" />
                                        <span>{{ $email }}</span>
                                    </a>
                                </li>
                            @endif

                            @if ($telp = $toko->teks('phone_number'))
                                <li class="flex items-center gap-2">
                                    <x-ikon name="phone" :size="18" />
                                    <span class="tech">{{ $telp }}</span>
                                </li>
                            @endif

                            @if ($jam = $toko->teks('operational_hours'))
                                <li class="flex items-start gap-2">
                                    <x-ikon name="clock" :size="18" class="mt-0.5 flex-none" />
                                    <span class="text-muted">{{ $jam }}</span>
                                </li>
                            @endif
                        </ul>
                    </div>

                    @if ($alamat = $toko->teks('address_full'))
                        <div class="plate p-4">
                            <p class="part-no">{{ __('site.contact.address') }}</p>
                            <p class="mt-3 text-sm text-muted">{{ $alamat }}</p>

                            @if ($lat = $toko->teks('maps_lat'))
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $toko->teks('maps_lng') }}"
                                   target="_blank" rel="noopener noreferrer" class="btn btn-outline mt-4 w-full">
                                    <x-ikon name="map" :size="16" />
                                    Buka di Maps
                                </a>
                            @endif
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>
</div>
