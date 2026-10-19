@props(['id', 'title', 'closeLabel' => 'Close', 'docked' => false])

<dialog @if ($docked) data-docked-drawer @endif {{ $attributes->merge(['id' => $id, 'aria-labelledby' => $id.'-title', 'class' => 'ui-dialog ui-drawer']) }}>
    <div class="ui-dialog__panel ui-drawer__panel">
        <header class="ui-dialog__header">
            <h2 class="ui-dialog__title" id="{{ $id }}-title">{{ $title }}</h2>
            <x-ui.icon-button :label="$closeLabel" data-close-dialog>
                <span aria-hidden="true">×</span>
            </x-ui.icon-button>
        </header>
        <div class="ui-dialog__body">
            {{ $slot }}
        </div>
    </div>
</dialog>
