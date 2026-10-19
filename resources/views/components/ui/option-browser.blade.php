@props(['sources' => [], 'locale' => 'ar'])
@php($t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en)
<details {{ $attributes->merge(['class' => 'admin-options']) }}>
    <summary>{{ $t('البحث في خيارات الفروع والأقسام والأدوار', 'Find branch, department and role options') }}</summary>
    <p class="ui-muted">{{ $t('ابحث أو انتقل بين صفحات الخيارات قبل ملء النموذج.', 'Find an option before filling in the form.') }}</p>
    @foreach ($sources as $source)
        @if (($source['paginator'] ?? null) instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            <form method="get" action="{{ request()->url() }}" class="admin-toolbar">
                @foreach (request()->except($source['query'], $source['paginator']->getPageName()) as $key => $value)
                    @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <x-ui.field :id="'option-'.$source['query']" :label="$source['label']"><x-ui.search-input :name="$source['query']" :value="request($source['query'])" /></x-ui.field>
                <x-ui.button type="submit" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
            </form>
            <x-ui.list-pagination :paginator="$source['paginator']" :locale="$locale" />
        @endif
    @endforeach
</details>
