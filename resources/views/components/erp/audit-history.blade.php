@props(['entries' => []])

<ol {{ $attributes->merge(['class' => 'ui-stack', 'aria-label' => __('Audit history')]) }}>
    @foreach ($entries as $entry)
        <li>
            <h3>{{ $entry['action'] ?? '' }}</h3>

            @if (filled($entry['actor'] ?? null) || filled($entry['date'] ?? null))
                <p class="ui-muted">
                    @if (filled($entry['actor'] ?? null))
                        <span>{{ $entry['actor'] }}</span>
                    @endif

                    @if (filled($entry['date'] ?? null))
                        <time>{{ $entry['date'] }}</time>
                    @endif
                </p>
            @endif

            @if (filled($entry['description'] ?? null))
                <p>{{ $entry['description'] }}</p>
            @endif
        </li>
    @endforeach
</ol>
