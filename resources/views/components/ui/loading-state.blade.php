@props(['label' => 'Loading'])

<div {{ $attributes->merge(['class' => 'ui-loading-state', 'role' => 'status', 'aria-live' => 'polite']) }}>
    <span class="ui-loading-state__spinner" aria-hidden="true"></span>
    <span>{{ $slot->isEmpty() ? $label : $slot }}</span>
</div>
