@props(['id' => null, 'name' => null, 'value' => null, 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

@php
    $describedBy = trim(implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        $hint && $id ? $id . '-hint' : null,
        $error && $id ? $id . '-error' : null,
    ])));
@endphp

<textarea {{ $attributes->except(['aria-describedby', 'aria-invalid'])->merge([
    'class' => 'ui-textarea',
    'id' => $id,
    'name' => $name,
    'aria-describedby' => $describedBy ?: null,
    'aria-invalid' => $error ? 'true' : null,
]) }}>{{ $value ?? $slot }}</textarea>
