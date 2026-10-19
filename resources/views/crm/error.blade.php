@php
    $locale = request()->query('locale') === 'en' ? 'en' : 'ar';
    $t = static fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $statusCode = filter_var($status ?? null, FILTER_VALIDATE_INT) ?: 500;
    $statusMessages = [
        400 => $t('تعذر معالجة الطلب. راجع البيانات المطلوبة ثم أعد المحاولة.', 'The request could not be processed. Review the required details and try again.'),
        401 => $t('يلزم تسجيل الدخول للمتابعة.', 'Sign in to continue.'),
        403 => $t('لا تملك الصلاحية لتنفيذ هذا الإجراء.', 'You do not have permission to complete this action.'),
        404 => $t('لم يُعثر على السجل المطلوب. قد يكون حُذف أو لم يعد متاحاً ضمن نطاقك.', 'The requested record was not found or is no longer available in your scope.'),
        409 => $t('تغيّرت حالة السجل منذ فتحه. أعد تحميله ثم حاول مرة أخرى.', 'The record changed after you opened it. Reload it and try again.'),
        419 => $t('انتهت صلاحية التحقق من الطلب. أعد فتح الصفحة ثم حاول مرة أخرى.', 'The request verification expired. Reopen the page and try again.'),
        422 => $t('راجع الحقول المحددة ثم أرسل النموذج مرة أخرى.', 'Review the listed fields, then submit the form again.'),
        'default' => $t('تعذر إكمال الطلب. ارجع إلى إدارة العملاء وحاول مرة أخرى.', 'The request could not be completed. Return to CRM and try again.'),
    ];
    $pageTitle = $t('تعذر إكمال الطلب', 'Request could not be completed');
    $fieldLabels = [
        'name' => $t('الاسم', 'Name'),
        'organization' => $t('الجهة', 'Organization'),
        'source' => $t('المصدر', 'Source'),
        'stage_id' => $t('المرحلة', 'Stage'),
        'contacts' => $t('بيانات التواصل', 'Contact details'),
        'contacts.email' => $t('البريد الإلكتروني', 'Email'),
        'contacts.phone' => $t('الهاتف', 'Phone'),
        'contacts.whatsapp' => $t('واتساب', 'WhatsApp'),
        'consent' => $t('الموافقة على التواصل', 'Consent to contact'),
        'preferences' => $t('قنوات التواصل المفضلة', 'Preferred communication channels'),
        'preferences.email' => $t('قناة البريد الإلكتروني', 'Email preference'),
        'preferences.phone' => $t('قناة الهاتف', 'Phone preference'),
        'preferences.whatsapp' => $t('قناة واتساب', 'WhatsApp preference'),
        'notes' => $t('الملاحظات', 'Notes'),
        'version' => $t('إصدار السجل', 'Record version'),
        'assignee_id' => $t('المسند إليه', 'Assignee'),
        'recipient_id' => $t('المستلم', 'Recipient'),
        'context' => $t('سياق النقل', 'Transfer context'),
        'kind' => $t('نوع النشاط', 'Activity type'),
        'outcome_id' => $t('نتيجة النشاط', 'Activity outcome'),
        'next_step' => $t('الخطوة التالية', 'Next step'),
        'note' => $t('ملاحظة النشاط', 'Activity note'),
        'occurred_at' => $t('وقت النشاط', 'Activity time'),
        'next_follow_up' => $t('موعد المتابعة', 'Follow-up time'),
        'decision' => $t('قرار النقل', 'Transfer decision'),
        'reason' => $t('سبب عرض بيانات التواصل', 'Reason for revealing contact details'),
    ];
    $visibleFieldLabels = [];
    foreach (is_array($fields ?? null) ? $fields : [] as $field) {
        if (is_string($field) && isset($fieldLabels[$field])) {
            $visibleFieldLabels[] = $fieldLabels[$field];
        }
    }
    $visibleFieldLabels = array_values(array_unique($visibleFieldLabels));
    $leadId = request()->route('lead');
    if ($statusCode === 401) {
        $returnUrl = route('login');
        $returnLabel = $t('تسجيل الدخول', 'Sign in');
    } elseif ($statusCode === 422 && request()->routeIs('crm.leads.store')) {
        $returnUrl = route('crm.leads.create', ['locale' => $locale]);
        $returnLabel = $t('العودة إلى النموذج', 'Return to the form');
    } elseif ($statusCode === 422 && request()->routeIs('crm.leads.update') && is_string($leadId)) {
        $returnUrl = route('crm.leads.edit', ['lead' => $leadId, 'locale' => $locale]);
        $returnLabel = $t('العودة إلى النموذج', 'Return to the form');
    } elseif ($statusCode !== 404 && is_string($leadId)) {
        $returnUrl = route('crm.leads.show', ['lead' => $leadId, 'locale' => $locale]);
        $returnLabel = $t('العودة إلى السجل', 'Return to the record');
    } else {
        $returnUrl = route('crm.leads.index', ['locale' => $locale]);
        $returnLabel = $t('العودة إلى العملاء المحتملين', 'Return to leads');
    }
@endphp
@extends('layouts.shell', ['title' => $pageTitle])
@section('content')
    <div class="ui-stack crm-error-page">
        <x-ui.alert variant="danger">
            <p>{{ $statusMessages[$statusCode] ?? $statusMessages['default'] }}</p>
            @if ($statusCode === 422 && count($visibleFieldLabels))
                <ul>
                    @foreach ($visibleFieldLabels as $fieldLabel)<li>{{ $fieldLabel }}</li>@endforeach
                </ul>
            @elseif ($statusCode === 422)
                <p>{{ $t('راجع بيانات النموذج وأعد المحاولة.', 'Review the form details and try again.') }}</p>
            @endif
        </x-ui.alert>
        <div class="ui-inline crm-error-actions">
            <a class="ui-button" href="{{ $returnUrl }}">{{ $returnLabel }}</a>
            <a href="/crm?locale={{ $locale }}">{{ $t('العودة إلى نظرة عامة', 'Return to overview') }}</a>
        </div>
    </div>
@endsection
