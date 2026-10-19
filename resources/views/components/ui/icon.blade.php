@props(['name' => 'building'])
@php
    $paths = [
        'building' => 'M3 21h18M6 21V4h12v17M9 8h1m4 0h1m-6 4h1m4 0h1m-5 9v-5h4v5',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m16-6a4 4 0 0 1 4 4v2M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0m4-3a4 4 0 0 1 0 8',
        'shield' => 'M12 3 3 7v5c0 5 5 8 9 10 4-2 9-5 9-10V7l-9-4m-4 9 3 3 5-6',
        'audit' => 'M6 3h8l4 4v14H6V3m8 0v5h4M9 12h6m-6 4h6',
        'search' => 'M21 21l-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
        'plus' => 'M12 5v14M5 12h14',
        'edit' => 'm15 4 5 5M4 20l5-1L21 7l-5-5L4 14v6',
        'arrow' => 'M19 12H5m6-6-6 6 6 6',
        'mail' => 'M3 5h18v14H3V5m0 0 9 8 9-8',
        'lock' => 'M5 10h14v11H5V10m3 0V7a4 4 0 0 1 8 0v3m-4 5v2',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12m13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
        'clock' => 'M12 7v5l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'help' => 'M9 8a3 3 0 1 1 5 2c-2 1-2 2-2 3m0 4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
        'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0',
        'sun' => 'M12 2v2m0 16v2M2 12h2m16 0h2M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2m-3 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'grid' => 'M3 3h7v7H3V3m11 0h7v7h-7V3M3 14h7v7H3v-7m11 0h7v7h-7v-7',
        'phone' => 'M5 3h4l2 5-3 2a15 15 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2C10 21 3 14 3 5a2 2 0 0 1 2-2',
        'target' => 'M21 11a9 9 0 1 1-8-8m8-1-9 10m4-10v6h6M16 12a4 4 0 1 1-4-4',
        'filter' => 'M3 4h18l-7 8v7l-4 2v-9L3 4',
        'more' => 'M12 5h.01M12 12h.01M12 19h.01',
        'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'ui-icon', 'viewBox' => '0 0 24 24', 'aria-hidden' => 'true', 'focusable' => 'false']) }}><path d="{{ $paths[$name] ?? $paths['building'] }}" /></svg>
