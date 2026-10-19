@props(['value', 'datetime' => null])

<time {{ $attributes->merge(['class' => 'ui-inline', 'datetime' => $datetime]) }}>{{ $value }}</time>
