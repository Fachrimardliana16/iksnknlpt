{{-- Paginasi gaya toko (checklist F11-09).

     Diterbitkan dari bawaan Livewire dengan dua perubahan:

     1. `aria-disabled` pada elemen <span> dibuang. Atribut itu hanya sah pada
        elemen yang memang dapat dinonaktifkan (button, input, fieldset).
        Pada <span> axe-core menandainya sebagai pelanggaran tingkat serius.
        Pemisah halaman aktif memang tidak dapat ditekan, cukup ditandai
        dengan gaya, bukan dengan atribut yang salah tempat.

     2. Warnanya disamakan dengan token tema supaya kontrasnya terjamin di
        kedua tema, bukan memakai abu-abu bawaan Tailwind.

     Kelasnya juga dirapikan mengikuti gaya panel: kotak, tepi tipis, aksen
     merah pada halaman aktif. --}}

@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="{{ __('pagination.navigation') }}" class="flex items-center justify-between gap-4">
            {{-- Tampilan sempit: hanya Sebelumnya / Berikutnya --}}
            <div class="flex justify-between flex-1 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="btn btn-outline opacity-50">{{ __('pagination.previous') }}</span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}" class="btn btn-outline">
                        {{ __('pagination.previous') }}
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                            x-on:click="{{ $scrollIntoViewJsSnippet }}" class="btn btn-outline">
                        {{ __('pagination.next') }}
                    </button>
                @else
                    <span class="btn btn-outline opacity-50">{{ __('pagination.next') }}</span>
                @endif
            </div>

            {{-- Tampilan lebar: nomor halaman --}}
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="part-no">
                        {{ __('pagination.showing', ['first' => $paginator->firstItem(), 'last' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
                    </p>
                </div>

                <div>
                    <span class="inline-flex items-center gap-1">
                        @if ($paginator->onFirstPage())
                            <span class="btn-icon opacity-40" aria-hidden="true">‹</span>
                        @else
                            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}" class="btn-icon"
                                    aria-label="{{ __('pagination.previous') }}">‹</button>
                        @endif

                        @foreach ($elements as $element)
                            @if (is_string($element))
                                <span class="btn-icon opacity-40" aria-hidden="true">{{ $element }}</span>
                            @endif

                            @if (is_array($element))
                                @foreach ($element as $page => $url)
                                    @if ($page == $paginator->currentPage())
                                        <span class="btn-icon !bg-accent !text-accent-fg !border-accent"
                                              aria-current="page">{{ $page }}</span>
                                    @else
                                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                                x-on:click="{{ $scrollIntoViewJsSnippet }}" class="btn-icon"
                                                aria-label="{{ __('pagination.go_to', ['page' => $page]) }}">{{ $page }}</button>
                                    @endif
                                @endforeach
                            @endif
                        @endforeach

                        @if ($paginator->hasMorePages())
                            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}" class="btn-icon"
                                    aria-label="{{ __('pagination.next') }}">›</button>
                        @else
                            <span class="btn-icon opacity-40" aria-hidden="true">›</span>
                        @endif
                    </span>
                </div>
            </div>
        </nav>
    @endif
</div>
