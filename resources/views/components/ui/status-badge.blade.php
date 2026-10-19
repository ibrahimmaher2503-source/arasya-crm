@props(['status' => 'neutral'])

@php
    $status = in_array($status, ['success', 'warning', 'danger', 'info', 'neutral'], true)
        ? $status
        : 'neutral';
@endphp

<span {{ $attributes->merge(['class' => 'ui-status-badge ui-status-badge--'.$status]) }}>
    {{ $slot }}
</span>
