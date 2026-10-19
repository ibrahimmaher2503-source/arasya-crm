@props(['id' => null, 'name' => null, 'value' => null, 'type' => 'text', 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

@php
    $describedBy = trim(implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        $hint && $id ? $id . '-hint' : null,
        $error && $id ? $id . '-error' : null,
    ])));
@endphp

<input {{ $attributes->except(['aria-describedby', 'aria-invalid'])->merge([
    'class' => 'ui-input',
    'type' => $type,
    'id' => $id,
    'name' => $name,
    'value' => $value,
    'aria-describedby' => $describedBy ?: null,
    'aria-invalid' => $error ? 'true' : null,
]) }}>
