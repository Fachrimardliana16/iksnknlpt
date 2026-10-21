{{-- 05 / FAQ — pertanyaan yang sering diajukan.

     Diletakkan SETELAH CTA, bukan sebelum: pengunjung yang masih ragu biasanya
     membaca CTA lalu mencari jawaban, dan FAQ adalah tempat terakhir sebelum ia
     memutuskan menghubungi toko. Isinya dikelola dari panel (Situs · FAQ).

     Memakai <details>/<summary> asli peramban, bukan Alpine. Alasannya bukan
     kemalasan: accordion buatan sendiri harus mengurus fokus, tombol Enter,
     pembaca layar, dan pencarian dalam halaman (Ctrl+F tidak menemukan teks
     yang tersembunyi oleh CSS). <details> sudah punya semuanya, bekerja tanpa
     JavaScript, dan Ctrl+F membuka sendiri butir yang cocok. --}}
@if ($faq->isNotEmpty())
    <section class="container-page py-10 lg:py-24" data-reveal>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <span class="part-no">05 / {{ __('site.home.faq_title') }}</span>
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

        @php
            // Dikelompokkan supaya delapan pertanyaan berderet tidak menjadi
            // satu dinding teks. Urutan kelompok mengikuti urutan kemunculan
            // pertama, jadi admin tetap yang menentukan lewat display_order.
            $perKelompok = $faq->groupBy('group_key');
        @endphp

        <div class="mt-6 space-y-8 lg:mt-10">
            @foreach ($perKelompok as $kunci => $butir)
                <div>
                    <h3 class="part-no mb-2">{{ $butir->first()->labelKelompok() }}</h3>

                    <div class="divide-y divide-line-soft border-y border-line-soft">
                        @foreach ($butir as $item)
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
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
