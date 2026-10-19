@props(['steps' => []])

<ol {{ $attributes->merge(['class' => 'ui-grid', 'aria-label' => __('Workflow steps')]) }}>
    @foreach ($steps as $step)
        @php
            $state = $step['state'] ?? 'upcoming';
            $stateLabel = match ($state) {
                'complete' => __('Complete'),
                'current' => __('Current step'),
                default => __('Upcoming'),
            };
        @endphp

        <li>
            <p @if ($state === 'current') aria-current="step" @endif>
                <span>{{ $step['label'] ?? '' }}</span>
                <span class="ui-muted">{{ $stateLabel }}</span>
            </p>
        </li>
    @endforeach
</ol>
