@props(['title'])

<section {{ $attributes->merge(['class' => 'ui-detail-panel', 'aria-label' => (string) $title]) }}>
    <h2 class="ui-detail-panel__title">{{ (string) $title }}</h2>
    <div class="ui-detail-panel__content">
        {{ $slot }}
    </div>
</section>
