{{--
    HERO BERANDA — satu layar, judul di tengah, kendali di kaki.

    Struktur (flex-col, satu sumbu, jarak simetris):

        ┌──────────────────────────────────────────────┐
        │  HOME                            [ID] [cari] │  baris atas
        │                                              │
        │              SLIDE UJI                       │  judul, tepat
        │               02 GULIR                       │  di tengah
        │                                              │
        │  Toko knalpot custom dan racing di …         │  deskripsi
        │ ──────────────────────────────────────────── │  garis
        │  [ig][tt][wa]              01/02 ▬▬▯  ⏸      │  kaki
        └──────────────────────────────────────────────┘

    Brand TIDAK dirender di sini. Dulu nama toko muncul tiga kali di satu
    layar: di navbar, sebagai `.hero-race__merek` di tengah, dan sebagai
    huruf raksasa bergaris tepi di latar. Ketiganya membuat hero terlihat
    penuh dan berantakan di atas gambar. Sekarang brand hanya ada di navbar;
    teks utama hero adalah judul slide.

    Sosial dan kendali slide ada di KAKI hero (baris paling bawah), bukan
    melayang di tengah gambar: di tengah, keduanya menutupi bagian gambar
    yang justru jadi fokus (produk knalpot) dan bertabrakan dengan judul di
    layar kecil.

    Tinggi hero dikunci satu layar; isinya memakai flex-col dengan `flex-1`
    di blok tengah, jadi judul selalu duduk di tengah sisa ruang yang sama —
    bukan menempel ke atas atau ke kaki.
--}}
@php
    /** @var \App\Support\Pengaturan $toko */
    $slides = $slides ?? collect();

    // Judul TIAP slide, bukan hanya slide pertama.
    //
    // Sebelumnya hanya $slides->first() yang dirender, sehingga menggulir ke
    // slide kedua tetap memperlihatkan judul slide pertama — gambar berganti,
    // tulisannya tidak. Sekarang tiap slide punya judulnya sendiri dan
    // ditukar bersama gambar oleh `i === n`.
    //
    // Pemenggalan per baris dilakukan di batas KATA, maksimal 3 kata, karena
    // memenggal per huruf membuat huruf terakhir satu kata berdiri sendiri
    // sebelum kata itu selesai ditampilkan.
    $pecahBaris = function (string $teks): array {
        $kata = preg_split('/\s+/', trim($teks)) ?: [];
        $baris = [];
        $kelompok = [];

        foreach ($kata as $i => $satu) {
            $kelompok[] = $satu;

            if (count($kelompok) === 3 || $i === count($kata) - 1) {
                $baris[] = implode(' ', $kelompok);
                $kelompok = [];
            }
        }

        return array_slice($baris, 0, 3);
    };

    // Daftar judul: satu per slide. Bila toko belum punya slide, tagline
    // dipakai sebagai judul tunggal — supaya hero tetap punya teks utama.
    //
    // Sengaja berupa array judul (bukan "slide semu" berbentuk objek): objek
    // tiruan dengan properti closure tidak bisa dicetak, dan itulah yang
    // membuat halaman 500 pada toko yang belum punya slide.
    $daftarJudul = $slides->isNotEmpty()
        ? $slides->map(fn ($s) => (string) $s->judul())->values()->all()
        : [(string) $toko->teks('site_tagline', __('site.brand.tagline'))];

    // Badge "02 GULIR" dan penghitung memakai jumlah slide YANG TAYANG.
    // Sebelumnya angka ini diambil dari count($slides), dan itu benar, tetapi
    // judulnya tetap judul pertama — pasangan yang membingungkan: tertulis
    // 2 slide, tulisannya tidak pernah berganti.
    $barisJudul = array_map($pecahBaris, $daftarJudul);

    $jumlahSlide = $slides->count();
@endphp

<section class="hero-race relative isolate overflow-hidden"
         x-data="{
            i: 0,
            n: {{ max(1, $jumlahSlide) }},
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

    {{-- ══ Latar: gambar slide mengisi seluruh hero ══
         `absolute inset-0 -z-10` memisahkan latar dari isi, sehingga isi bisa
         memakai flex biasa tanpa saling menimpa. Gradasi gelap dipasang CSS
         di atas gambar supaya judul tetap terbaca. --}}
    <div class="absolute inset-0 -z-10">
        @forelse ($slides as $idx => $slide)
            @php
                $urlSlide = \App\Models\ProductMedia::urlMedia($slide->media_path);
                $urlPonsel = filled($slide->media_path_mobile)
                    ? \App\Models\ProductMedia::urlMedia($slide->media_path_mobile)
                    : null;
            @endphp

            <div class="hero-race__slide absolute inset-0" data-aktif="{{ $idx === 0 ? 'true' : 'false' }}"
                 :data-aktif="i === {{ $idx }} ? 'true' : 'false'">
                <picture>
                    @if ($urlPonsel)
                        <source media="(max-width: 639px)" srcset="{{ $urlPonsel }}" />
                    @endif
                    <img src="{{ $urlSlide }}" alt="{{ $slide->judul() }}"
                         style="object-position: {{ $slide->posisiFokus() }}"
                         class="h-full w-full object-cover"
                         @if ($idx === 0) fetchpriority="high" @else loading="lazy" @endif
                         decoding="async" />
                </picture>
            </div>
        @empty
            <div class="hero-race__kosong" aria-hidden="true">
                <svg viewBox="0 0 400 160" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 120h150l40-40h120l20 20h30" />
                    <circle cx="120" cy="120" r="18" />
                    <circle cx="300" cy="100" r="12" />
                </svg>
            </div>
        @endforelse

        {{-- Lapisan gelap: menerangkan seluruh bidang tanpa menghapus gambar. --}}
        <div class="hero-race__selubung absolute inset-0" aria-hidden="true"></div>
    </div>

    {{-- ══ Isi hero ══
         flex-col + justify-between; blok tengah `flex-1 items-center
         justify-center` supaya judul tepat di tengah bidang, berapa pun
         tinggi layarnya. Padding horizontal simetris kiri-kanan. --}}
    <div class="hero-race__isi relative flex h-full flex-col justify-between
                px-5 py-6 sm:px-8 md:px-10 md:py-8 lg:px-12 lg:py-10">

        {{-- ── Baris atas ──
             Label "HOME" (crumb) DIHAPUS: di beranda ia duduk persis di
             belakang sidebar kiri, jadi selalu tertutup dan hanya menyisakan
             tumpukan teks.

             Tombol: bahasa, suka, cari, keranjang — empat tindakan yang
             paling sering dipakai pembeli, semuanya DI LUAR sidebar (sidebar
             memuat menu, sosial, tema, dan bahasanya sendiri).

             Suka dan keranjang ditulis langsung di sini, bukan memakai
             komponen Livewire: keduanya harus ikut berubah saat pengunjung
             menekan suka atau menambah barang di halaman ini. Itu didengar
             lewat event `suka-berubah` dan `keranjang-berubah` yang sudah
             dipancarkan komponen lain — tanpa polling, tanpa permintaan baru. --}}
        <div class="flex flex-none items-center justify-end gap-3">
            <div class="flex items-center gap-2">
                @php $localeLain = \App\Http\Middleware\SetLocale::lain(app()->getLocale()); @endphp
                <a href="{{ route('front.lang', $localeLain) }}" class="hero-race__pill"
                   title="{{ __('site.nav.language') }}">{{ strtoupper(app()->getLocale()) }}</a>

                {{-- Tema. Hero ini selalu gelap, jadi tombolnya tidak mengubah
                     hero — yang berubah adalah sisa halaman di bawahnya.
                     Tombolnya tetap disediakan di sini karena di beranda
                     inilah satu-satunya tempat yang selalu terlihat tanpa
                     membuka menu. --}}
                <button type="button" class="hero-race__pill" data-aksi="ganti-tema"
                        title="{{ __('site.nav.theme') }}" aria-label="{{ __('site.nav.theme') }}">
                    <span data-tema-ikon="gelap" class="hidden"><x-ikon name="sun" :size="16" /></span>
                    <span data-tema-ikon="terang" class="block"><x-ikon name="moon" :size="16" /></span>
                </button>

                <button type="button" class="hero-race__pill" data-aksi="buka-cari"
                        title="{{ __('site.nav.search') }}" aria-label="{{ __('site.nav.search') }}">
                    <x-ikon name="search" :size="16" />
                </button>

                {{-- Suka: menuju daftar produk yang disukai bila toko punya
                     halamannya, dan tetap menjadi tujuan yang jelas bagi
                     pembeli yang belum pernah menekan tombol suka mana pun. --}}
                <a href="{{ route('front.products.index') }}" class="hero-race__pill hero-race__pill--badge"
                   title="{{ __('site.product.liked') }}" aria-label="{{ __('site.product.liked') }}">
                    <x-ikon name="star" :size="16" />
                    <span data-hero-suka aria-hidden="true">0</span>
                </a>

                {{-- Keranjang. Angkanya diisi app.js dari penanda
                     `data-jumlah-keranjang` yang memuat komponen
                     front.jumlah-keranjang — satu hitungan untuk seluruh
                     halaman, ikut berubah lewat event `keranjang-berubah`. --}}
                <a href="{{ route('front.cart') }}" wire:navigate
                   class="hero-race__pill hero-race__pill--badge"
                   title="{{ __('site.nav.cart') }}" aria-label="{{ __('site.nav.cart') }}">
                    <x-ikon name="cart" :size="16" />
                    <span data-hero-keranjang aria-hidden="true">0</span>
                </a>
            </div>
        </div>

        {{-- ── Tengah: judul + indikator bawah judul ──
             `min-h-0` penting di dalam flex-column: tanpa itu blok tengah
             menolak menyusut dan mendorong kaki hero keluar layar. --}}
        <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 text-center
                    md:items-end md:gap-4 md:text-right">

            {{-- Judul per slide, ditumpuk di tempat yang sama dan ditukar
                 bersama gambar (`i === n`). Ditumpuk, bukan diganti isi teks,
                 supaya tinggi blok tidak melompat ketika judul slide
                 berikutnya lebih panjang dan animasi tulis tetap berjalan
                 dari awal pada tiap pergantian. --}}
            @foreach ($barisJudul as $idx => $baris)
                <h1 class="hero-race__judul" x-show="i === {{ $idx }}" x-cloak
                    @if ($idx === 0) style="display: grid" @endif>
                    @foreach ($baris as $b => $satuBaris)
                        <span class="hero-race__baris" style="--tulis-ke: {{ $b }}">
                            <span>{{ $satuBaris }}</span>
                        </span>
                    @endforeach
                </h1>
            @endforeach

            @if ($jumlahSlide > 1)
                <p class="hero-race__bawah-judul">
                    <b x-text="String(i + 1).padStart(2, '0')">01</b>
                    {{ __('site.home.hero_scroll') }}
                </p>
            @endif
        </div>

        {{-- ── Kaki: deskripsi, lalu garis, lalu sosial + kendali ──
             Semua yang dulu melayang di tengah gambar sekarang di sini.
             Di mobile satu kolom bertumpuk rapi (items-center); di layar
             lebar dua sisi (justify-between). --}}
        <div class="hero-race__kaki flex flex-none flex-col items-center gap-3
                    md:items-end md:gap-4">

            {{-- Angka keranjang untuk tombol di baris atas.

                 Sumbernya komponen Livewire yang sudah ada
                 (`front.jumlah-keranjang`), bukan hitungan kedua: komponen itu
                 sudah mendengarkan `keranjang-berubah`, jadi angkanya selalu
                 ikut berubah tanpa permintaan tambahan. Disembunyikan
                 (`hidden`) karena yang terlihat adalah tombol di hero;
                 angkanya dibaca dengan `textContent` oleh skrip app.js. --}}
            <span class="hidden" data-jumlah-keranjang data-jumlah-suka="{{ (int) count($suka['disukai'] ?? []) }}" aria-hidden="true">
                <livewire:front.jumlah-keranjang wire:key="badge-hero" />
            </span>

            <p class="hero-race__deskripsi max-w-xl px-1 text-center md:text-right">
                {{ $toko->teks('site_description') }}
            </p>

            {{-- Garis di atas baris kendali DIHAPUS: hero berlatarkan gambar
                 gelap penuh, jadi garis 1px di situ tidak memisahkan apa pun
                 — ia hanya menambah tepi yang bertumpuk dengan garis
                 pemisah sidebar di kiri. Kendali slide kini di tengah. --}}
            <div class="hero-race__dasar flex w-full flex-wrap items-center justify-center gap-3">

                {{-- Sosial DIHAPUS dari sini.
                     Ikonnya tertumpuk dengan sidebar kiri (sidebar juga
                     memuat sosial, di kaki sidebar), dan di layar lebar
                     keduanya terlihat dobel. Sidebar adalah tempat yang benar
                     untuk navigasi; kaki hero sekarang hanya memuat kendali
                     slide. --}}
                @if ($jumlahSlide > 1)
                    <div class="hero-race__alat flex items-center gap-4">
                        <div class="hero-race__hitung flex items-baseline gap-1" aria-hidden="true">
                            <b x-text="String(i + 1).padStart(2, '0')">01</b>
                            <span>/ {{ str_pad((string) $jumlahSlide, 2, '0', STR_PAD_LEFT) }}</span>
                        </div>

                        <div class="hero-race__geser flex items-center gap-1">
                            @foreach ($slides as $idx => $slide)
                                <button type="button" @click="i = {{ $idx }}; berhenti()"
                                        :aria-current="i === {{ $idx }} ? 'true' : 'false'"
                                        aria-label="{{ __('site.home.hero_scroll') }} {{ $idx + 1 }}">
                                    <span></span>
                                </button>
                            @endforeach
                        </div>

                        <button type="button" class="hero-race__pill"
                                @click="jeda = !jeda; jeda ? berhenti() : mulai()"
                                :aria-label="jeda ? 'Jalankan slide' : 'Jeda slide'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <g x-show="!jeda"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></g>
                                <path x-show="jeda" x-cloak d="M7 4l13 8-13 8z"/>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
