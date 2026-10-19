@props(['id' => null, 'name' => null, 'value' => null, 'checked' => false, 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

<x-ui.input type="radio" :id="$id" :name="$name" :value="$value" :checked="$checked" :error="$error" :hint="$hint" {{ $attributes->merge(['class' => 'ui-radio']) }} />
