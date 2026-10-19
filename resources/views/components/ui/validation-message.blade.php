@props(['id' => null, 'type' => 'error'])

<p {{ $attributes->merge([
    'class' => 'ui-validation-message ui-validation-message--' . $type,
    'id' => $id,
    'role' => $type === 'error' ? 'alert' : null,
]) }}>{{ $slot }}</p>
