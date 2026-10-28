<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $judul ?? 'Panel' }} · {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}</title>

    <script nonce="{{ csp_nonce() }}">
        (function () {
            try {
                var t = localStorage.getItem('iksan.theme');
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire dimuat eksplisit. Tanpa ini, panel admin tampil tetapi seluruh
         tombol wire:click mati — termasuk notifikasi, tabel, dan aksi simpan. --}}
    @livewireStyles
</head>
<body class="min-h-screen bg-bg text-ink">

@php
    // Sidebar admin.
    //
    // Dikelompokkan per pekerjaan, bukan satu daftar panjang. Sebelumnya
    // sembilan menu berturut-turut tanpa pemisah, sehingga "Pengaturan" dan
    // "Pesanan" tampak sederajat padahal dipakai dengan frekuensi yang sangat
    // berbeda. Yang punya halaman baru (Produk, Kategori, Pesanan, Keranjang)
    // baru muncul setelah rutenya terdaftar, jadi menu tidak pernah menautkan
    // halaman yang belum ada.
    $penggunaPanel = auth()->user();
    $superAdmin = $penggunaPanel !== null && $penggunaPanel->role->bisaUbahKredensial();

    // Menu dibatasi per peran di sini, tapi itu TIDAK pengaman.
    //
    // Yang mengunci tetap pemeriksaan di server: route admin memakai middleware
    // PastikanAdmin (ditambah can() pada rute yang terbatas), dan setiap
    // tindakan sensitif memanggil authorize() di dalam komponennya.
    // Menyembunyikan tautan hanya membuat menu tidak ramai — permintaan yang
    // dikirim langsung tetap ditolak.
    //
    // Pengelompokan mengikuti PERTANYAAN yang dijawab tiap halaman, bukan
    // nama fiturnya: "apa yang perlu dikerjakan" (Penjualan, Katalog, Situs)
    // dipisah dari "siapa boleh masuk dan mesinnya bagaimana" (Sistem). Semua
    // butir Sistem khusus Super Admin, jadi kelompok itu lenyap seluruhnya
    // bagi Admin biasa — bukan setengah tampil.
    //
    // Hanya butir yang rutenya BENAR-BENAR terdaftar yang didaftar di sini;
    // tidak ada tautan cadangan "untuk nanti". Yang belum punya halaman tidak
    // muncul sama sekali.
    $menu = [
        [
            'kelompok' => null,
            'butir' => [
                ['rute' => 'admin.dashboard', 'pola' => 'admin.dashboard', 'ikon' => 'activity', 'label' => 'Dashboard', 'bawah' => true, 'super' => false],
            ],
        ],
        [
            'kelompok' => 'Penjualan',
            'butir' => [
                ['rute' => 'admin.orders.index', 'pola' => 'admin.orders.*', 'ikon' => 'receipt', 'label' => 'Pesanan', 'bawah' => true, 'super' => false],
                ['rute' => 'admin.carts.index', 'pola' => 'admin.carts.*', 'ikon' => 'cart', 'label' => 'Keranjang Ditinggalkan', 'bawah' => false, 'super' => false],
                ['rute' => 'admin.customers.index', 'pola' => 'admin.customers.*', 'ikon' => 'users', 'label' => 'Pelanggan', 'bawah' => false, 'super' => false],
                ['rute' => 'admin.coupons.index', 'pola' => 'admin.coupons.*', 'ikon' => 'tag', 'label' => 'Kupon', 'bawah' => true, 'super' => false],
                ['rute' => 'admin.kol.index', 'pola' => 'admin.kol.*', 'ikon' => 'share', 'label' => 'Performa KOL', 'bawah' => true, 'super' => false],
            ],
        ],
        [
            'kelompok' => 'Katalog',
            'butir' => [
                ['rute' => 'admin.products.index', 'pola' => 'admin.products.*', 'ikon' => 'box', 'label' => 'Produk', 'bawah' => true, 'super' => false],
                ['rute' => 'admin.categories.index', 'pola' => 'admin.categories.*', 'ikon' => 'tag', 'label' => 'Kategori', 'bawah' => false, 'super' => false],
            ],
        ],
        [
            'kelompok' => 'Situs',
            'butir' => [
                ['rute' => 'admin.hero.index', 'pola' => 'admin.hero.*', 'ikon' => 'image', 'label' => 'Hero', 'bawah' => false, 'super' => false],
                ['rute' => 'admin.faq.index', 'pola' => 'admin.faq.*', 'ikon' => 'info', 'label' => 'FAQ', 'bawah' => false, 'super' => false],
            ],
        ],
        [
            // Seluruh kelompok ini khusus Super Admin. Diletakkan terakhir dan
            // dipisahkan dari pekerjaan harian karena pertanyaannya berbeda:
            // bukan "apa yang perlu dikerjakan" tapi "siapa boleh masuk, dan
            // mesinnya bagaimana".
            'kelompok' => 'Sistem',
            'butir' => [
                // Pengguna & Peran mengelola siapa yang boleh masuk panel.
                // Admin biasa boleh mengerjakan pesanan dan produk, bukan
                // menaikkan peran orang lain.
                ['rute' => 'admin.users.index', 'pola' => 'admin.users.*', 'ikon' => 'shield', 'label' => 'Pengguna & Peran', 'bawah' => false, 'super' => true],
                ['rute' => 'admin.settings.index', 'pola' => 'admin.settings.*', 'ikon' => 'sliders', 'label' => 'Pengaturan', 'bawah' => false, 'super' => true],
                ['rute' => 'admin.security.index', 'pola' => 'admin.security.*', 'ikon' => 'shield', 'label' => 'Keamanan', 'bawah' => false, 'super' => true],
                ['rute' => 'admin.backup.index', 'pola' => 'admin.backup.*', 'ikon' => 'download', 'label' => 'Cadangan', 'bawah' => false, 'super' => true],
            ],
        ],
    ];

    // Butir yang rutenya belum terdaftar tidak dirender sama sekali.
    $menu = collect($menu)->map(function (array $g) use ($superAdmin): array {
        $g['butir'] = array_values(array_filter(
            $g['butir'],
            fn (array $m): bool => \Illuminate\Support\Facades\Route::has($m['rute'])
                // Butir 'super' hanya untuk Super Admin. Untuk Admin biasa
                // hilang dari sidebar, tapi rutenya sendiri masih ada —
                // yang menolak tetap pemeriksaan di server.
                && (! ($m['super'] ?? false) || $superAdmin),
        ));

        return $g;
    })->filter(fn (array $g): bool => $g['butir'] !== [])->all();

    // Bar bawah hanya memuat lima yang paling sering dipakai.
    $bawah = array_values(array_filter(
        array_merge(...array_map(fn (array $g): array => $g['butir'], $menu)),
        fn (array $m): bool => $m['bawah'],
    ));

    $bawah = array_slice($bawah, 0, 5);

    $aktif = fn (string $pola) => request()->routeIs($pola);

    /*
     * Kelompok yang tidak boleh tertutup.
     *
     * Hanya yang memuat halaman yang SEDANG dibuka. Kelompok lain bebas
     * dilipat, dan pilihan admin disimpan di peramban (lihat di bawah).
     *
     * Dihitung di PHP, bukan di Alpine: keadaan awal yang salah akan
     * menyembunyikan tautan menuju halaman tempat admin sedang berdiri sampai
     * skrip sisi klien berjalan dan memperbaikinya — dan kalau skrip gagal
     * dimuat, tautannya tidak kembali sama sekali.
     */
    $grupTerbuka = collect($menu)
        ->filter(fn (array $g): bool => $g['kelompok'] !== null
            && collect($g['butir'])->contains(fn (array $m): bool => $aktif($m['pola'])))
        ->pluck('kelompok')
        ->all();

    // Semua nama kelompok yang punya kepala kelompok — dipakai sisi klien
    // untuk membaca pilihan lipat tersimpan. Kelompok tanpa nama (Dashboard)
    // tidak dilipat, jadi tak perlu tercatat.
    $semuaKelompok = collect($menu)
        ->map(fn (array $g): ?string => $g['kelompok'])
        ->filter()
        ->values()
        ->all();

    // Dua butir yang paling sering dibuka admin, dipasang sebagai tindakan
    // cepat di kepala panel supaya tidak perlu mencari di sidebar.
    $cepat = collect(array_merge(...array_map(fn (array $g): array => $g['butir'], $menu)))
        ->filter(fn (array $m): bool => in_array($m['rute'], ['admin.orders.index', 'admin.carts.index'], true))
        ->all();
@endphp

<div x-data="{ ringkas: localStorage.getItem('iksan.admin.ringkas') === '1', drawer: false, akun: false }"
     @class(['min-h-screen lg:pl-[260px]', 'lg:!pl-[72px]' => false])
     :class="ringkas ? 'lg:pl-[72px]' : 'lg:pl-[260px]'">

    {{-- ── Sidebar desktop ────────────────────────────────────────── --}}
    <aside class="fixed inset-y-0 left-0 z-40 hidden border-r border-line-soft bg-surface lg:flex lg:flex-col"
           :class="ringkas ? 'w-[72px]' : 'w-[260px]'">

        <div class="flex h-16 flex-none items-center gap-2 border-b border-line-soft px-3">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2" wire:navigate>
                <span class="torque-stripe flex-none" aria-hidden="true"><i></i><i></i><i></i></span>
                <span x-show="!ringkas" class="truncate font-display text-lg font-semibold">
                    {{ $toko->teks('site_name') }}
                </span>
            </a>
        </div>

        <nav class="flex-1 overflow-y-auto p-2" aria-label="Navigasi panel" x-data="{
                // Keadaan lipat per kelompok. Nilai awal dari PHP: kelompok yang
                // memuat halaman yang sedang dibuka SELALU terbuka, yang lain
                // terbuka secara bawaan. Pilihan admin kemudiannya disimpan di
                // localStorage per kelompok sehingga bertahan antar halaman.
                //
                // Sengaja satu peta di sini, bukan x-data terpisah per kelompok:
                // versi per-kelompok memakai x-init untuk membaca localStorage,
                // dan x-init berjalan SETELAH keadaan awal dipasang sehingga
                // selalu menimpanya. localStorage kosong (kunjungan pertama)
                // menghasilkan `null !== '0'` = true, jadi semua kelompok kembali
                // terbuka walau sudah dilipat — lipatnya tampak mati.
                //
                // Kelompok yang sedang aktif tidak dapat ditutup: menutupnya
                // menyembunyikan jalan kembali ke tempat admin sedang berdiri.
                grup: {{ json_encode($grupTerbuka, JSON_HEX_APOS | JSON_HEX_QUOT) }},
                kunciPrefix: 'iksan.admin.grup.',

                terbuka(nama) {
                    return this.grup.includes(nama);
                },

                alihkan(nama) {
                    if (this.kunciAktif.includes(nama)) return;

                    const i = this.grup.indexOf(nama);
                    if (i === -1) {
                        this.grup.push(nama);
                    } else {
                        this.grup.splice(i, 1);
                    }

                    try {
                        localStorage.setItem(this.kunciPrefix + nama, this.terbuka(nama) ? '1' : '0');
                    } catch (e) {}
                },

                // Dipakai untuk menolak penutupan kelompok yang sedang aktif.
                kunciAktif: {{ json_encode($grupTerbuka, JSON_HEX_APOS | JSON_HEX_QUOT) }},

                // Daftar SEMUA nama kelompok, dipakai init() untuk membaca
                // localStorage. Kelompok tanpa nama (Dashboard, tanpa kepala
                // kelompok) tidak dilipat sehingga tidak perlu tercatat.
                semuaKelompok: {{ json_encode($semuaKelompok, JSON_HEX_APOS | JSON_HEX_QUOT) }},

                init() {
                    // Baca pilihan admin SEKALI, sebelum ada yang diklik. Nilai
                    // bawaan dari PHP tetap dipakai untuk kelompok yang belum
                    // pernah disentuh.
                    try {
                        for (const nama of Object.keys(this.semuaKelompok)) {
                            const tersimpan = localStorage.getItem(this.kunciPrefix + nama);
                            if (tersimpan === null) continue;
                            if (tersimpan === '0') {
                                const i = this.grup.indexOf(nama);
                                if (i !== -1) this.grup.splice(i, 1);
                            } else if (!this.grup.includes(nama)) {
                                this.grup.push(nama);
                            }
                        }
                    } catch (e) {}
                }
             }"
             x-init="init()">

            @foreach ($menu as $grup)
                @if ($grup['kelompok'] !== null)
                    {{-- Judul kelompok sekaligus tombol lipat. Seluruh kelompoknya
                         dapat ditutup supaya yang jarang dipakai tidak mendorong
                         yang harian keluar layar.

                         Saat sidebar ringkas tombolnya hilang dan seluruh butir
                         ikut tampil: hanya ikon yang terlihat, jadi yang
                         disembunyikan malah membuat ikon tidak berarti. --}}
                    <button type="button" x-show="!ringkas" @click="alihkan('{{ $grup['kelompok'] }}')"
                            :aria-expanded="terbuka('{{ $grup['kelompok'] }}')"
                            class="flex w-full min-h-[44px] items-center gap-1 rounded-[var(--r-kecil)] px-3 pb-1 pt-4 text-left hover:bg-sunken">
                        <span class="part-no flex-1">{{ $grup['kelompok'] }}</span>
                        <x-ikon name="chevron-down" :size="14" class="flex-none text-dim transition-transform"
                                ::class="terbuka('{{ $grup['kelompok'] }}') ? '' : '-rotate-90'" />
                        <span class="visually-hidden">Lipat kelompok {{ $grup['kelompok'] }}</span>
                    </button>

                    <div x-show="ringkas" class="mx-3 my-3 border-t border-line-soft" aria-hidden="true"></div>

                    <div x-show="terbuka('{{ $grup['kelompok'] }}') || ringkas">
                        @foreach ($grup['butir'] as $item)
                            <a href="{{ route($item['rute']) }}" wire:navigate
                               class="flex min-h-[44px] items-center gap-3 rounded-[var(--r-kecil)] px-3 text-sm
                                      {{ $aktif($item['pola']) ? 'bg-sunken font-medium text-accent' : 'text-muted hover:bg-sunken hover:text-ink' }}"
                               :title="ringkas ? '{{ $item['label'] }}' : null">
                                <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                                <span x-show="!ringkas" class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    @foreach ($grup['butir'] as $item)
                        <a href="{{ route($item['rute']) }}" wire:navigate
                           class="flex min-h-[44px] items-center gap-3 rounded-[var(--r-kecil)] px-3 text-sm
                                  {{ $aktif($item['pola']) ? 'bg-sunken font-medium text-accent' : 'text-muted hover:bg-sunken hover:text-ink' }}"
                           :title="ringkas ? '{{ $item['label'] }}' : null">
                            <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                            <span x-show="!ringkas" class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        <div class="flex-none border-t border-line-soft p-2">
            <button type="button"
                    @click="ringkas = !ringkas; localStorage.setItem('iksan.admin.ringkas', ringkas ? '1' : '0')"
                    class="flex min-h-[44px] w-full items-center gap-3 px-3 text-sm text-muted hover:text-ink">
                <x-ikon name="chevron-left" :size="20" class="flex-none transition-transform"
                        ::class="ringkas ? 'rotate-180' : ''" />
                <span x-show="!ringkas">Ringkas</span>
                <span class="visually-hidden">Ringkas sidebar</span>
            </button>
        </div>
    </aside>

    {{-- ── Drawer tablet dan mobile ───────────────────────────────── --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-black/50" @click="drawer = false" x-transition.opacity></div>

        <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-xs flex-col bg-surface"
             x-transition:enter="transition-transform duration-200"
             x-transition:enter-start="-translate-x-full"
             x-transition:leave="transition-transform duration-150"
             x-transition:leave-end="-translate-x-full">

            <div class="flex h-16 flex-none items-center justify-between border-b border-line-soft px-3">
                <div class="flex items-center gap-2">
                    <span class="torque-stripe" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="font-display text-lg font-semibold">{{ $toko->teks('site_name') }}</span>
                </div>
                <button type="button" class="btn-icon" @click="drawer = false">
                    <x-ikon name="close" />
                    <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                </button>
            </div>

            {{-- Navigasi drawer.

                 State lipat dipisah dari sidebar desktop: keduanya tak pernah
                 tampil bersamaan (drawer hanya untuk layar kecil), dan satu
                 state membuat memilih lipat di satu tempat langsung mengubah
                 yang lain tanpa ada yang menyadarinya.

                 State yang SAMA tetap: `grupAktif` adalah kelompok yang memuat
                 halaman yang sedang dibuka, dan itu tidak boleh tertutup —
                 menutupnya menyembunyikan jalan kembali ke tempat admin sedang
                 berdiri.

                 localStorage dipakai supaya pilihan lipat bertahan antar
                 halaman, sama seperti sidebar desktop. --}}
            <nav class="flex-1 overflow-y-auto p-2" x-data="{
                    grup: {{ json_encode($grupTerbuka, JSON_HEX_APOS | JSON_HEX_QUOT) }},
                    grupAktif: {{ json_encode($grupTerbuka, JSON_HEX_APOS | JSON_HEX_QUOT) }},
                    semuaKelompok: {{ json_encode($semuaKelompok, JSON_HEX_APOS | JSON_HEX_QUOT) }},
                    kunciPrefix: 'iksan.admin.grup.',

                    terbuka(nama) {
                        return this.grup.includes(nama);
                    },

                    alihkan(nama) {
                        if (this.grupAktif.includes(nama)) return;

                        const i = this.grup.indexOf(nama);
                        if (i === -1) {
                            this.grup.push(nama);
                        } else {
                            this.grup.splice(i, 1);
                        }

                        try {
                            localStorage.setItem(this.kunciPrefix + nama, this.terbuka(nama) ? '1' : '0');
                        } catch (e) {}
                    },

                    init() {
                        // Pilihan tersimpan dibaca SEKALI di sini, bukan lewat
                        // x-init terpisah: x-init berjalan setelah keadaan awal
                        // dipasang, sehingga menimpanya — dan localStorage kosong
                        // menghasilkan true, membuat semua kelompok kembali
                        // terbuka walau sudah dilipat.
                        try {
                            for (const nama of this.semuaKelompok) {
                                const tersimpan = localStorage.getItem(this.kunciPrefix + nama);
                                if (tersimpan === null) continue;
                                if (tersimpan === '0') {
                                    const i = this.grup.indexOf(nama);
                                    if (i !== -1) this.grup.splice(i, 1);
                                } else if (!this.grup.includes(nama)) {
                                    this.grup.push(nama);
                                }
                            }
                        } catch (e) {}
                    }
                 }"
                 x-init="init()">

                @foreach ($menu as $grup)
                    @if ($grup['kelompok'] !== null)
                        <button type="button" @click="alihkan('{{ $grup['kelompok'] }}')"
                                :aria-expanded="terbuka('{{ $grup['kelompok'] }}')"
                                class="flex w-full min-h-[48px] items-center gap-1 rounded-[var(--r-kecil)] px-3 pb-1 pt-4 text-left hover:bg-sunken">
                            <span class="part-no flex-1">{{ $grup['kelompok'] }}</span>
                            <x-ikon name="chevron-down" :size="14" class="flex-none text-dim transition-transform"
                                    ::class="terbuka('{{ $grup['kelompok'] }}') ? '' : '-rotate-90'" />
                            <span class="visually-hidden">Lipat kelompok {{ $grup['kelompok'] }}</span>
                        </button>

                        <div x-show="terbuka('{{ $grup['kelompok'] }}')">
                            @foreach ($grup['butir'] as $item)
                                <a href="{{ route($item['rute']) }}" wire:navigate @click="drawer = false"
                                   class="flex min-h-[48px] items-center gap-3 rounded-[var(--r-kecil)] px-3 text-base
                                          {{ $aktif($item['pola']) ? 'bg-sunken font-medium text-accent' : 'text-muted' }}">
                                    <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        @foreach ($grup['butir'] as $item)
                            <a href="{{ route($item['rute']) }}" wire:navigate @click="drawer = false"
                               class="flex min-h-[48px] items-center gap-3 rounded-[var(--r-kecil)] px-3 text-base
                                      {{ $aktif($item['pola']) ? 'bg-sunken font-medium text-accent' : 'text-muted' }}">
                                <x-ikon :name="$item['ikon']" :size="20" class="flex-none" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </nav>

            <div class="flex-none border-t border-line-soft p-3">
                <a href="{{ route('front.home') }}" class="btn btn-outline w-full" target="_blank" rel="noopener">
                    Lihat situs
                </a>
            </div>
        </div>
    </div>

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 border-b border-line-soft bg-surface/95 backdrop-blur">
        <div class="flex h-16 items-center gap-2 px-3 lg:px-6">

            <button type="button" class="btn-icon lg:hidden" @click="drawer = true">
                <x-ikon name="menu" />
                <span class="visually-hidden">{{ __('site.nav.open_menu') }}</span>
            </button>

            <div class="min-w-0 flex-1">
                <p class="part-no truncate">{{ $bagian ?? ($judul ?? 'Panel') }}</p>
                <p class="truncate font-display text-lg font-semibold">{{ $judul ?? 'Dashboard' }}</p>
            </div>

            {{-- Tindakan cepat: dua halaman yang paling sering dibuka admin,
                 supaya tidak perlu mencarinya di sidebar. --}}
            <div class="hidden items-center gap-1 lg:flex">
                @foreach ($cepat as $c)
                    <a href="{{ route($c['rute']) }}" wire:navigate
                       class="btn btn-ghost {{ $aktif($c['pola']) ? 'text-accent' : '' }}"
                       title="{{ $c['label'] }}">
                        <x-ikon :name="$c['ikon']" :size="17" />
                        <span class="hidden xl:inline">{{ $c['label'] }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Notifikasi: stok menipis, stok habis, pesanan dibeli,
                 keranjang terisi, dan pesanan yang hampir batal. --}}
            <livewire:admin.lonceng-notifikasi />

            {{-- Pesan dari halaman kontak. Lonceng tersendiri, bukan satu
                 bagian dari lonceng notifikasi: keduanya menjawab pertanyaan
                 berbeda, dan pesan dari pembeli biasanya perlu dibalas cepat. --}}
            <livewire:admin.lonceng-pesan />

            <a href="{{ route('front.home') }}" target="_blank" rel="noopener" class="btn-icon hidden lg:inline-flex"
               title="Lihat situs">
                <x-ikon name="eye" />
                <span class="visually-hidden">Lihat situs</span>
            </a>

            {{-- Tombol tema. Sama seperti di sisi depan: memakai penanda
                 data-aksi, bukan onclick inline yang diblokir CSP. --}}
            <button type="button" class="btn-icon" data-aksi="ganti-tema"
                    title="{{ __('site.nav.theme') }}" aria-label="{{ __('site.nav.theme') }}">
                <span data-tema-ikon="gelap" class="hidden"><x-ikon name="sun" /></span>
                <span data-tema-ikon="terang" class="block"><x-ikon name="moon" /></span>
            </button>

            <div class="relative" @click.outside="akun = false">
                <button type="button" class="btn-icon" @click="akun = !akun" :aria-expanded="akun">
                    <x-ikon name="users" />
                    <span class="visually-hidden">Akun</span>
                </button>

                <div x-show="akun" x-cloak x-transition.opacity
                     class="plate-up absolute right-0 top-full mt-1 w-56 py-1">
                    <div class="border-b border-line-soft px-3 py-2">
                        <p class="truncate text-sm font-medium">{{ auth()->user()?->name }}</p>
                        <p class="truncate text-xs text-muted">{{ auth()->user()?->role?->label() }}</p>
                    </div>

                    @if (\Illuminate\Support\Facades\Route::has('admin.profile'))
                        <a href="{{ route('admin.profile') }}" class="block px-3 py-2 text-sm hover:bg-sunken">
                            Profil dan keamanan
                        </a>
                    @endif

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-danger hover:bg-sunken">
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    {{-- ── Isi ────────────────────────────────────────────────────── --}}
    <main class="px-3 py-4 pb-24 lg:px-6 lg:py-6 lg:pb-8">
        {{ $slot }}
    </main>

    {{-- ── Bar navigasi bawah: tablet dan mobile ─────────────────── --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line-soft bg-surface lg:hidden"
         style="padding-bottom: env(safe-area-inset-bottom, 0px)"
         aria-label="Navigasi panel">
        <div class="grid grid-cols-5">
            @foreach ($bawah as $item)
                @continue(! \Illuminate\Support\Facades\Route::has($item['rute']))
                <a href="{{ route($item['rute']) }}" wire:navigate
                   class="relative flex min-h-[60px] flex-col items-center justify-center gap-1
                          {{ $aktif($item['pola']) ? 'text-accent' : 'text-muted' }}">
                    <x-ikon :name="$item['ikon']" :size="22" />
                    <span class="text-[0.6875rem] font-medium leading-none">{{ $item['label'] }}</span>

                    @if ($aktif($item['pola']))
                        <span class="absolute inset-x-6 top-0 h-0.5 bg-accent" aria-hidden="true"></span>
                    @endif
                </a>
            @endforeach
        </div>
    </nav>
</div>

    @livewireScripts
</body>
</html>
