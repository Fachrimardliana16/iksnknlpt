@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
@endphp

<div>

    {{-- 01 / HERO — full viewport --}}
    <section class="hero-layar relative isolate"
             x-data="{
                i: 0,
                n: {{ max(1, $slides->count()) }},
                jeda: false,
                timer: null,
                bolehGerak: function () {
                    return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                },
                mulai: function () {
                    clearInterval(this.timer);
                    if (this.n < 2 || this.jeda || !this.bolehGerak()) return;
                    this.timer = setInterval(function () { this.i = (this.i + 1) % this.n; }.bind(this), 7000);
                },
                berhenti: function () { clearInterval(this.timer); this.timer = null; }
             }"
             x-init="mulai()"
             x-on:livewire:navigated.window="i = 0; jeda = false; berhenti(); mulai()">

        {{-- Slide pertama dimuat lebih dulu di <head> (lihat layouts/front), supaya
             peramban mulai mengunduhnya saat masih mengurai HTML. Di sini ia
             hanya dipakai sebagai latar. Slide berikutnya TIDAK dimuat sebelum
             giliran tampil: tanpa itu, tiga gambar 1600 px diunduh pada setiap
             kunjungan beranda, dan waktu muat ikut menanggung semuanya. --}}
        @forelse ($slides as $idx => $slide)
            @php
                $urlSlide = \App\Models\ProductMedia::urlMedia($slide->media_path);
            @endphp
            <div class="absolute inset-0 -z-10 transition-opacity duration-700"
                 x-show="i === {{ $idx }}"
                 :style="'opacity: i === {{ $idx }} ? 1 : 0'"
                 @if ($idx === 0)
                     style="background-image: url('{{ $urlSlide }}');
                            background-size: cover;
                            background-position: {{ $slide->posisiFokus() }};"
                 @else
                     {{-- Dipasang saat slide ditampilkan (Alpine menyalin latar). --}}
                     x-bind:style="i === {{ $idx }} ? \"background-image: url('{{ $urlSlide }}'); background-size: cover; background-position: {{ $slide->posisiFokus() }};\" : 'background-image: none;'"
                 @endif
                 >
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/70 to-black/45"></div>
            </div>
        @empty
            <div class="absolute inset-0 -z-10 carbon-panel">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/60 to-black/30"></div>
            </div>
        @endforelse

        <div class="container-page relative flex h-full flex-col justify-end pb-24 text-white sm:justify-center sm:pb-0">

            @if ($slides->isEmpty())
                <span class="torque-stripe mb-4" aria-hidden="true"><i></i><i></i><i></i></span>
                <h1 class="max-w-3xl text-4xl font-semibold sm:text-6xl lg:text-7xl">
                    {{ $toko->teks('site_tagline', __('site.brand.tagline')) }}
                </h1>
                <p class="mt-4 max-w-xl text-base text-white/80 sm:text-lg">
                    {{ $toko->teks('site_description') }}
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('front.products.index') }}" class="btn btn-primary">
                        {{ __('site.home.view_all_products') }}
                    </a>
                    @if ($toko->teks('whatsapp_number'))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $toko->teks('whatsapp_number')) }}"
                           target="_blank" rel="noopener noreferrer" class="btn btn-outline !border-white/40 !text-white">
                            {{ __('site.home.cta_primary') }}
                        </a>
                    @endif
                </div>
            @else
                @foreach ($slides as $idx => $slide)
                    <div x-show="i === {{ $idx }}" x-cloak x-transition.opacity.duration.500ms>
                        <span class="torque-stripe mb-4" aria-hidden="true"><i></i><i></i><i></i></span>

                        <h1 class="max-w-3xl text-4xl font-semibold sm:text-6xl lg:text-7xl">
                            {{ $slide->judul() }}
                        </h1>

                        @if (filled($slide->subjudul()))
                            <p class="mt-4 max-w-xl text-base text-white/80 sm:text-lg">
                                {{ $slide->subjudul() }}
                            </p>
                        @endif

                        <div class="mt-8 flex flex-wrap gap-3">
                            @if (filled($slide->primary_button_url) && filled($slide->labelTombolUtama()))
                                <a href="{{ $slide->primary_button_url }}" class="btn btn-primary">
                                    {{ $slide->labelTombolUtama() }}
                                </a>
                            @endif

                            @if (filled($slide->secondary_button_url) && filled($slide->labelTombolKedua()))
                                <a href="{{ $slide->secondary_button_url }}"
                                   class="btn btn-outline !border-white/40 !text-white">
                                    {{ $slide->labelTombolKedua() }}
                                </a>
                            @elseif ($toko->teks('whatsapp_number'))
                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $toko->teks('whatsapp_number')) }}"
                                   target="_blank" rel="noopener noreferrer"
                                   class="btn btn-outline !border-white/40 !text-white">
                                    {{ __('site.home.cta_primary') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Kendali slider. Tombol jeda selalu terlihat, bukan tersembunyi. --}}
            @if ($slides->count() > 1)
                <div class="absolute bottom-6 right-4 flex items-center gap-2 sm:bottom-8 sm:right-8">
                    <button type="button" class="btn-icon !text-white/80 hover:!text-white"
                            @click="jeda = !jeda; jeda ? berhenti() : mulai()"
                            :title="jeda ? 'Jalankan' : 'Jeda'">
                        {{-- Dua ikon ditukar dengan x-show, bukan <template x-if>.
                             Element <template> tidak dikenal di dalam SVG: HTML
                             mengabaikannya saat mengurai isi <svg>, sehingga
                             x-if tidak pernah punya template untuk di-clone dan
                             Alpine melempar "Cannot read properties of undefined
                             (reading 'cloneNode')". --}}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <g x-show="!jeda"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></g>
                            <path x-show="jeda" x-cloak d="M7 4l13 8-13 8z"/>
                        </svg>
                        <span class="visually-hidden">Jeda</span>
                    </button>

                    @foreach ($slides as $idx => $slide)
                        {{-- Sasaran sentuh 40x40px dengan batang visual 10px di
                             tengahnya. Sebelumnya tombolnya hanya 10x10px,
                             jauh di bawah ukuran yang dapat dipencet dengan
                             jempol. --}}
                        <button type="button" @click="i = {{ $idx }}; berhenti()"
                                class="group/titik flex h-10 w-10 items-center justify-center"
                                aria-label="Slide {{ $idx + 1 }}">
                            <span class="block h-2.5 transition-all duration-300"
                                  :class="i === {{ $idx }} ? 'w-6 bg-white' : 'w-2.5 bg-white/50 group-hover/titik:bg-white/80'"
                                  style="border-radius: 999px"></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- 02 / TENTANG --}}
    <section class="container-page py-10 lg:py-24" data-reveal>
        <span class="part-no">02 / {{ __('site.home.about_part') }}</span>

        <div class="mt-5 grid gap-8 lg:mt-6 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
            <div>
                <h2 class="text-3xl font-semibold sm:text-4xl">{{ __('site.home.about_title') }}</h2>

                <p class="mt-4 text-muted lg:mt-5">
                    {{ $toko->teks('site_description') }}
                </p>

                <ol class="mt-6 space-y-4 lg:mt-8 lg:space-y-5">
                    <li class="flex gap-4">
                        <span class="tech flex-none text-accent">01</span>
                        <div>
                            <p class="font-semibold">Diukur per unit</p>
                            <p class="mt-1 text-sm text-muted">Panjang pipa dan diameter dalam diperiksa sebelum pengelasan, bukan setelah.</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="tech flex-none text-accent">02</span>
                        <div>
                            <p class="font-semibold">Bahan dari satu pemasok</p>
                            <p class="mt-1 text-sm text-muted">Stainless dan titanium dari pemasok yang sama untuk setiap batch, sehingga warna las tidak berbeda.</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="tech flex-none text-accent">03</span>
                        <div>
                            <p class="font-semibold">Dipasang lalu diuji</p>
                            <p class="mt-1 text-sm text-muted">Setiap unit dicoba pasang sebelum dikirim, termasuk clearance terhadap bodi.</p>
                        </div>
                    </li>
                </ol>

                <a href="{{ route('front.about') }}" class="btn btn-outline mt-6 lg:mt-8">
                    {{ __('site.nav.about') }}
                    <x-ikon name="arrow-right" :size="16" />
                </a>
            </div>

            {{-- Angka nyata dari basis data. Bila nol, barisnya tidak dirender.
                 Di ponsel tiga angka ditampilkan sebaris, bukan bertumpuk, agar
                 tidak menambah tinggi halaman. --}}
            <div class="plate dl-grid self-start p-5 lg:p-8">
                <dl class="grid grid-cols-3 gap-4 lg:grid-cols-1 lg:gap-6">
                    @if (($angka['produk'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.home.stats_products') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['produk'], 0, ',', '.') }}</dd>
                        </div>
                    @endif

                    @if (($angka['pesanan'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.home.stats_customers') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['pesanan'], 0, ',', '.') }}</dd>
                        </div>
                    @endif

                    @if (($angka['kategori'] ?? 0) > 0)
                        <div>
                            <dt class="part-no">{{ __('site.footer.categories') }}</dt>
                            <dd class="tech mt-1 text-3xl">{{ number_format($angka['kategori'], 0, ',', '.') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </section>

    <div class="container-page"><div class="measure-line"></div></div>

    {{-- 03 / KATALOG --}}
    <section class="container-page py-10 lg:py-24" data-reveal>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">03 / {{ __('site.home.products_part') }}</span>
                <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.home.products_title') }}</h2>
                <p class="mt-2 text-muted">{{ __('site.home.products_subtitle') }}</p>
            </div>

            {{-- Di mobile tombol ini pindah ke bawah grid supaya tidak mendorong judul. --}}
            <a href="{{ route('front.products.index') }}" class="btn btn-outline hidden lg:inline-flex">
                {{ __('site.home.view_all_products') }}
                <x-ikon name="arrow-right" :size="16" />
            </a>
        </div>

        @if ($unggulan->isEmpty())
            <div class="plate mt-6 p-8 text-center lg:mt-8">
                <p class="font-semibold">{{ __('site.product.empty') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('site.product.empty_hint') }}</p>
            </div>
        @else
            {{-- Di ponsel hanya sebagian kartu dirender; sisanya lewat tombol
                 lihat semua. Kartu ke-5 dan seterusnya disembunyikan dengan
                 kelas, bukan dipotong dari koleksi, supaya markup-nya tetap
                 satu sumber untuk kedua ukuran layar.

                 Catatan: "hidden" mengeluarkan elemen dari pohon aksesibilitas,
                 jadi di ponsel kartu itu memang tidak terbaca pembaca layar.
                 Itu disengaja — jalan menuju produk tersebut adalah tombol
                 lihat semua, bukan gulir. --}}
            <div class="mt-6 grid grid-cols-2 gap-3 lg:mt-8 lg:grid-cols-4 lg:gap-6">
                @foreach ($unggulan as $produk)
                    {{-- Tiap kartu muncul berurutan, dengan jeda 55ms. Muncul
                         serentak membuat empat kartu terbaca sebagai satu blok
                         yang melompat; berurutan, mata mengikutinya. --}}
                    <div class="{{ $loop->index >= $batasKatalog ? 'hidden lg:block' : '' }} h-full"
                         data-reveal style="transition-delay: {{ $loop->index * 55 }}ms">
                        <x-kartu-produk :produk="$produk" />
                    </div>
                @endforeach
            </div>
        @endif

        <a href="{{ route('front.products.index') }}" class="btn btn-primary mt-6 w-full lg:hidden">
            {{ __('site.home.view_all_products') }}
            <x-ikon name="arrow-right" :size="16" />
        </a>
    </section>

    {{-- 04 / CTA --}}
    <section class="carbon-panel" data-reveal>
        <div class="container-page py-12 lg:py-20">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
                <div class="max-w-xl">
                    <span class="torque-stripe mb-4" aria-hidden="true"><i></i><i></i><i></i></span>
                    <h2 class="text-3xl font-semibold sm:text-4xl">{{ __('site.home.cta_title') }}</h2>
                    <p class="mt-3 text-[#a5a5a8]">{{ __('site.home.cta_body') }}</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if ($wa = $toko->teks('whatsapp_number'))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}?text={{ rawurlencode($toko->teks('whatsapp_message_template')) }}"
                           target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                            <x-ikon name="whatsapp" :size="18" />
                            {{ __('site.home.cta_primary') }}
                        </a>
                    @endif

                    <a href="{{ route('front.contact') }}" class="btn btn-outline !border-white/30 !text-white">
                        {{ __('site.home.cta_secondary') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- 05 / FAQ, tepat di bawah CTA. --}}
    @include('partials.front.faq-beranda', ['faq' => $faq])

</div>
