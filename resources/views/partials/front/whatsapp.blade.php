@php
    /** @var \App\Support\Pengaturan $toko */
    $wa = preg_replace('/\D/', '', $toko->teks('whatsapp_number'));

    // Pesan berbeda per konteks halaman.
    $produk = request()->route('product');
    $template = $toko->teks('whatsapp_message_template');

    $pesan = match (true) {
        $produk instanceof \App\Models\Product => "Halo {$toko->teks('site_name')}, saya tertarik dengan {$produk->judul()} ({$produk->sku}). Stoknya masih ada?",
        request()->routeIs('front.cart') => "Halo, saya ingin menanyakan pesanan saya dengan ".app(\App\Services\Keranjang::class)->jumlahItem().' item.',
        request()->routeIs('front.products.*') => 'Halo, saya sedang melihat katalog. Boleh minta rekomendasi?',
        default => $template ?: "Halo {$toko->teks('site_name')}, saya ingin bertanya tentang knalpot.",
    };
@endphp

@if ($wa)
    {{-- Tombol WhatsApp melayang.

         ATURAN YANG PERNAH TERLANGAR, dan akibatnya tombol ini tidak muncul:

         1. `x-cloak` TIDAK dipakai di mana pun di blok ini. Alpine menghapus
            elemen ber-x-cloak yang dipindai ulang (atribut luar berubah di
            sekitarnya / Livewire memperbarui isi). Dulu tombol bulatnya
            memakai x-cloak, dan itulah sebabnya ia hilang dari layar.
         2. Tidak ada `:style` pada elemen yang sama dengan `x-data`. Atribut
            dinamis di situ memicu pemindaian ulang seluruh isi elemen, dan
            itulah kondisi yang memicu aturan (1). Posisi diatur lewat kelas
            CSS `.wa-melayang` saja.
         3. Posisi dasar ada di CSS, bukan di skrip: tanpa Alpine pun tombol
            tetap duduk di atas bar navigasi bawah.

         Tinggi bilah keranjang lengket (halaman detail produk) dihitung di
         tokens.css lewat `.wa-melayang[data-bar-tampil]`; statusnya ditulis
         oleh resources/js/bar-aksi.js ke atribut data pada <html>. --}}
    <div class="fixed z-30 wa-melayang" style="right: 20px;">
        <div id="wa-melayang"
             x-data="{
                buka: false,
                mulai() {
                    this.buka = !sessionStorage.getItem('iksan.wa.tutup');
                },
                setuju() { this.buka = true; sessionStorage.removeItem('iksan.wa.tutup'); },
                tutup() { this.buka = false; sessionStorage.setItem('iksan.wa.tutup', '1'); }
             }"
             x-init="mulai()"
             class="flex flex-col items-end">

            <div x-show="buka" x-transition.opacity
                 class="plate-up plate-besar mb-3 w-[min(78vw,19rem)] p-4"
                 role="dialog" aria-label="{{ __('site.home.cta_primary') }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-display text-lg font-semibold">
                            {{ $toko->teks('site_name', __('site.brand.fallback_name')) }}
                        </p>
                        <p class="mt-1 flex items-center gap-2 text-xs text-muted">
                            <span class="live-dot" aria-hidden="true"></span>
                            {{ $toko->teks('operational_hours', __('site.contact.hours')) }}
                        </p>
                    </div>
                    <button type="button" class="btn-icon !h-8 !w-8"
                            @click="tutup()">
                        <x-ikon name="close" :size="16" />
                        <span class="visually-hidden">{{ __('site.nav.close') }}</span>
                    </button>
                </div>

                <p class="mt-3 text-sm text-muted">{{ __('site.home.cta_body') }}</p>

                <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($pesan) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="btn btn-primary mt-4 w-full">
                    <x-ikon name="whatsapp" :size="18" />
                    {{ __('site.home.cta_primary') }}
                </a>
            </div>

            <button type="button"
                    @click="setuju()"
                    :aria-expanded="buka ? 'true' : 'false'"
                    aria-controls="wa-melayang"
                    class="ml-auto flex h-14 w-14 items-center justify-center rounded-full
                           bg-[#25D366] text-white shadow-[var(--shadow-3)]"
                    title="{{ __('site.home.cta_primary') }}">
                <x-ikon name="whatsapp" :size="26" />
                <span class="visually-hidden">{{ __('site.home.cta_primary') }}</span>
            </button>
        </div>
    </div>
@endif
