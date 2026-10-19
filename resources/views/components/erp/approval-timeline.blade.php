@props(['events' => []])

<ol {{ $attributes->merge(['class' => 'ui-stack', 'aria-label' => __('Approval timeline')]) }}>
    @foreach ($events as $event)
        <li>
            <div class="ui-inline">
                <h3>{{ $event['title'] ?? '' }}</h3>

                @if (isset($event['status']) && $event['status'] !== '')
                    <x-ui.status-badge :status="$event['status']">{{ $event['status'] }}</x-ui.status-badge>
                @endif
            </div>

            @if (filled($event['actor'] ?? null) || filled($event['date'] ?? null))
                <p class="ui-muted">
                    @if (filled($event['actor'] ?? null))
                        <span>{{ $event['actor'] }}</span>
                    @endif

                    @if (filled($event['date'] ?? null))
                        <time>{{ $event['date'] }}</time>
                    @endif
                </p>
            @endif

            @if (filled($event['note'] ?? null))
                <p>{{ $event['note'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
