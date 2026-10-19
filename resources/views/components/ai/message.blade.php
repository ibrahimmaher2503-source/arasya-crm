@props(['role' => 'assistant', 'name' => null, 'time' => null])

@php
    $role = in_array($role, ['user', 'assistant', 'system'], true) ? $role : 'assistant';
    $speaker = $name ?: __(ucfirst($role));
@endphp

<article {{ $attributes->merge(['class' => 'ui-stack', 'aria-label' => $speaker]) }}>
    <header class="ui-inline">
        <strong>{{ $speaker }}</strong>

        @if ($time !== null)
            <span class="ui-muted">{{ $time }}</span>
        @endif
    </header>

    <div>{{ $slot }}</div>
</article>
