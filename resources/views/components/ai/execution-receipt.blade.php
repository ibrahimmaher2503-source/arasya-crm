@props(['state', 'label', 'details' => null])

<section {{ $attributes->merge(['class' => 'ui-detail-panel', 'aria-label' => __('Sample receipt')]) }}>
    <h2 class="ui-detail-panel__title">{{ __('Sample receipt') }}</h2>

    <dl class="ui-description-list">
        <div class="ui-description-list__item">
            <dt>{{ __('State') }}</dt>
            <dd>{{ $state }}</dd>
        </div>
        <div class="ui-description-list__item">
            <dt>{{ __('Label') }}</dt>
            <dd>{{ $label }}</dd>
        </div>
    </dl>

    @if ($details !== null)
        <p>{{ $details }}</p>
    @endif
</section>
