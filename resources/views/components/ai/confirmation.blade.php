@props(['title', 'confirmLabel' => 'Confirm'])

<x-ui.detail-panel :title="$title">
    <div class="ui-stack">
        <div>{{ $slot }}</div>
        <x-ui.button variant="primary" type="button" disabled>{{ $confirmLabel }}</x-ui.button>
        <p class="ui-muted">{{ __('Preview only') }}</p>
    </div>
</x-ui.detail-panel>
