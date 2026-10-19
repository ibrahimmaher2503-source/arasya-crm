@props(['items' => [], 'variant' => 'strip'])
<section {{ $attributes->class(['admin-summary', 'admin-summary--cards' => $variant === 'cards']) }}>
    @foreach ($items as $item)
        <div class="admin-summary__item"><span class="admin-icon-tile admin-icon-tile--{{ $item['tone'] ?? 'primary' }}"><x-ui.icon :name="$item['icon'] ?? 'building'" /></span><x-ui.stat-card :label="$item['label']" :value="$item['value']" :change="$item['description'] ?? null" /></div>
    @endforeach
</section>
