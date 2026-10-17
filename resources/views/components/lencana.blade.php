@props(['nada' => 'neutral', 'teks' => null])

@php
    $kelas = match ($nada) {
        'accent' => 'badge badge-accent',
        'steel' => 'badge badge-steel',
        'ok' => 'badge badge-ok',
        'warn' => 'badge badge-warn',
        'danger' => 'badge badge-danger',
        'brass' => 'badge badge-accent',
        default => 'badge badge-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => $kelas]) }}>{{ $teks ?? $slot }}</span>
