@props(['name' => 'dot', 'size' => 20, 'class' => ''])

@php
    // Seluruh ikon memakai viewBox 24x24. Ikon garis memakai stroke, ikon
    // merek memakai isian. Tidak ada berkas gambar ikon yang diunggah admin,
    // supaya bentuknya konsisten di seluruh situs.
    $garis = [
        'home' => '<path d="M3 10.6 12 3.2l9 7.4V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'grid' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'cart' => '<path d="M3 4h2.2l2.4 11.2h11.1L21 7H6"/><circle cx="9" cy="19.5" r="1.4"/><circle cx="18" cy="19.5" r="1.4"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20.5 20.5 16 16"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'check' => '<path d="M4 12.5 9 17.5 20 6"/>',
        'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 9.5v4.5M12 17.2h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.8h.01"/>',
        'activity' => '<path d="M2 12h4l3-8 4 16 3-8h6"/>',
        'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 21v-9"/>',
        'receipt' => '<path d="M5 3h14v18l-2.3-1.6L14.4 21l-2.4-1.6L9.6 21l-2.3-1.6L5 21z"/><path d="M9 8h6M9 12.5h6"/>',
        'sliders' => '<path d="M4 7h6M14 7h6M4 17h10M18 17h2"/><circle cx="12" cy="7" r="2"/><circle cx="16" cy="17" r="2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3.5"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M2 20h20"/>',
        'shield' => '<path d="M12 3l8 3v6c0 4.8-3.3 8-8 9-4.7-1-8-4.2-8-9V6z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'printer' => '<path d="M6 9V3h12v6M6 18H4v-6h16v6h-2M8 14h8v7H8z"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'image' => '<path d="M3 5h18v14H3z"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 15.5 15.5 10 4.5 19.5"/>',
        'share' => '<path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 3v13M7 8l5-5 5 5"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.1a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7A2 2 0 0 1 22 16.9z"/>',
        'mail' => '<path d="M3 6h18v12H3z"/><path d="m3 7 9 6.5L21 7"/>',
        'tag' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0l-8-8V3h9.6l8.4 8.4a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'card' => '<path d="M2 6h20v12H2z"/><path d="M2 10.5h20"/>',
        'map' => '<path d="M12 22s7-6.3 7-12A7 7 0 0 0 5 10c0 5.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
        'dots' => '<path d="M12 6h.01M12 12h.01M12 18h.01"/>',
        'copy' => '<path d="M9 9h11v11H9z"/><path d="M5 15V4h11"/>',
        'eye' => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        // eye-off: celah mata ditutup garis miring.
        //
        // Ditambahkan karena tombol aktif/nonaktif dulu memakai ikon yang SAMA
        // untuk kedua keadaan, jadi tidak ada cara membaca status hanya dari
        // ikonnya — harus membaca title, yang di ponsel tidak pernah muncul.
        'eye-off' => '<path d="M2 12s3.6-6.5 10-6.5a10 10 0 0 1 5 1.4M22 12s-3.6 6.5-10 6.5a10 10 0 0 1-5-1.4"/><path d="M3 3l18 18"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.6-6.4M21 4v5h-5"/>',
        'lock' => '<path d="M5 11h14v10H5z"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/>',
        'star' => '<path d="m12 3 2.8 5.9 6.2.9-4.5 4.4 1.1 6.2L12 17.5 6.4 20.4l1.1-6.2L3 9.8l6.2-.9z"/>',
        'bell' => '<path d="M18 9a6 6 0 1 0-12 0c0 5.2-2 6-2 6h16s-2-.8-2-6"/><path d="M10.3 19a2 2 0 0 0 3.4 0"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3.5V16h-7z"/><circle cx="7" cy="18.5" r="1.6"/><circle cx="17.5" cy="18.5" r="1.6"/>',
    ];

    $isi = [
        'whatsapp' => '<path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.74.46 3.44 1.32 4.94L2 22l5.36-1.4a9.8 9.8 0 0 0 4.68 1.2h.01c5.43 0 9.84-4.4 9.84-9.84 0-2.63-1.02-5.1-2.88-6.96A9.78 9.78 0 0 0 12.04 2zm0 1.8c2.15 0 4.17.84 5.69 2.36a7.98 7.98 0 0 1 2.36 5.68c0 4.44-3.61 8.04-8.05 8.04a8.1 8.1 0 0 1-4.1-1.12l-.3-.18-3.05.8.81-2.97-.19-.31a8 8 0 0 1-1.23-4.26c0-4.43 3.62-8.04 8.06-8.04zM8.5 7.3c-.18 0-.47.07-.71.34-.24.27-.93.91-.93 2.22 0 1.31.95 2.57 1.08 2.75.13.18 1.85 2.95 4.6 4.02 2.28.9 2.75.72 3.24.67.5-.04 1.6-.65 1.83-1.28.23-.63.23-1.17.16-1.28-.07-.11-.25-.18-.53-.32-.27-.14-1.6-.79-1.85-.88-.25-.09-.43-.14-.61.13-.18.27-.7.88-.86 1.06-.16.18-.32.2-.59.07a7.4 7.4 0 0 1-2.18-1.35 8.2 8.2 0 0 1-1.5-1.87c-.16-.27-.02-.42.11-.55.12-.12.27-.32.4-.48.14-.16.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.13-.6-1.45-.83-1.98-.2-.48-.4-.42-.55-.43l-.47-.01z"/>',
        'instagram' => '<path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9A5.5 5.5 0 0 1 16.5 22h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm0 1.8A3.7 3.7 0 0 0 3.8 7.5v9a3.7 3.7 0 0 0 3.7 3.7h9a3.7 3.7 0 0 0 3.7-3.7v-9a3.7 3.7 0 0 0-3.7-3.7zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4zM17.3 5.6a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2z"/>',
        'facebook' => '<path d="M14.5 8.5h3V5.4h-3c-2.3 0-4 1.8-4 4.1v2H8.3v3.1h2.2V22h3.2v-7.4h2.6l.5-3.1h-3.1V9.6c0-.7.3-1.1.8-1.1z"/>',
        'youtube' => '<path d="M21.6 7.2a2.9 2.9 0 0 0-2-2C17.8 4.7 12 4.7 12 4.7s-5.8 0-7.6.5a2.9 2.9 0 0 0-2 2A30 30 0 0 0 2 12a30 30 0 0 0 .4 4.8 2.9 2.9 0 0 0 2 2c1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5a2.9 2.9 0 0 0 2-2A30 30 0 0 0 22 12a30 30 0 0 0-.4-4.8zM10 15.2V8.8L15.5 12z"/>',
        'tiktok' => '<path d="M15.9 3h-2.8v11.6a2.4 2.4 0 1 1-2.4-2.4c.2 0 .5 0 .7.1V9.4a5.5 5.5 0 0 0-.7-.1 5.3 5.3 0 1 0 5.3 5.3V8.9a6.4 6.4 0 0 0 3.9 1.3V7.4a3.9 3.9 0 0 1-4-4z"/>',
        'shopee' => '<path d="M12 2 4 5v7.5c0 4.6 3.3 8.3 8 9.5 4.7-1.2 8-4.9 8-9.5V5zM9 11.5a3 3 0 0 0 3 2.2c1.4 0 2.3-.7 2.3-1.7 0-1.1-1-1.5-2.6-1.9-1.9-.5-2.9-1.2-2.9-2.7C8.8 6 10 5.2 11.6 5.2c1.5 0 2.6.7 3 1.8l-1.3.6c-.3-.6-.9-1-1.7-1-.9 0-1.5.4-1.5 1.1 0 .7.6 1 2.1 1.4 2.2.6 3.3 1.3 3.3 3 0 1.9-1.5 3.1-3.7 3.1A4.4 4.4 0 0 1 7.5 12z"/>',
        'tokopedia' => '<path d="M12 2 3 6v6c0 5 3.8 9.3 9 10 5.2-.7 9-5 9-10V6zM8.2 9.4a1.6 1.6 0 1 1 0 3.2 1.6 1.6 0 0 1 0-3.2zm7.6 0a1.6 1.6 0 1 1 0 3.2 1.6 1.6 0 0 1 0-3.2zM12 15.4c-1.8 0-3.3-.9-4-2.2h8c-.7 1.3-2.2 2.2-4 2.2z"/>',
    ];
@endphp

@if (isset($isi[$name]))
    <svg {{ $attributes->merge(['class' => $class]) }} width="{{ $size }}" height="{{ $size }}"
        viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
        {!! $isi[$name] !!}
    </svg>
@else
    <svg {{ $attributes->merge(['class' => $class]) }} width="{{ $size }}" height="{{ $size }}"
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
        stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">
        {!! $garis[$name] ?? '<circle cx="12" cy="12" r="3"/>' !!}
    </svg>
@endif
