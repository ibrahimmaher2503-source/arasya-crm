@props(['id', 'items' => []])

<div {{ $attributes->except('aria-label')->merge(['id' => $id, 'class' => 'ui-tabs', 'data-tabs' => '']) }}>
    <div class="ui-tabs__list" role="tablist" aria-label="{{ (string) $attributes->get('aria-label', 'Sections') }}">
        @foreach ($items as $index => $item)
            <button
                class="ui-tabs__tab"
                id="{{ $id }}-tab-{{ $item['id'] }}"
                type="button"
                role="tab"
                aria-controls="{{ $id }}-panel-{{ $item['id'] }}"
                aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                tabindex="{{ $index === 0 ? '0' : '-1' }}"
            >{{ (string) $item['label'] }}</button>
        @endforeach
    </div>

    @foreach ($items as $index => $item)
        <div
            class="ui-tabs__panel"
            id="{{ $id }}-panel-{{ $item['id'] }}"
            role="tabpanel"
            aria-labelledby="{{ $id }}-tab-{{ $item['id'] }}"
            tabindex="0"
            @if ($index !== 0) hidden @endif
        >{{ (string) $item['content'] }}</div>
    @endforeach
</div>
