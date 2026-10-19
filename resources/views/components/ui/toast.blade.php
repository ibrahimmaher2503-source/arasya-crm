@props(['dismissLabel' => 'Dismiss'])

<div {{ $attributes->merge(['class' => 'ui-toast', 'role' => 'status', 'aria-live' => 'polite', 'data-ui-toast' => '']) }}>
    <div class="ui-toast__message">
        {{ $slot }}
    </div>
    <x-ui.icon-button :label="$dismissLabel" data-dismiss-toast>
        <span aria-hidden="true">×</span>
    </x-ui.icon-button>
</div>
