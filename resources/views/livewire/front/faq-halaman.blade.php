{{-- Halaman /faq: seluruh pertanyaan aktif, dikelompokkan. --}}

<div class="container-page py-10 lg:py-16">

    <span class="part-no">{{ __('site.faq.title') }}</span>
    <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ __('site.faq.title') }}</h1>
    <p class="mt-2 max-w-2xl text-muted">{{ __('site.faq.meta') }}</p>

    @if ($daftar->isEmpty())
        <div class="plate mt-8 p-8 text-center">
            <x-ikon name="info" :size="28" class="mx-auto text-dim" />
            <p class="mt-3 text-sm text-muted">{{ __('site.faq.empty') }}</p>
        </div>
    @else
        <div class="mt-8 space-y-10">
            @foreach ($daftar->groupBy('group_key') as $butir)
                <div>
                    <h2 class="part-no mb-2">{{ $butir->first()->labelKelompok() }}</h2>

                    <div class="divide-y divide-line-soft border-y border-line-soft">
                        @foreach ($butir as $item)
                            {{-- <details> asli: bisa dicari dengan Ctrl+F, bekerja
                                 tanpa JavaScript, dan pembaca layar sudah paham. --}}
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
    @endif
</div>
