@props(['variant' => 'text'])

@php
    $variant = in_array($variant, ['text', 'circle', 'block'], true) ? $variant : 'text';
@endphp

<span {{ $attributes->merge(['class' => 'ui-skeleton ui-skeleton--'.$variant, 'aria-hidden' => 'true']) }}></span>
