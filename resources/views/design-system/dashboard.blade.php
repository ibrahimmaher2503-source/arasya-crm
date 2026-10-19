<div class="ui-stack">
    <div class="ui-grid">
        <x-ui.stat-card :label="$t('إجمالي المبيعات التجريبي', 'Sample total sales')" value="1,248,500" :change="$t('مثال عرض · ر.س', 'Presentation sample · SAR')" />
        <x-ui.stat-card :label="$t('الطلبات المؤكدة', 'Confirmed orders')" value="124" :change="$t('بيانات تركيبية', 'Synthetic data')" />
        <x-ui.stat-card :label="$t('طلبات قيد التنفيذ', 'Orders in progress')" value="56" :change="$t('بيانات تركيبية', 'Synthetic data')" />
        <x-ui.stat-card :label="$t('بانتظار المراجعة', 'Awaiting review')" value="18" :change="$t('بيانات تركيبية', 'Synthetic data')" />
    </div>
    <div class="ui-grid ui-grid--wide">
        <x-ui.detail-panel :title="$t('أحدث الطلبات', 'Recent orders')">
            <div class="ui-stack">
                @foreach (array_slice($orders, 0, 3) as $row)
                    <div class="ui-inline">
                        <x-ui.avatar :name="$locale === 'ar' ? $row['customer'] : $row['english']" />
                        <div><bdi>{{ $row['id'] }}</bdi><p class="ui-muted">{{ $locale === 'ar' ? $row['customer'] : $row['english'] }}</p></div>
                        <x-ui.status-badge :status="$row['status']">{{ $t('مثال عرض', 'Sample') }}</x-ui.status-badge>
                    </div>
                @endforeach
                <a href="/dev/design-system?screen=orders&amp;locale={{ $locale }}">{{ $t('عرض الطلبات', 'View orders') }}</a>
            </div>
        </x-ui.detail-panel>
        <x-ui.detail-panel :title="$t('مساحة العمل', 'Workspace')">
            <div class="ui-stack">
                <x-ui.alert variant="success">{{ $t('المكونات المشتركة جاهزة للمعاينة.', 'Shared components are ready to preview.') }}</x-ui.alert>
                <x-ui.description-list :items="[$t('الفرع', 'Branch') => $t('الفرع الرئيسي · تجريبي', 'Main branch · sample'), $t('الواجهة', 'Interface') => $t('العربية · من اليمين لليسار', 'English · left to right')]" />
            </div>
        </x-ui.detail-panel>
    </div>
</div>
