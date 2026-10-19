<x-ui.detail-panel :title="$t('طلبات المبيعات', 'Sales orders')">
    <div class="ui-stack">
        <form method="get" action="/dev/design-system">
            <input type="hidden" name="screen" value="orders"><input type="hidden" name="locale" value="{{ $locale }}">
            <x-ui.table-toolbar>
                <x-ui.field id="order-search" :label="$t('البحث في الطلبات', 'Search orders')"><x-ui.search-input name="search" :value="$filters['search'] ?? ''" maxlength="100" /></x-ui.field>
                <x-ui.field id="order-status" :label="$t('حالة الطلب', 'Order status')">
                    <x-ui.select name="status">
                        @foreach (['all' => $t('كل الحالات', 'All statuses'), 'success' => $t('مكتمل', 'Complete'), 'info' => $t('قيد التنفيذ', 'In progress'), 'warning' => $t('معلق', 'Pending'), 'danger' => $t('ملغي', 'Cancelled')] as $code => $label)
                            <option value="{{ $code }}" @selected(($filters['status'] ?? 'all') === $code)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field id="order-sort" :label="$t('ترتيب النتائج', 'Sort results')">
                    <x-ui.select name="sort">
                        @foreach (['date_desc' => $t('الأحدث أولاً', 'Newest first'), 'amount_desc' => $t('الأعلى قيمة', 'Highest value'), 'customer_asc' => $t('اسم العميل', 'Customer name')] as $code => $label)
                            <option value="{{ $code }}" @selected(($filters['sort'] ?? 'date_desc') === $code)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="submit">{{ $t('تطبيق', 'Apply') }}</x-ui.button>
            </x-ui.table-toolbar>
        </form>
        <x-ui.table data-testid="orders-table" :aria-label="$t('جدول الطلبات التجريبي', 'Sample orders table')">
            <thead><tr>@foreach ([$t('رقم الطلب', 'Order'), $t('العميل', 'Customer'), $t('التاريخ', 'Date'), $t('القيمة', 'Value'), $t('الحالة', 'Status'), $t('الفرع', 'Branch')] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($orders as $row)
                    <tr data-order-id="{{ $row['id'] }}">
                        <th scope="row"><a href="/dev/design-system?screen=order&amp;locale={{ $locale }}"><bdi dir="ltr">#{{ $row['id'] }}</bdi></a></th>
                        <td>{{ $locale === 'ar' ? $row['customer'] : $row['english'] }}</td>
                        <td><bdi dir="ltr">{{ $row['date'] }}</bdi></td>
                        <td><bdi dir="ltr">{{ number_format($row['amount']) }}</bdi> {{ $t('ر.س', 'SAR') }}</td>
                        <td><x-ui.status-badge :status="$row['status']">{{ ['success' => $t('مكتمل', 'Complete'), 'info' => $t('قيد التنفيذ', 'In progress'), 'warning' => $t('معلق', 'Pending'), 'danger' => $t('ملغي', 'Cancelled')][$row['status']] }}</x-ui.status-badge></td>
                        <td>{{ $locale === 'ar' ? $row['branch'] : 'Sample branch' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ $t('لا توجد نتائج. جرّب بحثاً آخر.', 'No results. Try another search.') }}</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
        <div class="ui-inline"><x-ui.pagination :links="$pagination" :aria-label="$t('صفحات الطلبات', 'Order pages')" /><span class="ui-muted" data-testid="order-count">{{ $orderCount }} {{ $t('طلب تجريبي', 'sample orders') }}</span></div>
    </div>
</x-ui.detail-panel>
