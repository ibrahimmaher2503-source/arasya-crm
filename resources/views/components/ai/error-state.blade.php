@props(['title' => 'Assistant unavailable', 'message' => null])

<x-ui.error-state :title="$title" :message="$message" {{ $attributes }} />
