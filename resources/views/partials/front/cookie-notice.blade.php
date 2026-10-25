{{--
    Pemberitahuan cookie.

    Muncul sekali per peramban, sampai pembeli menekan "Setuju". Yang disetujui
    adalah cookie NON-ESENSIAL — di situs ini hanya cookie zona pengiriman
    (`iksan_zona`) yang mengingat negara pilihan pembeli. Cookie esensial (sesi,
    CSRF, bahasa) tetap tertulis tanpa persetujuan: tanpa itu situs tidak dapat
    berjalan, dan meminta izin untuk sesuatu yang wajib ada hanya membingungkan.

    Tombolnya memakai `data-aksi`, bukan `onclick=`: CSP situs ini tidak memuat
    'unsafe-inline', sehingga atribut onclick tidak pernah dijalankan.
--}}
<div id="cookie-notice"
     class="fixed inset-x-3 bottom-20 z-40 hidden lg:inset-x-auto lg:bottom-4 lg:right-4 lg:max-w-sm"
     role="region"
     aria-label="{{ __('site.cookie.title') }}">

    <div class="plate p-4 shadow-lg">
        <p class="text-sm font-semibold">{{ __('site.cookie.title') }}</p>

        <p class="mt-1 text-xs text-muted">
            {{ __('site.cookie.body') }}
        </p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-aksi="cookie-setuju">
                {{ __('site.cookie.accept') }}
            </button>

            <a href="{{ route('front.page', 'kebijakan-privasi') }}" class="btn btn-ghost btn-sm">
                {{ __('site.cookie.learn_more') }}
            </a>
        </div>
    </div>
</div>
