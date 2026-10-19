@props(['id' => null, 'name' => null, 'value' => null, 'type' => 'date', 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

<x-ui.input :id="$id" :name="$name" :value="$value" :type="$type" :error="$error" :hint="$hint" {{ $attributes->merge(['class' => 'ui-date-input']) }} />
