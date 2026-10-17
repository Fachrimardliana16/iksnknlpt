@props(['judul' => null, 'nomor' => null, 'kelas' => ''])

{{-- Nomor bagian monospace dua digit, seperti nomor item pada lembar rakitan. --}}
<div {{ $attributes->merge(['class' => 'mb-6 '.$kelas]) }}>
    <div class="flex items-center gap-3">
        <span class="torque-stripe" aria-hidden="true"><i></i><i></i><i></i></span>
        @if ($nomor)
            <span class="part-no">{{ $nomor }}</span>
        @endif
    </div>
    @if ($judul)
        <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">{{ $judul }}</h2>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-3 max-w-2xl text-muted">{{ $slot }}</div>
    @endif
</div>
