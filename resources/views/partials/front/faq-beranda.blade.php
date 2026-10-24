{{-- 05 / FAQ — pertanyaan yang sering diajukan.

     Diletakkan SETELAH CTA, bukan sebelum: pengunjung yang masih ragu biasanya
     membaca CTA lalu mencari jawaban, dan FAQ adalah tempat terakhir sebelum ia
     memutuskan menghubungi toko. Isinya dikelola dari panel (Situs · FAQ).

     TIDAK SEMUA ditampilkan. Dulu delapan pertanyaan berderet menjadi satu
     dinding teks yang mendorong katalog keluar layar. Sekarang hanya beberapa
     butir pertama yang tampil; sisanya dibuka lewat tombol "Lihat lebih banyak".

     Teknis yang perlu dicatat:

     1. Butir yang disembunyikan TETAP DI DOM. Bukan `hidden`, yang
        mengeluarkan elemen dari pohon aksesibilitas dan membuat Ctrl+F tidak
        menemukannya. Yang dipakai adalah x-show, yang cuma mengatur
        display — teksnya tetap ada dan tetap bisa dicari.
     2. Transisi memakai grid-template-rows: 0fr → 1fr, bukan max-height.
        Transisi max-height butuh angka piksel yang pasti, dan tinggi isi yang
        berubah (jawaban panjang, font pembesaran) selalu memotongnya. 0fr/1fr
        bekerja untuk tinggi berapa pun.
     3. Tiap butir tetap memakai <details>/<summary> asli peramban. Accordion
        buatan sendiri harus mengurus fokus, tombol Enter, dan pembaca layar;
        <details> sudah punya semuanya dan bekerja tanpa JavaScript.

     Batas tampil diambil dari konfigurasi, bukan angka di template, supaya
     bisa diubah tanpa menyentuh berkas ini. --}}

@php
    /** @var \Illuminate\Support\Collection $faq */
    $faq = $faq ?? collect();
@endphp

@if ($faq->isNotEmpty())
    @php
        // Dikelompokkan supaya delapan pertanyaan berderet tidak menjadi satu
        // dinding teks. Urutan kelompok mengikuti urutan kemunculan pertama,
        // jadi admin tetap yang menentukan lewat display_order.
        $perKelompok = $faq->groupBy('group_key');

        // Butir ke-berapa yang mulai disembunyikan. Dihitung dari seluruh FAQ
        // beranda, bukan per kelompok, supaya jumlah yang tampil tetap sama
        // walaupun isinya berasal dari beberapa kelompok.
        $tampil = (int) config('iksan.home.faq_visible', 4);
        $adaSisa = $faq->count() > $tampil;
    @endphp

    <section class="container-page py-10 lg:py-24" data-reveal x-data="{ terbuka: false }">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">06 / {{ __('site.home.faq_title') }}</span>
                <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.home.faq_title') }}</h2>
                <p class="mt-2 max-w-2xl text-muted">{{ __('site.home.faq_subtitle') }}</p>
            </div>

            {{-- Tautan "semua FAQ" hanya muncul bila halaman itu benar-benar
                 terdaftar. Route::has() diuji DUA KALI — sekali untuk memutuskan,
                 sekali untuk membuat alamat — karena rute dapat berubah antara
                 keduanya pada aplikasi lain, dan kesalahan di sini berarti
                 seluruh beranda gagal dirender, bukan hanya tombolnya. --}}
            @php
                $adaHalamanFaq = \Illuminate\Support\Facades\Route::has('front.faq');
            @endphp
            @if ($adaHalamanFaq)
                <a href="{{ url('/faq') }}" class="btn btn-outline">
                    {{ __('site.home.faq_all') }}
                    <x-ikon name="arrow-right" :size="16" />
                </a>
            @endif
        </div>

        <div class="mt-6 space-y-8 lg:mt-10">
            @php
                // Penghitung lintas kelompok: satu daftar utuh, bukan per
                // kelompok. Ditulis sebagai blok, bukan @php($nomor = 0),
                // karena Blade masih mengurai komentar Blade setelah baris itu
                // sebagai bagian dari direktif yang sama.
                $nomor = 0;
            @endphp
            @foreach ($perKelompok as $butir)
                <div>
                    <h3 class="part-no mb-2">{{ $butir->first()->labelKelompok() }}</h3>

                    <div class="divide-y divide-line-soft border-y border-line-soft">
                        @foreach ($butir as $item)
                            @php
                                $sisa = $nomor >= $tampil;
                                $nomor++;
                            @endphp

                            {{-- Butir sisa: dibuka oleh tombol. x-show menjaga
                                 teksnya tetap ada di DOM — lihat catatan di
                                 atas mengapa bukan `hidden`. --}}
                            <div @if ($sisa) x-show="terbuka" x-cloak @endif
                                 @if ($sisa && $adaSisa)
                                     x-transition:enter="transition-[grid-template-rows] duration-300 ease-out"
                                     x-transition:enter-start="grid grid-rows-[0fr]"
                                     x-transition:enter-end="grid grid-rows-[1fr]"
                                     x-transition:leave="transition-[grid-template-rows] duration-200 ease-in"
                                     x-transition:leave-start="grid grid-rows-[1fr]"
                                     x-transition:leave-end="grid grid-rows-[0fr]"
                                 @endif>
                                <details class="group faq-item">
                                    <summary class="flex cursor-pointer items-center justify-between gap-4 py-4 text-left">
                                        <span class="text-base font-medium sm:text-lg">{{ $item->pertanyaan() }}</span>
                                        <x-ikon name="chevron-down" :size="18"
                                                class="flex-none text-dim transition-transform duration-200 group-open:rotate-180" />
                                    </summary>
                                    <div class="pb-4 pr-8 text-sm leading-relaxed text-muted">
                                        {!! nl2br(e($item->jawaban())) !!}
                                    </div>
                                </details>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tombol hanya dirender bila memang ada yang disembunyikan. Label
             berganti lewat x-text, dan aria-expanded memberi tahu pembaca
             layar keadaan tombol, bukan hanya perubahan visual. --}}
        @if ($adaSisa)
            <div class="mt-6 flex justify-center">
                <button type="button"
                        class="btn btn-outline"
                        x-on:click="terbuka = !terbuka"
                        :aria-expanded="terbuka ? 'true' : 'false'">
                    <span x-text="terbuka ? '{{ __('site.home.faq_less') }}' : '{{ __('site.home.faq_more') }}'">
                        {{ __('site.home.faq_more') }}
                    </span>
                    <x-ikon name="chevron-down" :size="16"
                            class="flex-none transition-transform duration-200"
                            ::class="terbuka && 'rotate-180'" />
                </button>
            </div>
        @endif
    </section>
@endif
