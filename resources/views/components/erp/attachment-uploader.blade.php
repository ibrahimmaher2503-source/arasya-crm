@props(['id', 'name', 'label', 'accept' => null, 'multiple' => false])

<x-ui.field :id="$id" :label="$label">
    <x-ui.input :id="$id" :name="$name" type="file" :accept="$accept" :multiple="$multiple" {{ $attributes }} />
</x-ui.field>
