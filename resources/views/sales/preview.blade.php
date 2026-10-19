@extends('layouts.shell')
@section('content')
<article class="ui-detail-panel ui-stack quotation-print-preview">
    <h2>{{ $quotation['title'] }}</h2>
    <p>{{ $lead['organization'] ?: $lead['name'] }}</p>
    <p><strong>{{ $t('مسودة تجريبية غير ملزمة — غير معتمدة ولا يجوز إرسالها للعميل', 'Non-binding synthetic draft — not approved and must not be sent to a customer') }}</strong></p>
    <x-ui.table :caption="$t('بنود العرض', 'Quotation lines')">
        <thead><tr><th>{{ $t('الوصف', 'Description') }}</th><th>{{ $t('الكمية', 'Quantity') }}</th><th>{{ $t('سعر الوحدة', 'Unit price') }}</th><th>{{ $t('الخصم', 'Discount') }}</th><th>{{ $t('الضريبة %', 'Tax %') }}</th></tr></thead>
        <tbody>@foreach ($quotation['lines'] as $line)<tr>@foreach (['description', 'quantity', 'unit_price', 'discount', 'tax_rate'] as $key)<td>{{ $line[$key] }}</td>@endforeach</tr>@endforeach</tbody>
    </x-ui.table>
    <dl class="crm-contact-list">@foreach (['subtotal' => $t('قبل الخصم', 'Subtotal'), 'discount' => $t('الخصم', 'Discount'), 'tax' => $t('الضريبة', 'Tax'), 'total' => $t('الإجمالي', 'Total')] as $key => $label)<div><dt>{{ $label }}</dt><dd><bdi>{{ $quotation['totals'][$key] }} {{ $quotation['currency'] }}</bdi></dd></div>@endforeach</dl>
    <p class="ui-muted">{{ $t('استخدم أمر الطباعة في المتصفح ثم حفظ كملف PDF. لا توجد قواعد تقريب مالية معتمدة في هذه المعاينة.', 'Use your browser’s Print command and Save as PDF. No approved financial rounding rules are applied in this preview.') }}</p>
    <a class="ui-button ui-button--secondary quotation-preview-back" href="/crm/leads/{{ $lead['id'] }}/quotations?{{ http_build_query(['locale' => $locale, 'selected' => $quotation['id']]) }}">{{ $t('عودة', 'Back') }}</a>
</article>
@endsection
