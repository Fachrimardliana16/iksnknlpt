@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();
@endphp

<div class="beranda-home">

    {{-- Hero beranda: navigasi samping tampil saat di puncak (lihat navbar.css). --}}
    @include('partials.front.hero-race', ['slides' => $slides, 'toko' => $toko])

    {{-- 02 / TENTANG + KATEGORI.

         Dua bagian dalam satu section karena keduanya menjawab pertanyaan yang
         sama: "ini toko apa, dan ada apa di dalamnya". Dipisah jadi dua
         section yang berselang measure-line membuat halaman terasa patah.

         Struktur: kepala (judul + narasi + tombol) dan baris angka berdampingan
         di desktop; tiga proses pindah ke baris sendiri sebaris — sebelumnya
         menumpuk vertikal di kolom kiri dan membuang enam baris tinggi sebelum
         pengunjung sampai ke katalog; lalu strip kategori, yang sebelumnya
         tidak ada sama sekali. --}}
    <section class="container-page race-about py-10 lg:py-24" data-reveal>
        <div class="grid gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-16">

            <div>
                <span class="part-no">02 / {{ __('site.home.about_part') }}</span>
                <h2 class="section-heading mt-4 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                    {{ __('site.home.about_title') }}
                </h2>

                <p class="mt-5 max-w-xl text-base leading-relaxed text-muted lg:text-lg">
                    {{ $toko->teks('site_description') }}
                </p>
            </div>

            {{-- Angka dari basis data. Bila nol, barisnya tidak dirender. --}}
            {{-- Tiga kolom tetap. `grid-cols-3` saja tidak cukup: tanpa
                 min-w-0 dan text-center, label panjang ("PRODUK AKTIF") meluber
                 ke kolom sebelah dan angkanya tidak sejajar. Setiap sel kini
                 flex-col items-center justify-center dengan padding sendiri. --}}
            @if (array_filter($angka))
                <dl class="plate grid grid-cols-3 gap-2 self-start p-4 text-center lg:gap-4 lg:p-6">
                    @if (($angka['produk'] ?? 0) > 0)
                        <div class="flex min-w-0 flex-col items-center justify-center px-1 py-2">
                            <dt class="text-[0.625rem] uppercase leading-tight tracking-wide text-dim lg:text-xs">
                                {{ __('site.home.stats_products') }}
                            </dt>
                            <dd class="tech mt-1.5 text-xl font-bold tabular-nums leading-none lg:text-3xl">
                                {{ number_format($angka['produk'], 0, ',', '.') }}
                            </dd>
                        </div>
                    @endif

                    @if (($angka['pesanan'] ?? 0) > 0)
                        <div class="flex min-w-0 flex-col items-center justify-center px-1 py-2">
                            <dt class="text-[0.625rem] uppercase leading-tight tracking-wide text-dim lg:text-xs">
                                {{ __('site.home.stats_customers') }}
                            </dt>
                            <dd class="tech mt-1.5 text-xl font-bold tabular-nums leading-none lg:text-3xl">
                                {{ number_format($angka['pesanan'], 0, ',', '.') }}
                            </dd>
                        </div>
                    @endif

                    @if (($angka['kategori'] ?? 0) > 0)
                        <div class="flex min-w-0 flex-col items-center justify-center px-1 py-2">
                            <dt class="text-[0.625rem] uppercase leading-tight tracking-wide text-dim lg:text-xs">
                                {{ __('site.footer.categories') }}
                            </dt>
                            <dd class="tech mt-1.5 text-xl font-bold tabular-nums leading-none lg:text-3xl">
                                {{ number_format($angka['kategori'], 0, ',', '.') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </div>

        {{-- Tiga proses: baris sendiri, sebaris di desktop. Kartu memberi
             pemisah visual yang dulu hanya berupa daftar bernomor. --}}
        <ol class="mt-10 grid gap-4 lg:mt-14 lg:grid-cols-3 lg:gap-6">
            <li class="plate plate-lux p-5 lg:p-6" data-reveal>
                <span class="tech text-accent">01</span>
                <p class="mt-2 font-semibold lg:text-lg">Diukur per unit</p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">Panjang pipa dan diameter dalam diperiksa sebelum pengelasan, bukan setelah.</p>
            </li>
            <li class="plate plate-lux p-5 lg:p-6" data-reveal style="transition-delay: 60ms">
                <span class="tech text-accent">02</span>
                <p class="mt-2 font-semibold lg:text-lg">Bahan dari satu pemasok</p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">Stainless dan titanium dari pemasok yang sama untuk setiap batch, sehingga warna las tidak berbeda.</p>
            </li>
            <li class="plate plate-lux p-5 lg:p-6" data-reveal style="transition-delay: 120ms">
                <span class="tech text-accent">03</span>
                <p class="mt-2 font-semibold lg:text-lg">Dipasang lalu diuji</p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">Setiap unit dicoba pasang sebelum dikirim, termasuk clearance terhadap bodi.</p>
            </li>
        </ol>

        {{-- Tombol "Tentang" di bawah baris proses, bukan di kolom narasi.
             Alasannya urutan baca: pengunjung membaca judul, narasi, angka,
             lalu tiga proses — tombol yang ditaruh di kolom narasi muncul di
             tengah urutan itu dan memotong alur sebelum proses selesai dibaca.
             Rata kiri, bukan tengah: ia tindak lanjut dari daftar di atasnya. --}}
        <a href="{{ route('front.about') }}" class="btn btn-outline mt-8 self-start lg:mt-10">
            {{ __('site.nav.about') }}
            <x-ikon name="arrow-right" :size="16" />
        </a>
    </section>

    {{-- 03 / KATEGORI — section mandiri, tepat di bawah about.

         Satu klik menuju etalase — jalan yang dulu harus lewat navbar lalu
         memilih filter di halaman produk.

         Ikon, bukan gambar: kategori tidak punya media sendiri, dan ikon
         membuat kartu tetap ringan serta seukuran. Angka produk dihitung satu
         kueri untuk SEMUA lewat withCount, bukan satu per kartu.

         Nomor bagian 03, bukan 02a: ia punya kepala sendiri, dan menomorinya
         sebagai sub-bagian tentang membuat hierarki judul menyimpang dari
         nomornya. --}}
    @if ($kategori->isNotEmpty())
        <section class="container-page race-categories py-10 lg:py-24" data-reveal>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="part-no">03 / {{ __('site.footer.categories') }}</span>
                    <h2 class="section-heading mt-3 text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">
                        {{ __('site.home.categories_title') }}
                    </h2>
                    <p class="mt-4 max-w-2xl text-base text-muted lg:text-lg">
                        {{ __('site.home.categories_subtitle') }}
                    </p>
                </div>
            </div>

            {{-- Kategori SEBARIS: enam kartu dalam satu baris di desktop, dua
                 baris di ponsel (tiga + tiga). Bukan 2x3 yang menjorok —
                 grid-cols-3 selalu membuat enam kartu jadi dua baris rapi,
                 tapi di lebar tablet hasilnya tiga kolom yang tingginya tak
                 sejajar. Dengan grid-cols-6 semua kartu seukuran dan namanya
                 tetap terbaca; di bawah lg jatuh ke dua kolom.

                 Angka produk dibuang. Ia menjawab "ada berapa", pertanyaan
                 yang tidak pernah diajukan pembaca yang sedang memilih
                 kategori — nama dan ikon sudah cukup, dan angka membuat nama
                 kategori yang panjang (mis. "Aksesoris & Kelengkapan Racing")
                 terpaksa memakai truncate di kartu yang lebarnya seperenam. --}}
            <div class="mt-6 grid grid-cols-2 gap-3 lg:mt-10 lg:grid-cols-6 lg:gap-3">
                @foreach ($kategori as $i => $kat)
                    <a href="{{ route('front.category', $kat) }}" wire:navigate
                       class="plate plate-interaktif kategori-kartu group flex flex-col items-center gap-3 p-4 text-center lg:p-5"
                       data-reveal style="transition-delay: {{ $i * 45 }}ms">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full border border-line-soft bg-sunken text-accent transition-colors group-hover:border-accent lg:h-12 lg:w-12">
                            <x-ikon :name="$kat->ikon()" :size="20" />
                        </span>

                        <span class="w-full">
                            <span class="block font-semibold leading-snug lg:text-base">{{ $kat->nama() }}</span>
                        </span>

                        <x-ikon name="arrow-right" :size="14"
                                class="flex-none text-dim transition-transform group-hover:translate-x-0.5 group-hover:text-accent" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="container-page"><div class="measure-line"></div></div>

    {{-- 03 / KATALOG --}}
    <section class="container-page race-catalog py-10 lg:py-24" data-reveal>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">04 / {{ __('site.home.products_part') }}</span>
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
    <section class="carbon-panel beranda-cta" data-reveal>
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
