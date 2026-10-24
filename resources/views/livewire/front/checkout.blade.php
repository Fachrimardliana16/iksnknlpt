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

        {{-- ── Jebakan bot ───────────────────────────────────────────
             DUA lapis, keduanya disembunyikan dari manusia:

             1. Kolom "situs". Bot mengisi setiap field yang ada; manusia
                tidak melihat field ini sama sekali (tabindex negatif,
                aria-hidden, tanpa label yang tampil).
             2. wire:model="segel" — nilai acak yang diulang. Manusia mengirim
                sekali; bot mengulang ratusan kali dari muatan yang sama.

             Menjaga keduanya tetap tersembunyi: jangan pernah memberi tahu bot
             bahwa field ini ada, dan jangan membalas galatnya dengan bahasa
             natural — itu berarti memberi tahu bot cara memperbaiki diri. --}}
        <input type="text" wire:model="situs" tabindex="-1" autocomplete="off"
               aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0">

        <input type="hidden" wire:model="segel">

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

                        <div>
                            <label class="label" for="negara">{{ __('site.checkout.country') }}</label>
                            <select id="negara" class="field" wire:model.live="negara">
                                @foreach ($daftarNegara as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="label" for="mataUang">{{ __('site.checkout.currency') }}</label>
                            <select id="mataUang" class="field" wire:model.live="mataUang" @disabled($danNegeri)>
                                <option value="IDR">Rupiah (IDR)</option>
                                <option value="USD">US Dollar (USD)</option>
                            </select>
                            @if ($danNegeri)
                                <p class="mt-1 text-xs text-dim">Pengiriman dalam negeri ditagih dalam Rupiah.</p>
                            @endif
                        </div>

                        @unless ($danNegeri)
                            <div class="sm:col-span-2">
                                <div class="plate border-l-2 border-l-warn p-3 text-sm">
                                    <p class="font-semibold">Kirim ke {{ $namaNegara }}</p>
                                    <ul class="mt-2 space-y-1 text-muted">
                                        <li>Tulis alamat dalam huruf Latin. Nama jalan dan nomor saja sudah cukup — jangan campur bahasa setempat.</li>
                                        <li>Ongkir internasional dihitung manual oleh admin setelah pesanan masuk. Anda akan dihubungi lewat surel atau WhatsApp sebelum pesanan dikirim.</li>
                                        <li>Bea masuk dan pajak impor di negara tujuan menjadi tanggungan pembeli.</li>
                                        <li>Harga yang dibayar tetap dalam Rupiah. {{ $kodeTelepon }} saja hanyalah kode telepon yang disarankan.</li>
                                    </ul>
                                </div>
                            </div>
                        @endunless

                        <div class="sm:col-span-2">
                            <label class="label" for="catatan">{{ __('site.checkout.note') }}</label>
                            <textarea id="catatan" class="field" wire:model="catatan" rows="2"
                                      maxlength="500" placeholder="{{ __('site.cart.note_hint') }}"></textarea>
                            @error('catatan') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="plate p-4 lg:p-6">
                    <p class="part-no">04 / {{ __('site.checkout.payment_method') }}</p>

                    <div class="mt-4 space-y-3">
                        @foreach ($daftarMetode as $satuMetode)
                            @if ($satuMetode === 'manual')
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="radio" name="metode" value="manual" wire:model="metode"
                                           class="mt-1 h-4 w-4 accent-[var(--c-accent)]">
                                    <span>
                                        <span class="font-semibold">{{ __('site.checkout.payment_manual_label') }}</span>
                                        <span class="mt-0.5 block text-sm text-muted">
                                            {{ __('site.checkout.payment_manual_hint') }}
                                        </span>
                                    </span>
                                </label>
                            @endif

                            @if ($satuMetode === 'paypal')
                                <label class="flex cursor-pointer items-start gap-3">
                                    <input type="radio" name="metode" value="paypal" wire:model="metode"
                                           class="mt-1 h-4 w-4 accent-[var(--c-accent)]">
                                    <span>
                                        <span class="font-semibold">PayPal</span>
                                        <span class="mt-0.5 block text-sm text-muted">
                                            {{ __('site.checkout.payment_paypal_hint') }}
                                        </span>
                                    </span>
                                </label>
                            @endif
                        @endforeach

                        {{-- Mode pembayaran hanya manual atau hanya PayPal: tidak ada
                             yang perlu dipilih, jadi pilihannya dijelaskan, bukan
                             ditampilkan sebagai satu tombol radio yang sudah aktif. --}}
                        @if (count($daftarMetode) === 1)
                            <p class="text-xs text-dim">
                                {{ $daftarMetode[0] === 'manual'
                                    ? __('site.checkout.payment_only_manual')
                                    : __('site.checkout.payment_only_paypal') }}
                            </p>
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

                {{-- Perkiraan dalam mata uang pembeli asing.
                     Sengaja BERADA di bawah total IDR, bukan menggantikannya:
                     yang dipakai membayar tetap Rupiah, jadi angka itu yang
                     dinomordukan dan angka ini yang diberi tanda "perkiraan". --}}
                @if ($perkiraanAsing !== null)
                    <p class="mt-1 text-xs text-dim">
                        Perkiraan {{ $perkiraanAsing['mata'] }}: {{ $perkiraanAsing['teks'] }}
                        @if ($perkiraanAsing['kurs'])
                            (kurs {{ number_format($perkiraanAsing['kurs'], 0, ',', '.') }} · {{ $perkiraanAsing['sumber'] }})
                        @endif
                    </p>
                @endif

                <div class="measure-line my-4"></div>

                <p class="text-sm text-muted">
                    {{ $ringkasan['item'] }} item · {{ __('site.checkout.courier') }}: {{ $kurir }}
                    @unless ($danNegeri)
                        · {{ __('site.checkout.shipping_intl_quote') }}
                    @endunless
                </p>

                <button type="submit" class="btn btn-primary mt-5 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="buatPesanan">{{ __('site.checkout.place_order') }}</span>
                    <span wire:loading wire:target="buatPesanan">{{ __('site.common.loading') }}</span>
                </button>

                <a href="{{ route('front.cart') }}" class="btn btn-ghost mt-2 w-full" wire:navigate>
                    {{ __('site.cart.title') }}
                </a>
            </aside>
        </form>
    </div>
</div>
