<div {{ $attributes->class(['ui-directory-toolbar']) }}>
    <div class="ui-directory-toolbar__summary">{{ $slot }}</div>
    <div class="ui-directory-toolbar__actions">{{ $actions ?? '' }}</div>
</div>
