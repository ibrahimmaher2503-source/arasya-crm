@extends('layouts.shell')

@section('content')
    @php($t = fn (string $arabic, string $english): string => $locale === 'ar' ? $arabic : $english)
    <div class="ui-stack">
        <div class="ui-demo-note" role="note" data-testid="demo-notice">
            {{ $t('بيئة تطوير · بيانات تركيبية للعرض فقط. لا حفظ أعمال أو رفع ملفات أو تنفيذ AI من هذه الصفحة.', 'Development only · Synthetic demonstration data. This page does not save business records, upload files or execute AI actions.') }}
        </div>
        <x-ui.breadcrumbs :aria-label="$t('مسار الصفحة', 'Breadcrumbs')" :items="[['label' => $t('أراسيا', 'Arasya'), 'url' => '/dev/design-system'], ['label' => $title]]" />
        @include('design-system.'.$screen)
    </div>

    <x-ui.modal id="demo-modal" :title="$t('معاينة المكون', 'Component preview')" :close-label="$t('إغلاق', 'Close')">
        <div class="ui-stack">
            <p>{{ $t('هذه نافذة عرض محلية؛ لم يتم تغيير أي سجل.', 'This is a local preview. No record has been changed.') }}</p>
            <x-ui.field id="dialog-note" :label="$t('ملاحظة', 'Note')"><x-ui.input name="note" /></x-ui.field>
            <x-ui.button data-close-dialog>{{ $t('تم', 'Done') }}</x-ui.button>
        </div>
    </x-ui.modal>
    <x-ui.drawer id="demo-drawer" :title="$t('تفاصيل إضافية', 'More details')" :close-label="$t('إغلاق', 'Close')">
        <x-ui.description-list :items="[$t('المصدر', 'Source') => $t('مثال تطوير', 'Development example'), $t('التنفيذ', 'Execution') => $t('غير متاح', 'Unavailable')]" />
    </x-ui.drawer>
    <x-ui.confirmation-dialog id="demo-confirm" :title="$t('تأكيد الإجراء', 'Confirm action')" :cancel-label="$t('إلغاء', 'Cancel')" :close-label="$t('إغلاق', 'Close')">
        <x-slot:confirm>
            <x-ui.button variant="danger" disabled>{{ $t('الحذف غير متاح في العرض', 'Delete unavailable in preview') }}</x-ui.button>
        </x-slot:confirm>
        {{ $t('مثال لحالة إجراء خطر، دون نقطة تنفيذ خلفية.', 'An example of a destructive confirmation, with no execution endpoint.') }}
    </x-ui.confirmation-dialog>
@endsection
