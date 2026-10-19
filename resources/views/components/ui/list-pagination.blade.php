@props(['paginator', 'locale' => 'ar'])
@if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    @php
        $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
        $links = [['label' => $t('السابق', 'Previous'), 'url' => $paginator->previousPageUrl()]];
        $start = max(1, $paginator->currentPage() - 2);
        $end = min($paginator->lastPage(), $paginator->currentPage() + 2);
        if ($start > 1) { $links[] = ['label' => '1', 'url' => $paginator->url(1)]; }
        if ($start > 2) { $links[] = ['label' => '…']; }
        for ($page = $start; $page <= $end; $page++) { $links[] = ['label' => (string) $page, 'url' => $paginator->url($page), 'current' => $page === $paginator->currentPage()]; }
        if ($end < $paginator->lastPage() - 1) { $links[] = ['label' => '…']; }
        if ($end < $paginator->lastPage()) { $links[] = ['label' => (string) $paginator->lastPage(), 'url' => $paginator->url($paginator->lastPage())]; }
        $links[] = ['label' => $t('التالي', 'Next'), 'url' => $paginator->nextPageUrl()];
    @endphp
    <div {{ $attributes->merge(['class' => 'admin-pagination']) }}>
        <p class="ui-muted"><bdi>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</bdi> {{ $t('من', 'of') }} <bdi>{{ $paginator->total() }}</bdi> {{ $t('نتيجة', 'results') }}</p>
        <x-ui.pagination :links="$links" :aria-label="$t('صفحات النتائج', 'Result pages')" />
    </div>
@endif
