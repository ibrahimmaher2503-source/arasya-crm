@props(['lines' => [], 'totalLabel', 'totalValue'])

<dl {{ $attributes->merge(['class' => 'ui-description-list']) }}>
    @foreach ($lines as $line)
        <div class="ui-description-list__item">
            <dt>{{ $line['label'] ?? '' }}</dt>
            <dd>{{ $line['value'] ?? '' }}</dd>
        </div>
    @endforeach

    <div class="ui-description-list__item">
        <dt>{{ $totalLabel }}</dt>
        <dd><strong>{{ $totalValue }}</strong></dd>
    </div>
</dl>
