@props(['locale' => 'ar', 'sorts' => ['name' => 'Name']])
@php($t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en)
<x-ui.field id="list-per-page" :label="$t('عرض', 'Show')"><x-ui.select name="per_page">@foreach ([10,25,50] as $size)<option value="{{ $size }}" @selected((int) request('per_page',10) === $size)>{{ $size }}</option>@endforeach</x-ui.select></x-ui.field>
<x-ui.field id="list-sort" :label="$t('ترتيب حسب', 'Sort by')"><x-ui.select name="sort">@foreach ($sorts as $value => $label)<option value="{{ $value }}" @selected(request('sort',array_key_first($sorts)) === $value)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
<x-ui.field id="list-direction" :label="$t('الترتيب', 'Order')"><x-ui.select name="direction"><option value="asc" @selected(request('direction','asc') === 'asc')>{{ $t('تصاعدي', 'Ascending') }}</option><option value="desc" @selected(request('direction') === 'desc')>{{ $t('تنازلي', 'Descending') }}</option></x-ui.select></x-ui.field>
