<div>
    <div class="container-page py-8 lg:py-12">

        <nav class="part-no mb-6" aria-label="Breadcrumb">
            <a href="{{ route('front.home') }}" class="hover:text-accent">{{ __('site.nav.home') }}</a>
            <span class="mx-2">/</span>
            <a href="{{ route('front.products.index') }}" class="hover:text-accent">{{ __('site.nav.products') }}</a>
            <span class="mx-2">/</span>
            <span>{{ $product->category?->nama() }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_1fr] lg:gap-12">

            {{-- Galeri --}}
            {{-- Carousel geser: gambar utama tersusun berjajar di dalam wadah
                 scroll-snap horizontal, sehingga bisa digeser (swipe) dengan
                 jari maupun trackpad. Sebelumnya hanya ada SATU elemen <img>
                 yang src-nya ditukar saat thumbnail diklik — tidak ada yang
                 bisa digeser sama sekali.

                 Thumbnail dan gambar utama memakai satu indeks `aktif`:
                 menggeser gambar memperbarui thumbnail, dan menekan thumbnail
                 menggulir ke gambar yang sama. --}}
            <div x-data="{
                    aktif: 0,
                    geserKe(i) {
                        this.aktif = i;
                        const trek = this.$refs.trek;
                        if (!trek) return;
                        trek.scrollTo({ left: trek.clientWidth * i, behavior: 'smooth' });
                    },
                    sinkron() {
                        const trek = this.$refs.trek;
                        if (!trek || trek.clientWidth === 0) return;
                        this.aktif = Math.round(trek.scrollLeft / trek.clientWidth);
                    }
                 }">
                @php $daftarMedia = $product->media; @endphp

                @if ($daftarMedia->isEmpty())
                    <div class="plate flex aspect-[4/3] items-center justify-center text-dim">
                        <x-ikon name="image" :size="40" />
                    </div>
                @else
                    <div class="relative">
                        <div x-ref="trek"
                             @scroll.debounce.80ms="sinkron()"
                             class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth
                                    [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                             style="touch-action: pan-x pan-y; overscroll-behavior-x: contain;">

                            @foreach ($daftarMedia as $i => $m)
                                <div class="w-full flex-none snap-center"
                                     wire:key="media-{{ $m->id ?? $i }}">
                                    <div class="plate aspect-[4/3] overflow-hidden">
                                        <img src="{{ $m->url('full') }}"
                                             width="{{ $m->width ?: 1600 }}" height="{{ $m->height ?: 1200 }}"
                                             alt="{{ $m->alt() }}"
                                             @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                                             decoding="async"
                                             draggable="false"
                                             class="pointer-events-none h-full w-full select-none object-cover">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($daftarMedia->count() > 1)
                            {{-- Penunjuk posisi: tampil di ponsel karena thumbnail
                                 saja tidak cukup jelas menunjukkan ada gambar lain. --}}
                            <div class="pointer-events-none absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
                                @foreach ($daftarMedia as $i => $m)
                                    <span class="h-1.5 rounded-full transition-all duration-300"
                                          :class="aktif === {{ $i }} ? 'w-5 bg-accent' : 'w-1.5 bg-white/70'"></span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($daftarMedia->count() > 1)
                        <div class="mt-3 grid grid-cols-5 gap-2">
                            @foreach ($daftarMedia as $i => $m)
                                <button type="button"
                                        @click="geserKe({{ $i }})"
                                        :aria-current="aktif === {{ $i }} ? 'true' : 'false'"
                                        aria-label="Gambar {{ $i + 1 }} dari {{ $daftarMedia->count() }}"
                                        class="aspect-[4/3] overflow-hidden border transition-colors"
                                        :class="aktif === {{ $i }} ? 'border-accent' : 'border-line-soft'">
                                    <img src="{{ $m->url('thumb') }}" width="320" height="240"
                                         alt="{{ $m->alt() }}" loading="lazy" decoding="async"
                                         class="h-full w-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            {{-- Informasi dan pembelian --}}
            <div class="lg:sticky lg:top-24 lg:self-start">
                <p class="part-no">{{ $product->category?->nama() }}</p>

                <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ $product->judul() }}</h1>

                <p class="tech mt-2 text-sm text-dim">
                    {{ __('site.product.sku') }}: {{ $product->sku }}
                </p>

                <p class="tech mt-5 text-3xl font-medium tabular-nums">{{ rupiah($harga) }}</p>
                <x-harga-asing :nominal="$harga" class="mt-1" />

                @if ($tersedia > 0)
                    <p class="mt-1 text-sm text-ok">{{ __('site.product.in_stock', ['count' => $tersedia]) }}</p>
                @else
                    <p class="mt-1 text-sm text-danger">{{ __('site.product.out_of_stock') }}</p>
                @endif

                @if ($product->variants->where('is_active', true)->count() > 1)
                    <div class="measure-line my-6"></div>
                    <p class="label">{{ __('site.product.variant') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->variants->where('is_active', true)->sortBy('display_order') as $v)
                            <button type="button" wire:click="pilihVarian({{ $v->id }})"
                                    @disabled($v->habis())
                                    class="btn min-h-[40px] px-3 text-sm
                                        {{ $variantId === $v->id ? 'btn-primary' : 'btn-outline' }}
                                        {{ $v->habis() ? '!opacity-40' : '' }}">
                                {{ $v->nama() }}
                                @if ($v->price_adjustment != 0)
                                    <span class="tech text-xs">
                                        {{ $v->price_adjustment > 0 ? '+' : '' }}{{ number_format((float) $v->price_adjustment, 0, ',', '.') }}
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="measure-line my-6"></div>

                <div class="flex items-end gap-3">
                    <div>
                        <label class="label" for="jumlah">{{ __('site.product.quantity') }}</label>
                        <div class="flex items-center">
                            {{-- aria-label wajib: isinya hanya gambar, sehingga tanpa ini
                                 pembaca layar hanya membacakan "tombol". --}}
                            <button type="button" class="btn-icon !border !border-line"
                                    aria-label="{{ __('site.product.decrease_qty') }}"
                                    wire:click="turunkanJumlah">
                                <x-ikon name="minus" :size="16" />
                            </button>
                            <input id="jumlah" type="number" min="1" max="{{ max(1, $tersedia) }}"
                                   class="field !w-20 !min-h-[44px] text-center"
                                   wire:model.live="jumlah">
                            <button type="button" class="btn-icon !border !border-line"
                                    aria-label="{{ __('site.product.increase_qty') }}"
                                    wire:click="naikkanJumlah">
                                <x-ikon name="plus" :size="16" />
                            </button>
                        </div>
                    </div>
                </div>

                @if ($pesan)
                    <div class="plate mt-4 p-3 text-sm {{ $nada === 'danger' ? 'text-danger' : 'text-ok' }}">
                        {{ $pesan }}
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <button type="button" class="btn btn-primary flex-1" wire:click="tambah"
                            data-aksi-utama
                            @disabled($tersedia < 1)>
                        <x-ikon name="cart" :size="18" />
                        {{ __('site.product.add_to_cart') }}
                    </button>
                    <button type="button" class="btn btn-outline flex-1" wire:click="tambah(true)"
                            @disabled($tersedia < 1)>
                        {{ __('site.product.buy_now') }}
                    </button>
                </div>

                {{-- Bagikan ke WhatsApp (checklist F6-05). Tombolnya hanya muncul
                     bila nomor WhatsApp terisi di pengaturan; tanpa nomor, tautan
                     wa.me mengarah ke halaman galat WhatsApp. --}}
                @if ($tautanWhatsApp)
                    <a href="{{ $tautanWhatsApp }}" target="_blank" rel="noopener noreferrer"
                       class="btn btn-ghost mt-3 w-full">
                        <x-ikon name="whatsapp" :size="18" />
                        {{ __('site.product.share_whatsapp') }}
                    </a>
                @endif

                {{-- Suka produk.

                     Berdampingan dengan tautan Bagikan, bukan menggantikannya:
                     membagikan menjawab "siapa lagi yang perlu lihat ini", suka
                     menjawab "produk mana yang dibutuhkan pembeli" — dan yang
                     kedua tidak punya tautan keluar, cuma satu ketukan.

                     Jumlah suka ikut tampil supaya pembeli melihat barang yang
                     memang diperhatikan orang lain. --}}
                <div class="mt-3 flex items-center gap-3">
                    {{-- Halaman detail memuat satu produk, jadi kuerinya memang
                         satu — tapi propertinya tetap diteruskan supaya kartu
                         katalog dan halaman detail memakai kontrak yang sama. --}}
                    <livewire:front.suka-produk
                        :produk-id="$product->id"
                        :disukai="isset($suka['disukai'][$product->id])"
                        :jumlah="(int) ($suka['hitungan'][$product->id] ?? 0)"
                        :key="'suka-detail-'.$product->id" />
                    <span class="text-xs text-dim">Sukai produk ini untuk menandai minat Anda</span>
                </div>

                <div class="measure-line my-6"></div>

                {{-- Deskripsi sebagai accordion. Default TERTUTUP: pengunjung
                     ponsel membuka halaman untuk melihat harga dan tombol beli,
                     bukan membaca paragraf. Isinya tetap ada di DOM (hanya
                     dilipat) sehingga Ctrl+F tetap menemukannya. --}}
                <div x-data="{ buka: false }">
                    <button type="button"
                            @click="buka = !buka"
                            :aria-expanded="buka ? 'true' : 'false'"
                            aria-controls="deskripsi-produk"
                            class="flex w-full items-center justify-between gap-3 py-1 text-left">
                        <span class="label !mb-0">{{ __('site.product.description') }}</span>
                        <x-ikon name="chevron-down" :size="18"
                                class="flex-none text-muted transition-transform duration-300"
                                x-bind:class="buka ? 'rotate-180' : ''" />
                    </button>

                    <div id="deskripsi-produk" x-show="buka" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="mt-2 space-y-3 text-sm leading-relaxed text-muted">
                        @foreach (preg_split('/\n\s*\n/', trim($product->deskripsi())) as $paragraf)
                            <p>{{ trim($paragraf) }}</p>
                        @endforeach
                    </div>
                </div>

                {{-- Pratinjau 3D dan contoh suara. Tidak ikut dilipat: keduanya
                     pemutar, bukan teks bacaan — melipatnya hanya menambah satu
                     ketukan sebelum pembeli bisa memutarnya. --}}
                <x-media-produk :media3d="$media3d" :audio="$audio" :judul="$product->judul()" />

                @if ($spesifikasi !== [])
                    <div x-data="{ buka: window.innerWidth >= 1024 }">
                        <button type="button"
                                @click="buka = !buka"
                                :aria-expanded="buka ? 'true' : 'false'"
                                aria-controls="spesifikasi-produk"
                                class="flex w-full items-center justify-between gap-3 py-1 text-left">
                            <span class="label !mb-0">{{ __('site.product.specification') }}</span>
                            <x-ikon name="chevron-down" :size="18"
                                    class="flex-none text-muted transition-transform duration-300"
                                    x-bind:class="buka ? 'rotate-180' : ''" />
                        </button>

                        <div id="spesifikasi-produk" x-show="buka" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="mt-3 divide-y divide-[var(--c-line-soft)] border-y border-line-soft">
                            @foreach ($spesifikasi as $baris)
                                <div class="grid grid-cols-[minmax(0,9rem)_1fr] gap-3 py-2.5">
                                    <dt class="text-sm text-muted">{{ $baris['label'] }}</dt>
                                    <dd class="tech text-sm">{{ $baris['nilai'] }}</dd>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                {{-- Info produk (SKU, berat, varian, kategori) sebagai accordion. --}}
                <div x-data="{ buka: window.innerWidth >= 1024 }">
                    <button type="button"
                            @click="buka = !buka"
                            :aria-expanded="buka ? 'true' : 'false'"
                            aria-controls="info-produk"
                            class="flex w-full items-center justify-between gap-3 py-1 text-left">
                        <span class="label !mb-0">{{ __('site.product.product_info') }}</span>
                        <x-ikon name="chevron-down" :size="18"
                                class="flex-none text-muted transition-transform duration-300"
                                x-bind:class="buka ? 'rotate-180' : ''" />
                    </button>

                    <div id="info-produk" x-show="buka" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="mt-3 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="part-no">{{ __('site.product.sku') }}</dt>
                            <dd class="tech mt-1">{{ $product->sku }}</dd>
                        </div>
                        <div>
                            <dt class="part-no">{{ __('site.product.weight') }}</dt>
                            <dd class="tech mt-1">{{ number_format(($variant?->berat() ?? $product->weight_gram) / 1000, 2, ',', '.') }} kg</dd>
                        </div>
                        <div>
                            <dt class="part-no">{{ __('site.product.variant_col') }}</dt>
                            <dd class="tech mt-1">{{ $variant?->nama() ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="part-no">{{ __('site.footer.categories') }}</dt>
                            <dd class="tech mt-1">{{ $product->category?->nama() }}</dd>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bar aksi lengket. Hanya di bawah 1024px, tempat keranjang paling
             mungkin terlewat karena tombol ikut tergulir keluar layar.

             Isinya harga, pengatur jumlah, dan label tombol yang menyebut apa
             yang akan ditambahkan. Label statis "Tambah ke Keranjang" menyembunyikan
             dua hal yang mahal: jumlah yang sudah diubah, dan varian yang
             sedang dipilih.

             KAPAN MUNCUL. Sejak 2026-10 bar ini TERSEMBUNYI pada gulir
             pertama. Sebelumnya ia selalu tampil, sehingga di ponsel pengguna
             melihat dua tombol "Tambah ke Keranjang" yang sama di satu layar
             (panel dan bar) dan bar-nya menutupi isi. Sekarang ia baru muncul
             ketika tombol utama di panel sudah tidak terlihat —
             lihat resources/js/bar-aksi.js.

             `data-tersembunyi` dipakai, bukan kelas `hidden`: statusnya harus
             terbaca dari DOM, dan utilitas `hidden` Tailwind tidak bisa
             dibedakan dari `hidden` layar lebar. `inert` menahan fokus
             keyboard selama bar belum tampil. --}}
        <div class="bottom-bar-cart fixed inset-x-0 z-30 border-t border-line-soft bg-surface p-3 lg:hidden"
             data-tersembunyi inert
             style="bottom: calc(60px + env(safe-area-inset-bottom, 0px))">
            <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                    <p class="tech truncate text-lg font-medium">{{ rupiah($harga) }}</p>
                    <p class="truncate text-xs text-muted">
                        {{ $varian?->nama() ?? $product->judul() }}
                        @if ($tersedia > 0) · {{ __('site.product.in_stock', ['count' => $tersedia]) }} @endif
                    </p>
                </div>

                <div class="flex flex-none items-center">
                    <button type="button" class="btn-icon !h-10 !w-10 !border !border-line"
                            aria-label="{{ __('site.product.decrease_qty') }}" wire:click="turunkanJumlah">
                        <x-ikon name="minus" :size="14" />
                    </button>
                    <span class="tech w-9 text-center text-sm" aria-live="polite">{{ $jumlah }}</span>
                    <button type="button" class="btn-icon !h-10 !w-10 !border !border-line"
                            aria-label="{{ __('site.product.increase_qty') }}" wire:click="naikkanJumlah">
                        <x-ikon name="plus" :size="14" />
                    </button>
                </div>

                <button type="button" class="btn btn-primary flex-none !min-h-[44px]" wire:click="tambah"
                        @disabled($tersedia < 1)>
                    <x-ikon name="cart" :size="16" />
                    {{ __('site.product.add_to_cart') }}
                </button>
            </div>
        </div>

        @if ($terkait->isNotEmpty())
            <section class="mt-24 pb-24 lg:pb-0">
                <span class="part-no">02 / {{ __('site.product.related') }}</span>
                <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
                    @foreach ($terkait as $p)
                        <x-kartu-produk :produk="$p" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
