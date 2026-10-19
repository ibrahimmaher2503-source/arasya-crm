@props(['items' => []])

<dl {{ $attributes->merge(['class' => 'ui-description-list']) }}>
    @foreach ($items as $label => $value)
        <div class="ui-description-list__item">
            <dt>{{ (string) $label }}</dt>
            <dd>{{ (string) $value }}</dd>
        </div>
    @endforeach
</dl>
