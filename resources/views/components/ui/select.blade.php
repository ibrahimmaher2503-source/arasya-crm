@props(['id' => null, 'name' => null, 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

@php
    $describedBy = trim(implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        $hint && $id ? $id . '-hint' : null,
        $error && $id ? $id . '-error' : null,
    ])));
@endphp

<select {{ $attributes->except(['aria-describedby', 'aria-invalid'])->merge([
    'class' => 'ui-select',
    'id' => $id,
    'name' => $name,
    'aria-describedby' => $describedBy ?: null,
    'aria-invalid' => $error ? 'true' : null,
]) }}>{{ $slot }}</select>
