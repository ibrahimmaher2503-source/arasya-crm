@props(['title' => null, 'description' => null])

<fieldset {{ $attributes->merge(['class' => 'ui-form-section']) }}>
    @if ($title)
        <legend class="ui-form-section__title">{{ $title }}</legend>
    @endif

    @if ($description)
        <p class="ui-form-section__description">{{ $description }}</p>
    @endif

    <div class="ui-form-section__content">{{ $slot }}</div>
</fieldset>
