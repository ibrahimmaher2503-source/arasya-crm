@props(['allowed' => false])

{{-- Presentation visibility only; the action still requires server-side authorization. --}}
@if ($allowed === true)
    <div {{ $attributes->merge(['class' => 'ui-inline', 'role' => 'group', 'aria-label' => __('Available actions')]) }}>
        {{ $slot }}
    </div>
@endif
