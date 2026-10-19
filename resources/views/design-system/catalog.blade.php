<div class="ui-stack">
    @php
        $samplePage = max(1, min(1000, request()->integer('sample_page', 1)));
        $sampleRows = [];
        for ($sampleIndex = ($samplePage - 1) * 10 + 1; $sampleIndex <= $samplePage * 10; $sampleIndex++) {
            $sampleRows[] = ['id' => $sampleIndex, 'name' => $t('مستخدم تجريبي', 'Sample user').' '.$sampleIndex];
        }
        $sampleDirectory = new \Illuminate\Pagination\LengthAwarePaginator($sampleRows, 10000, 10, $samplePage, ['path' => request()->url(), 'pageName' => 'sample_page', 'query' => request()->except('sample_page')]);
    @endphp
    <section class="admin-section ui-stack" id="admin-components" aria-label="{{ $t('مكونات الإدارة المشتركة', 'Shared administration components') }}">
        <x-ui.page-header :title="$t('نمط إدارة السجلات', 'Record management pattern')" :subtitle="$t('أمثلة اصطناعية للمكونات نفسها المستخدمة في الإدارة.', 'Synthetic examples of the actual administration components.')" icon="users" heading="h2" />
        <x-ui.summary-strip :items="[['label'=>$t('سجلات اصطناعية','Synthetic records'),'value'=>10000,'icon'=>'users'],['label'=>$t('صفوف الصفحة','Rows per page'),'value'=>10,'icon'=>'grid']]" />
        <div class="admin-table"><x-ui.table :caption="$t('مثال قائمة كبيرة محدودة العرض', 'Bounded large directory example')"><thead><tr><th scope="col">{{ $t('المعرف','ID') }}</th><th scope="col">{{ $t('الاسم','Name') }}</th><th scope="col">{{ $t('الحالة','Status') }}</th></tr></thead><tbody>@foreach($sampleDirectory as $sampleRow)<tr><td><bdi>{{ $sampleRow['id'] }}</bdi></td><td><div class="admin-entity"><x-ui.avatar :name="$sampleRow['name']" />{{ $sampleRow['name'] }}</div></td><td><x-ui.status-badge status="success">{{ $t('نشط','Active') }}</x-ui.status-badge></td></tr>@endforeach</tbody></x-ui.table><x-ui.list-pagination :paginator="$sampleDirectory" :locale="$locale" /></div>
        <x-erp.permission-groups :catalog="['organization.read','organization.write','users.read','users.write','audit.read']" :labels="['organization.read'=>$t('عرض المؤسسة','View organization'),'organization.write'=>$t('إدارة المؤسسة','Manage organization'),'users.read'=>$t('عرض المستخدمين','View users'),'users.write'=>$t('إدارة المستخدمين','Manage users'),'audit.read'=>$t('عرض سجل التدقيق','View audit log')]" id="catalog-permissions" :locale="$locale" />
        <x-ui.summary-strip variant="cards" :items="[['label'=>$t('بطاقات ملخص منفصلة','Separate summary cards'),'value'=>10,'icon'=>'users'],['label'=>$t('المكونات المشتركة','Shared components'),'value'=>3,'icon'=>'shield']]" />
        <pre class="ui-code"><code>&lt;x-ui.summary-strip :items="$scopedMetrics" variant="cards" /&gt;
&lt;x-ui.list-pagination :paginator="$records" :locale="$locale" /&gt;
&lt;x-erp.permission-groups :catalog="$authorizedCatalog" :selected="$permissions" /&gt;</code></pre>
    </section>
    <div class="ui-inline">
        <x-ui.status-badge status="success">Design system · v1</x-ui.status-badge>
        <p class="ui-muted">{{ $t('أساس موحد لشاشات أراسيا — Blade ومكونات مشتركة، دون إطار واجهات إضافي.', 'One shared foundation for Arasya screens — Blade components without another UI framework.') }}</p>
    </div>
    <x-ui.detail-panel id="foundation" :title="$t('الألوان والخطوط', 'Colors and typography')">
        <div class="ui-stack">
            <div class="ui-inline">
                @foreach (['primary', 'primary-hover', 'primary-soft', 'surface', 'background', 'success', 'warning', 'danger', 'info'] as $token)
                    <div><span class="ui-swatch" data-token="{{ $token }}" aria-hidden="true"></span><p><code>{{ $token }}</code></p></div>
                @endforeach
            </div>
            <h2>{{ $t('أنجز عملك بوضوح كامل', 'Work with complete clarity') }}</h2>
            <p>{{ $t('واجهة عربية مريحة للقراءة، وأرقام معزولة الاتجاه داخل سجلات العمل.', 'Readable typography with isolated numbers in business records.') }} <bdi dir="ltr">Aa Bb · 0123456789</bdi></p>
            <pre class="ui-code"><code>{{ '<x-ui.button variant="primary">Save changes</x-ui.button>' }}</code></pre>
        </div>
    </x-ui.detail-panel>
    <x-ui.detail-panel id="actions" :title="$t('الأزرار والإجراءات', 'Buttons and actions')">
        <div class="ui-inline">
            <x-ui.button data-open-dialog="demo-modal">{{ $t('زر رئيسي', 'Primary button') }}</x-ui.button>
            <x-ui.button variant="secondary" data-open-dialog="demo-drawer">{{ $t('فتح اللوحة', 'Open drawer') }}</x-ui.button>
            <x-ui.button variant="ghost" data-open-dialog="demo-modal">{{ $t('زر خفيف', 'Ghost button') }}</x-ui.button>
            <x-ui.button variant="danger" data-open-dialog="demo-confirm">{{ $t('تأكيد إجراء خطر', 'Destructive confirmation') }}</x-ui.button>
            <x-ui.button disabled>{{ $t('غير متاح', 'Unavailable') }}</x-ui.button>
            <x-ui.icon-button :label="$t('إضافة', 'Add')" data-open-dialog="demo-modal"><span aria-hidden="true">+</span></x-ui.icon-button>
            <x-ui.dropdown-menu :label="$t('إجراءات أخرى', 'More actions')"><a href="/dev/design-system?screen=order&amp;locale={{ $locale }}">{{ $t('عرض مثال الطلب', 'View sample order') }}</a></x-ui.dropdown-menu>
            <x-ui.tooltip id="sample-tooltip" :text="$t('لا تُرسل بيانات من هذه المعاينة.', 'No data is sent from this preview.')">{{ $t('عن المعاينة', 'About the preview') }}</x-ui.tooltip>
        </div>
    </x-ui.detail-panel>
    <section id="forms" class="ui-section" aria-labelledby="forms-heading"><h2 id="forms-heading">{{ $t('الحقول والتحقق', 'Inputs and validation') }}</h2>@include('design-system.form')</section>
    <section id="data" class="ui-section" aria-labelledby="data-heading"><h2 id="data-heading">{{ $t('الجداول والبحث والترقيم', 'Tables, search and pagination') }}</h2>@include('design-system.orders')</section>
    <x-ui.detail-panel id="feedback" :title="$t('الحالات والتنبيهات', 'States and feedback')">
        <div class="ui-grid">
            <div class="ui-stack">
                @foreach (['success' => $t('مكتمل', 'Complete'), 'info' => $t('قيد التنفيذ', 'In progress'), 'warning' => $t('معلق', 'Pending'), 'danger' => $t('ملغي', 'Cancelled'), 'neutral' => $t('مسودة', 'Draft')] as $status => $label)
                    <x-ui.status-badge :status="$status">{{ $label }}</x-ui.status-badge>
                @endforeach
            </div>
            <div class="ui-stack">
                <x-ui.alert variant="success">{{ $t('مثال لرسالة نجاح.', 'Sample success message.') }}</x-ui.alert>
                <x-ui.alert variant="warning">{{ $t('راجع البيانات قبل المتابعة.', 'Review the data before continuing.') }}</x-ui.alert>
                <x-ui.alert variant="danger">{{ $t('تعذر إكمال المثال. راجع الحقول.', 'The sample could not be completed. Check the fields.') }}</x-ui.alert>
                <x-ui.alert variant="info">{{ $t('هذه رسالة معلومات.', 'This is an informational message.') }}</x-ui.alert>
                <x-ui.toast :dismiss-label="$t('إخفاء التنبيه', 'Dismiss notification')">{{ $t('إشعار عرض قابل للإغلاق.', 'A dismissible preview notification.') }}</x-ui.toast>
            </div>
        </div>
    </x-ui.detail-panel>
    <section id="metrics" class="ui-section" aria-labelledby="metrics-heading"><h2 id="metrics-heading">{{ $t('المؤشرات وبطاقات البيانات', 'Metrics and data cards') }}</h2>@include('design-system.dashboard')</section>
    <section id="record" class="ui-section" aria-labelledby="record-heading"><h2 id="record-heading">{{ $t('تفاصيل السجل والعناصر', 'Record details and items') }}</h2>@include('design-system.order')</section>
    <x-ui.detail-panel id="empty-loading" :title="$t('التحميل والفراغ والخطأ', 'Loading, empty and error states')">
        <div class="ui-grid">
            <div class="ui-stack"><x-ui.skeleton variant="block" /><x-ui.skeleton /><x-ui.loading-state :label="$t('جار التحميل', 'Loading')" /></div>
            <x-ui.empty-state :title="$t('لا توجد مرفقات', 'No attachments')" :description="$t('ستظهر المرفقات المسموح بها هنا.', 'Permitted attachments will appear here.')" />
            <x-ui.error-state :title="$t('تعذر تحميل المثال', 'Unable to load sample')" :message="$t('حالة خطأ للعرض فقط؛ لا يوجد طلب شبكي متعثر.', 'Presentation error state only. There is no failed network request.')" />
        </div>
    </x-ui.detail-panel>
    <section id="workflow" class="ui-section" aria-labelledby="workflow-heading"><h2 id="workflow-heading">{{ $t('الموافقات وسير العمل', 'Approvals and workflow') }}</h2>@include('design-system.approvals')</section>
    <section id="assistant" class="ui-section" aria-labelledby="assistant-heading">
        <h2 id="assistant-heading">{{ $t('واجهة المساعد · غير متصل', 'Assistant interface · disconnected') }}</h2>
        <x-ai.assistant-panel :title="$t('المساعد الذكي · مثال واجهة', 'AI assistant · interface sample')">
            <div class="ui-stack">
                <x-ai.message role="user" :name="$t('مستخدم تجريبي', 'Sample user')" time="09:30">{{ $t('اعرض ملاحظات الطلب التجريبي.', 'Show findings for the sample order.') }}</x-ai.message>
                <x-ai.message role="assistant" :name="$t('عرض المساعد', 'Assistant preview')" time="09:31">{{ $t('هذه رسالة تركيبية، وليست استجابة من مزود AI.', 'This is a synthetic message, not an AI provider response.') }}</x-ai.message>
                <x-ai.finding-card severity="warning" :title="$t('ملاحظة توضيحية', 'Illustrative finding')" :summary="$t('مثال لشكل ملاحظة مرتبطة بدليل؛ لم يتم تحليل بيانات حقيقية.', 'An evidence-linked finding example; no live data was analyzed.')"><bdi>DEMO-SO-015</bdi></x-ai.finding-card>
                <x-ai.action-preview :title="$t('معاينة اقتراح', 'Action preview')">{{ $t('عرض التغيير المقترح قبل التأكيد، دون تنفيذ.', 'Show a proposed change before confirmation, without execution.') }}</x-ai.action-preview>
                <x-ai.confirmation :title="$t('التأكيد معطل', 'Confirmation disabled')" :confirm-label="$t('غير متاح — بوابة AI غير موصلة', 'Unavailable — AI gateway disconnected')" />
                <x-ai.execution-receipt state="not-executed" :label="$t('لم يُنفذ أي إجراء', 'No action executed')" :details="$t('إيصال توضيحي فقط.', 'Illustrative receipt only.')" />
                <x-ai.error-state :title="$t('المساعد غير متصل', 'Assistant disconnected')" :message="$t('لا يوجد مزود خارجي أو أدوات أعمال مفعلة في هذه المكتبة.', 'No external provider or business action tools are enabled in this library.')" />
            </div>
        </x-ai.assistant-panel>
    </section>
    <x-ui.alert variant="info">{{ $t('استخدم أزرار المظهر واللغة أعلى الصفحة لمعاينة الثيم الداكن واتجاه القراءة. جميع الأمثلة تتكيف مع الهاتف.', 'Use the theme and language controls above to preview dark mode and reading direction. All examples adapt to mobile.') }}</x-ui.alert>
</div>
