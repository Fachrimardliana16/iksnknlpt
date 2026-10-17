<div class="w-full max-w-sm">

    <div class="flex items-center gap-3">
        <span class="torque-stripe" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="part-no">ADMIN / MASUK</span>
    </div>

    <h1 class="mt-4 font-display text-3xl font-semibold">
        {{ $tahapOtp ? 'Verifikasi dua faktor' : 'Masuk panel' }}
    </h1>

    <p class="mt-2 text-sm text-muted">
        {{ $tahapOtp
            ? 'Masukkan 6 angka dari aplikasi authenticator, atau satu kode cadangan.'
            : 'Panel ini hanya untuk pengelola toko.' }}
    </p>

    <div class="plate mt-6 p-5">
        @if ($tahapOtp)
            <form wire:submit="verifikasiOtp">
                <label class="label" for="otp">Kode verifikasi</label>
                <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code"
                       class="field tech text-center !text-2xl tracking-[0.4em]"
                       wire:model="otp" autofocus maxlength="10">

                <button type="submit" class="btn btn-primary mt-5 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="verifikasiOtp">Verifikasi</span>
                    <span wire:loading wire:target="verifikasiOtp">{{ __('common.loading') }}</span>
                </button>
            </form>
        @else
            <form wire:submit="masuk">
                <label class="label" for="email">Email</label>
                <input id="email" type="email" class="field" wire:model="email"
                       autocomplete="username" autofocus>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror

                <label class="label mt-4" for="password">Kata sandi</label>
                <input id="password" type="password" class="field" wire:model="password"
                       autocomplete="current-password">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror

                <label class="mt-4 flex cursor-pointer items-center gap-3 text-sm">
                    <input type="checkbox" wire:model="ingat" class="h-4 w-4 accent-[var(--c-accent)]">
                    <span>Ingat perangkat ini</span>
                </label>

                <button type="submit" class="btn btn-primary mt-5 w-full" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="masuk">Masuk</span>
                    <span wire:loading wire:target="masuk">{{ __('common.loading') }}</span>
                </button>
            </form>
        @endif

        @if ($galat)
            <div class="mt-4 border-l-2 border-l-danger pl-3 py-1 text-sm text-danger">
                {{ $galat }}
                @if ($tungguDetik)
                    <span class="tech block text-xs">Tunggu {{ $tungguDetik }} detik.</span>
                @endif
            </div>
        @endif
    </div>

    <a href="{{ route('front.home') }}" class="btn btn-ghost mt-4 w-full">
        <x-ikon name="chevron-left" :size="16" />
        {{ __('site.errors.back_home') }}
    </a>

    <p class="mt-6 text-center text-xs text-dim">
        Percobaan masuk dibatasi 5 per menit. Setelah 10 kegagalan dalam 30 menit,
        jaringan ini diblokir selama 1 jam.
    </p>
</div>
