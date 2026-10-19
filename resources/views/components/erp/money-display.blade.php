@props(['value', 'currency'])

<span {{ $attributes->merge(['class' => 'ui-inline']) }}>
    <bdi dir="ltr">{{ $value }}</bdi>
    <span class="ui-muted">{{ $currency }}</span>
</span>
