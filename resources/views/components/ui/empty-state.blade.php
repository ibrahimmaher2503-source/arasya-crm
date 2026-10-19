@props(['title', 'description' => null])

<section {{ $attributes->merge(['class' => 'ui-empty-state']) }}>
    <h2 class="ui-empty-state__title">{{ (string) $title }}</h2>

    @if ($description !== null)
        <p class="ui-empty-state__description">{{ (string) $description }}</p>
    @endif

    @if (trim((string) $slot) !== '')
        <div class="ui-empty-state__actions">{{ $slot }}</div>
    @endif
</section>
