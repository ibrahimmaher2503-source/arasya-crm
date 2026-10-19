@props(['id', 'text'])

<span class="ui-tooltip" data-ui-tooltip>
    <span class="ui-tooltip__trigger" tabindex="0" aria-describedby="{{ $id }}">
        {{ $slot }}
    </span>
    <span class="ui-tooltip__content" id="{{ $id }}" role="tooltip">{{ $text }}</span>
</span>
