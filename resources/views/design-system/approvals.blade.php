<div class="ui-stack">
    <x-erp.workflow-stepper :steps="[['label' => $t('مسودة', 'Draft'), 'state' => 'complete'], ['label' => $t('مراجعة', 'Review'), 'state' => 'current'], ['label' => $t('اعتماد', 'Approval'), 'state' => 'upcoming'], ['label' => $t('تنفيذ', 'Execution'), 'state' => 'upcoming']]" />
    <div class="ui-grid">
        <x-ui.detail-panel :title="$t('مسار الموافقات التجريبي', 'Sample approval timeline')">
            <x-erp.approval-timeline :events="[['title' => $t('إنشاء الطلب', 'Order created'), 'date' => '2026-10-17 09:30', 'status' => 'success', 'actor' => $t('مستخدم تجريبي', 'Sample user')], ['title' => $t('مراجعة المدير', 'Manager review'), 'date' => '2026-10-17 10:00', 'status' => 'warning', 'note' => $t('بانتظار المراجعة · مثال', 'Awaiting review · sample')]]" />
        </x-ui.detail-panel>
        <x-ui.detail-panel :title="$t('سجل التدقيق التجريبي', 'Sample audit history')">
            <x-erp.audit-history :entries="[['actor' => $t('مستخدم تجريبي', 'Sample user'), 'action' => $t('فتح المعاينة', 'Opened preview'), 'date' => '2026-10-17 09:30', 'description' => $t('لا توجد عملية أعمال منفذة.', 'No business action was executed.')]]" />
        </x-ui.detail-panel>
    </div>
    <x-ui.detail-panel :title="$t('الإجراءات والصلاحيات', 'Actions and permissions')">
        <div class="ui-stack">
            <p>{{ $t('المكون يخفي زر العرض فقط. صلاحيات الخادم مطلوبة في أي تنفيذ حقيقي.', 'The component only controls presentation. Server authorization is still required for real execution.') }}</p>
            <x-erp.permission-aware-action :allowed="false"><x-ui.button>{{ $t('إجراء محجوب', 'Restricted action') }}</x-ui.button></x-erp.permission-aware-action>
            <x-erp.permission-aware-action :allowed="true"><x-ui.button variant="secondary" data-open-dialog="demo-modal">{{ $t('معاينة إجراء مسموح', 'Preview permitted action') }}</x-ui.button></x-erp.permission-aware-action>
        </div>
    </x-ui.detail-panel>
</div>
