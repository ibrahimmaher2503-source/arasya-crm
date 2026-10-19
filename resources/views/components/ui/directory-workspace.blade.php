<div {{ $attributes->class(['ui-directory-workspace']) }} data-directory-workspace>
    <div class="ui-directory-workspace__content">{{ $slot }}</div>
    {{ $editor ?? '' }}
</div>
