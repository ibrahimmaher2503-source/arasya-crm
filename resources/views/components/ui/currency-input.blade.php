@props(['id' => null, 'name' => null, 'value' => null, 'type' => 'number', 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

<x-ui.input :id="$id" :name="$name" :value="$value" :type="$type" inputmode="decimal" :error="$error" :hint="$hint" {{ $attributes->merge(['class' => 'ui-currency-input', 'step' => 'any']) }} />
