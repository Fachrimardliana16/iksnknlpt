@php
    /** @var \App\Support\Pengaturan $toko */
    $tahun = now()->year;

    // Diukur pada 390x844: enam kategori berarti enam baris di kolom yang
    // sempit. Empat sudah cukup untuk mengenalkan struktur katalog.
    $kategoriFooter = \App\Models\Category::query()
        ->aktif()
        ->terurut()
        ->take(max(2, (int) config('iksan.home.footer_categories', 4)))
        ->get();
@endphp

{{-- Footer.
     Diukur pada viewport 390x844, footer lama setinggi 1007 px — 1,19 layar
     penuh, lebih tinggi daripada hero. Penyebabnya: empat blok ditumpuk
     vertikal dengan jarak 40 px, padding 48 px atas dan bawah, margin atas
     64 px, dan dua kolom tautan yang sebagian besar mengulang bar navigasi
     bawah (Home, Produk, dan Kontak sudah ada di sana).

     Di ponsel, susunannya sekarang: identitas dan kontak masing-masing
     selebar penuh, lalu kategori dan navigasi berdampingan dalam dua kolom.
     Tautan yang sudah ada di bar bawah dibuang dari sini, kecuali "Lacak
     pesanan" yang tidak ada di sana dan dipindah ke baris hak cipta. --}}
<footer class="carbon-panel mt-10 lg:mt-24">
    <div class="container-page py-10 lg:py-16">

        <div class="grid grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr] lg:gap-10">

            <div class="col-span-2 lg:col-span-1">
                <p class="font-display text-2xl font-semibold">
                    {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                </p>
                <p class="mt-3 max-w-xs text-sm text-[#a5a5a8]">
                    {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
                </p>
                <p class="mt-3 text-sm text-[#a5a5a8]">
                    {{ $toko->teks('address_full') }}
                </p>
            </div>

            {{-- Navigasi: hanya desktop. Di ponsel, Home, Produk, dan Kontak
                 sudah tersedia di bar navigasi bawah sehingga mengulanginya di
                 sini hanya menambah tinggi tanpa menambah jalan. --}}
            {{-- Di desktop: blok terbuka seperti kolom biasa. Di mobile:
                 <details> yang menumpuk dan baru terbuka saat disentuh, agar
                 footer tidak mendorong halaman menjadi dua layar panjang.
                 Atribut `open` dipasang pada lebar besar lewat CSS, bukan
                 JavaScript, supaya tetap benar walau skrip gagal dimuat. --}}
            <details class="footer-akordeon hidden lg:block" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.navigation') }}</summary>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a href="{{ route('front.home') }}" class="hover:text-white">{{ __('site.nav.home') }}</a></li>
                    <li><a href="{{ route('front.products.index') }}" class="hover:text-white">{{ __('site.nav.products') }}</a></li>
                    <li><a href="{{ route('front.about') }}" class="hover:text-white">{{ __('site.nav.about') }}</a></li>
                    <li><a href="{{ route('front.contact') }}" class="hover:text-white">{{ __('site.nav.contact') }}</a></li>
                    <li><a href="{{ route('front.order.track') }}" class="hover:text-white">{{ __('site.order.track') }}</a></li>
                </ul>
            </details>

            <details class="footer-akordeon" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.categories') }}</summary>
                <ul class="mt-3 space-y-2 text-sm lg:mt-3">
                    @foreach ($kategoriFooter as $kat)
                        <li>
                            <a href="{{ route('front.products.index', ['kategori' => $kat->slug]) }}"
                               class="hover:text-white">{{ $kat->nama() }}</a>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- Kontak: di mobile juga bisa ditutup, karena isinya panjang
                 (WhatsApp, surel, jam, ikon sosial). --}}
            <details class="footer-akordeon col-span-2 lg:col-span-1" open>
                <summary class="part-no cursor-pointer list-none lg:cursor-default">{{ __('site.footer.contact') }}</summary>
                <ul class="space-y-2 text-sm">
                    @if ($wa = $toko->teks('whatsapp_number'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="whatsapp" :size="16" class="flex-none" />
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" rel="noopener"
                               class="tech hover:text-white">+{{ preg_replace('/\D/', '', $wa) }}</a>
                        </li>
                    @endif

                    @if ($email = $toko->teks('contact_email'))
                        <li class="flex items-center gap-2">
                            <x-ikon name="mail" :size="16" class="flex-none" />
                            <a href="mailto:{{ $email }}" class="truncate hover:text-white">{{ $email }}</a>
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
                    <div class="mt-4 flex flex-wrap gap-2">
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
            </details>
        </div>

        <div class="measure-line mt-8 mb-5 lg:mt-12 lg:mb-6" style="background: rgba(255,255,255,.14)"></div>

        <div class="flex flex-col gap-2 text-xs text-[#a5a5a8] sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ $tahun }} {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}. {{ __('site.footer.rights') }}</p>
            <div class="flex flex-wrap gap-4">
                {{-- Lacak pesanan tidak ada di bar navigasi bawah, jadi
                     dipindahkan ke sini agar tetap terjangkau dari ponsel. --}}
                <a href="{{ route('front.order.track') }}" class="hover:text-white lg:hidden">{{ __('site.order.track') }}</a>
                <a href="{{ route('front.about') }}" class="hover:text-white lg:hidden">{{ __('site.nav.about') }}</a>
                <a href="{{ route('front.page', 'kebijakan-privasi') }}" class="hover:text-white">{{ __('site.footer.privacy') }}</a>
                <a href="{{ route('front.page', 'syarat-ketentuan') }}" class="hover:text-white">{{ __('site.footer.terms') }}</a>
            </div>
        </div>
    </div>
</footer>

