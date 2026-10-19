@props(['title'])

<x-ui.detail-panel :title="$title" {{ $attributes }}>
    <div class="ui-stack">
        <x-ui.status-badge status="info">{{ __('Preview only') }}</x-ui.status-badge>
        {{ $slot }}
    </div>
</x-ui.detail-panel>
