@props(['id' => null, 'name' => null, 'value' => null, 'checked' => false, 'label' => null, 'error' => null, 'hint' => null])
@aware(['id' => null, 'error' => null, 'hint' => null])

@php
    if (! blank($label) && blank($id)) {
        $id = 'switch-' . \Illuminate\Support\Str::ulid();
    }
@endphp

<span class="ui-switch-control">
    <x-ui.input type="checkbox" role="switch" :id="$id" :name="$name" :value="$value" :checked="$checked" :error="$error" :hint="$hint" {{ $attributes->except('role')->merge(['class' => 'ui-switch']) }} />
    @if ($label)
        <label class="ui-switch__label" @if ($id) for="{{ $id }}" @endif>{{ $label }}</label>
    @endif
</span>
