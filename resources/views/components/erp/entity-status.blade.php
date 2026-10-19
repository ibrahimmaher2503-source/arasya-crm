@props(['status' => 'neutral'])

<x-ui.status-badge :status="$status" {{ $attributes }}>{{ $slot }}</x-ui.status-badge>
