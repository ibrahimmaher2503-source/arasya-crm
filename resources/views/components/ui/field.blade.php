@props(['id' => null, 'label' => null, 'error' => null, 'hint' => null])

@if (! blank($label) && blank($id))
    @php throw new \InvalidArgumentException('A field label requires a non-empty id.'); @endphp
@endif

<div {{ $attributes->merge(['class' => 'ui-field']) }}>
    @if ($label)
        <label class="ui-field__label" @if ($id) for="{{ $id }}" @endif>{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint)
        <x-ui.validation-message :id="$id ? $id . '-hint' : null" type="hint">{{ $hint }}</x-ui.validation-message>
    @endif

    @if ($error)
        <x-ui.validation-message :id="$id ? $id . '-error' : null">{{ $error }}</x-ui.validation-message>
    @endif
</div>
