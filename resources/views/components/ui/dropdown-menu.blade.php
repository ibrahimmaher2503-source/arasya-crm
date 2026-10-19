@props(['label' => 'Actions'])

<details {{ $attributes->merge(['class' => 'ui-dropdown']) }}>
    <summary class="ui-dropdown__trigger">
        @isset($trigger)
            {{ $trigger }}
        @else
            {{ $label }}
        @endisset
    </summary>
    <div class="ui-dropdown__content">
        {{ $slot }}
    </div>
</details>
