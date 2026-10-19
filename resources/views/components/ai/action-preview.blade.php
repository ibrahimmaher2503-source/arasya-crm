@props(['title', 'description' => null])

<x-ui.detail-panel :title="$title" {{ $attributes }}>
    <div class="ui-stack">
        <x-ui.alert variant="info">{{ __('Preview only') }}</x-ui.alert>

        @if ($description !== null)
            <p>{{ $description }}</p>
        @endif

        {{ $slot }}
    </div>
</x-ui.detail-panel>
