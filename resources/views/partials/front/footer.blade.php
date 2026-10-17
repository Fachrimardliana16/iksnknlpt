@php
    /** @var \App\Support\Pengaturan $toko */
    $tahun = now()->year;
    $kategoriFooter = \App\Models\Category::query()->aktif()->terurut()->take(6)->get();
@endphp

<footer class="carbon-panel mt-16 lg:mt-24">
    <div class="container-page py-12 lg:py-16">

        {{-- Empat kolom dengan lebar berbeda, bukan empat kolom sama rata. --}}
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">

            <div>
                <p class="font-display text-2xl font-semibold">
                    {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                </p>
                <p class="mt-3 max-w-xs text-sm text-[#a5a5a8]">
                    {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
                </p>
                <p class="mt-4 text-sm text-[#a5a5a8]">
                    {{ $toko->teks('address_full') }}
                </p>
            </div>

            <nav aria-label="{{ __('site.footer.navigation') }}">
                <p class="part-no mb-3">{{ __('site.footer.navigation') }}</p>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('front.home') }}" class="hover:text-white">{{ __('site.nav.home') }}</a></li>
                    <li><a href="{{ route('front.products.index') }}" class="hover:text-white">{{ __('site.nav.products') }}</a></li>
                    <li><a href="{{ route('front.about') }}" class="hover:text-white">{{ __('site.nav.about') }}</a></li>
                    <li><a href="{{ route('front.contact') }}" class="hover:text-white">{{ __('site.nav.contact') }}</a></li>
                    <li><a href="{{ route('front.order.track') }}" class="hover:text-white">{{ __('site.order.track') }}</a></li>
                </ul>
            </nav>

            <nav aria-label="{{ __('site.footer.categories') }}">
                <p class="part-no mb-3">{{ __('site.footer.categories') }}</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($kategoriFooter as $kat)
                        <li>
                            <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                               class="hover:text-white">{{ $kat->nama() }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div>
                <p class="part-no mb-3">{{ __('site.footer.contact') }}</p>
                <ul class="space-y-3 text-sm">
                    @if ($wa = $toko->teks('whatsapp_number'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="whatsapp" :size="16" />
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" rel="noopener"
                               class="tech hover:text-white">+{{ preg_replace('/\D/', '', $wa) }}</a>
                        </li>
                    @endif

                    @if ($email = $toko->teks('contact_email'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="mail" :size="16" />
                            <a href="mailto:{{ $email }}" class="hover:text-white">{{ $email }}</a>
                        </li>
                    @endif

                    @if ($jam = $toko->teks('operational_hours'))
                        <li class="flex items-start gap-2">
                            <x-ikon name="clock" :size="16" class="mt-0.5 flex-none" />
                            <span class="text-[#a5a5a8]">{{ $jam }}</span>
                        </li>
                    @endif
                </ul>

                @if ($toko->mediaSosial()->isNotEmpty())
                    <p class="part-no mb-2 mt-6">{{ __('site.footer.follow') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($toko->mediaSosial() as $sosial)
                            <a href="{{ $sosial['url'] }}" target="_blank" rel="noopener noreferrer"
                               class="btn-icon !text-[#a5a5a8] hover:!text-white"
                               title="{{ ucfirst($sosial['platform']) }}">
                                <x-ikon :name="$sosial['platform']" />
                                <span class="visually-hidden">{{ ucfirst($sosial['platform']) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="measure-line mt-12 mb-6" style="background: rgba(255,255,255,.14)"></div>

        <div class="flex flex-col gap-3 text-xs text-[#a5a5a8] sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ $tahun }} {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}. {{ __('site.footer.rights') }}</p>
            <div class="flex gap-4">
                <a href="{{ route('front.page', 'kebijakan-privasi') }}" class="hover:text-white">{{ __('site.footer.privacy') }}</a>
                <a href="{{ route('front.page', 'syarat-ketentuan') }}" class="hover:text-white">{{ __('site.footer.terms') }}</a>
            </div>
        </div>
    </div>
</footer>
