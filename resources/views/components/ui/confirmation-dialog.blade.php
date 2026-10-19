@props([
    'id',
    'title',
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'closeLabel' => 'Close',
    'form' => null,
])

<x-ui.modal :id="$id" :title="$title" :close-label="$closeLabel" {{ $attributes }}>
    <div class="ui-confirmation-dialog__message">
        {{ $slot }}
    </div>

    <div class="ui-confirmation-dialog__actions">
        <x-ui.button variant="secondary" data-close-dialog>{{ $cancelLabel }}</x-ui.button>

        @isset($confirm)
            {{ $confirm }}
        @elseif(filled($form))
            <x-ui.button variant="danger" type="submit" :form="$form">{{ $confirmLabel }}</x-ui.button>
        @else
            <x-ui.button variant="danger" type="button" data-confirm-action>{{ $confirmLabel }}</x-ui.button>
        @endif
    </div>
</x-ui.modal>
