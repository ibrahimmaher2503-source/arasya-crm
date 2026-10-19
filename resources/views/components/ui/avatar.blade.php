@props(['name'])

@php
    $nameParts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = implode('', array_map(
        fn ($part) => mb_substr($part, 0, 1),
        array_slice($nameParts, 0, 2),
    ));
@endphp

<span {{ $attributes->merge(['class' => 'ui-avatar', 'role' => 'img', 'aria-label' => (string) $name]) }}>
    <span aria-hidden="true">{{ $initials }}</span>
</span>
