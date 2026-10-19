@props(['title', 'subtitle' => null, 'identifier' => null])

<header {{ $attributes->merge(['class' => 'ui-stack']) }}>
    <div class="ui-inline">
        <div class="ui-stack">
            <h2>{{ $title }}</h2>

            @if ($subtitle !== null)
                <p class="ui-muted">{{ $subtitle }}</p>
            @endif

            @if ($identifier !== null)
                <p class="ui-muted">{{ $identifier }}</p>
            @endif
        </div>

        @if (isset($status) || isset($actions))
            <div class="ui-stack">
                @if (isset($status) && trim((string) $status) !== '')
                    <div>{{ $status }}</div>
                @endif

                @if (isset($actions) && trim((string) $actions) !== '')
                    <div class="ui-inline" role="group" aria-label="{{ __('Entity actions') }}">{{ $actions }}</div>
                @endif
            </div>
        @endif
    </div>
</header>
