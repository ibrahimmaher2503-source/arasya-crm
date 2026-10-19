@props(['label' => null, 'variant' => 'ghost', 'type' => 'button'])

@php
    if (! filled($label)) {
        throw new \InvalidArgumentException('The x-ui.icon-button component requires a label.');
    }

    $variant = in_array($variant, ['primary', 'secondary', 'ghost', 'danger'], true) ? $variant : 'ghost';
    $type = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp

<button {{ $attributes->merge(['type' => $type, 'aria-label' => $label, 'class' => 'ui-button ui-button--'.$variant.' ui-icon-button']) }}>
    <span aria-hidden="true">{{ $slot }}</span>
</button>
