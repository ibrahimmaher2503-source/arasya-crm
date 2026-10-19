<div class="ui-stack">
    <x-erp.entity-header :title="$t('طلب مبيعات', 'Sales order')" identifier="SO-2026-015" :subtitle="$t('شركة النصر للتجارة · مثال تطوير', 'Al Nasr Trading · development example')">
        <x-slot:status>
            <x-erp.entity-status status="success">{{ $t('مكتمل · تجريبي', 'Complete · sample') }}</x-erp.entity-status>
        </x-slot:status>
        <x-slot:actions>
            <x-ui.button variant="secondary" data-open-dialog="demo-drawer">{{ $t('التفاصيل', 'Details') }}</x-ui.button>
        </x-slot:actions>
    </x-erp.entity-header>
    <div class="ui-grid">
        <x-ui.detail-panel :title="$t('معلومات الطلب', 'Order information')">
            <x-ui.description-list :items="[$t('رقم الطلب', 'Order number') => 'SO-2026-015', $t('الفرع', 'Branch') => $t('الفرع الرئيسي', 'Main branch'), $t('المندوب', 'Representative') => $t('أحمد محمد', 'Ahmed Mohamed')]" />
            <x-erp.date-time-display value="2026-10-17 · 09:30" datetime="2026-10-17T09:30:00" />
        </x-ui.detail-panel>
        <x-ui.detail-panel :title="$t('معلومات العميل', 'Customer information')">
            <x-ui.description-list :items="[$t('العميل', 'Customer') => $t('شركة النصر للتجارة', 'Al Nasr Trading'), $t('رقم العميل', 'Customer ID') => 'MC-00125', $t('البريد', 'Email') => 'customer@example.test']" />
        </x-ui.detail-panel>
        <x-ui.detail-panel :title="$t('الملخص المالي التجريبي', 'Sample financial summary')">
            <x-erp.totals-summary :lines="[['label' => $t('قيمة العناصر', 'Items'), 'value' => '125,000 SAR'], ['label' => $t('خصم توضيحي', 'Illustrative discount'), 'value' => '0 SAR']]" :total-label="$t('الإجمالي المعروض', 'Displayed total')" total-value="125,000 SAR" />
        </x-ui.detail-panel>
    </div>
    <x-ui.tabs id="order-tabs" :aria-label="$t('أقسام الطلب', 'Order sections')" :items="[['id' => 'products', 'label' => $t('المنتجات', 'Products'), 'content' => $t('عناصر الطلب التجريبية موضحة أدناه.', 'Sample order items are shown below.')], ['id' => 'activity', 'label' => $t('سجل العمليات', 'Activity'), 'content' => $t('لا توجد عمليات حقيقية في هذه المعاينة.', 'There are no live operations in this preview.')], ['id' => 'attachments', 'label' => $t('المرفقات', 'Attachments'), 'content' => $t('اختيار الملف لا يرفعه إلى الخادم.', 'Selecting a file does not upload it.')]]" />
    <x-erp.line-items-table :caption="$t('عناصر تجريبية؛ ليست فاتورة', 'Sample items; not an invoice')">
        <thead><tr><th scope="col">{{ $t('المنتج', 'Product') }}</th><th scope="col">{{ $t('الكمية', 'Quantity') }}</th><th scope="col">{{ $t('القيمة المعروضة', 'Displayed value') }}</th></tr></thead>
        <tbody>
            @foreach ([[$t('قاطع كهربائي', 'Circuit breaker'), '10', '45,000'], [$t('كابل نحاس', 'Copper cable'), '20', '25,000'], [$t('لوحة توزيع', 'Distribution panel'), '5', '55,000']] as $item)
                <tr><th scope="row">{{ $item[0] }}</th><td>{{ $item[1] }}</td><td><x-erp.money-display :value="$item[2]" :currency="$t('ر.س', 'SAR')" /></td></tr>
            @endforeach
        </tbody>
    </x-erp.line-items-table>
    <x-erp.attachment-uploader id="sample-attachment" name="attachment" :label="$t('اختيار مرفق · لا يتم رفعه', 'Choose attachment · not uploaded')" accept=".pdf,.png,.jpg" />
</div>
