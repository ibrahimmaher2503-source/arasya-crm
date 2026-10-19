@props(['variant' => 'info'])

@php
    $variant = $variant === 'error' ? 'danger' : $variant;
    $variant = in_array($variant, ['success', 'warning', 'danger', 'info'], true) ? $variant : 'info';
    $role = $variant === 'danger' ? 'alert' : 'status';
@endphp

<div {{ $attributes->merge(['class' => 'ui-alert ui-alert--'.$variant, 'role' => $role, 'aria-live' => $role === 'alert' ? 'assertive' : 'polite']) }}>
    {{ $slot }}
</div>
