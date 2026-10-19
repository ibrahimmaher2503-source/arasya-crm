@props(['id' => null, 'name' => null, 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

<x-ui.select :id="$id" :name="$name" :error="$error" :hint="$hint" multiple {{ $attributes->except('multiple')->merge(['class' => 'ui-multi-select']) }}>{{ $slot }}</x-ui.select>
