@props(['severity' => 'info', 'title', 'summary'])

@php
    $severity = in_array($severity, ['info', 'warning', 'danger'], true) ? $severity : 'info';
@endphp

<x-ui.detail-panel :title="$title" {{ $attributes }}>
    <div class="ui-stack">
        <x-ui.status-badge :status="$severity">{{ __(ucfirst($severity)) }}</x-ui.status-badge>
        <p>{{ $summary }}</p>
        {{ $slot }}
    </div>
</x-ui.detail-panel>
