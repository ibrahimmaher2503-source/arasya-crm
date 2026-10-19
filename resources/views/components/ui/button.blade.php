@props(['variant' => 'primary', 'type' => 'button'])

@php
    $variant = in_array($variant, ['primary', 'secondary', 'ghost', 'danger'], true) ? $variant : 'primary';
    $type = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => 'ui-button ui-button--'.$variant]) }}>
    {{ $slot }}
</button>
