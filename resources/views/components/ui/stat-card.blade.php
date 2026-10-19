@props(['label', 'value', 'change' => null])

<article {{ $attributes->merge(['class' => 'ui-stat-card']) }}>
    <dl class="ui-stat-card__data">
        <dt class="ui-stat-card__label">{{ (string) $label }}</dt>
        <dd class="ui-stat-card__value">{{ (string) $value }}</dd>
    </dl>

    @if ($change !== null)
        <p class="ui-stat-card__change">{{ (string) $change }}</p>
    @endif
</article>
