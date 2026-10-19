@props(['caption' => null])

<div {{ $attributes->merge([
    'class' => 'ui-table-region',
    'role' => 'region',
    'aria-label' => $caption ?? 'Table',
    'tabindex' => '0',
]) }}>
    <table class="ui-table">
        @if ($caption !== null)
            <caption>{{ (string) $caption }}</caption>
        @endif

        {{ $slot }}
    </table>
</div>
