@extends('layouts.shell')
@section('content')
@php
    $lines = $quotation['lines'] ?? [['description' => '', 'quantity' => '1', 'unit_price' => '', 'discount' => '0', 'tax_rate' => '']];
    $lineLabels = ['description' => $t('الوصف', 'Description'), 'quantity' => $t('الكمية', 'Quantity'), 'unit_price' => $t('سعر الوحدة', 'Unit price'), 'discount' => $t('قيمة الخصم', 'Discount amount'), 'tax_rate' => $t('الضريبة %', 'Tax %')];
@endphp
<div class="ui-stack crm-page">
    @include('crm._entity-header')
    <p class="ui-muted">{{ $t('مسودة تجريبية غير ملزمة. أدخل نسبة الضريبة صراحةً؛ لا يتم تقريب الحسابات أو اعتماد العرض.', 'Non-binding synthetic draft. Enter an explicit tax rate; calculations are not rounded and the quotation is not approved.') }}</p>
    <form class="ui-detail-panel ui-stack" method="post" action="/crm/leads/{{ $lead['id'] }}/quotations{{ $quotation ? '/'.$quotation['id'] : '' }}?locale={{ $locale }}" data-quotation-form>
        @csrf
        @if ($quotation) @method('PATCH') <input type="hidden" name="version" value="{{ $quotation['version'] }}"> @endif
        <x-ui.field id="quote-title" :label="$t('عنوان العرض', 'Quotation title')"><x-ui.input id="quote-title" name="title" :value="$quotation['title'] ?? ''" required maxlength="200" /></x-ui.field>
        <x-ui.field id="quote-currency" :label="$t('رمز العملة', 'Currency code')" :hint="$t('ثلاثة أحرف مثل SAR أو EGP', 'Three letters such as SAR or EGP')"><x-ui.input id="quote-currency" name="currency" :value="$quotation['currency'] ?? ''" required pattern="[A-Z]{3}" maxlength="3" /></x-ui.field>
        <div class="ui-stack" data-quote-lines>
            @foreach ($lines as $index => $line)
            <fieldset class="ui-detail-panel ui-stack" data-quote-line>
                <legend>{{ $t('بند العرض', 'Quotation line') }}</legend>
                @foreach ($lineLabels as $key => $label)
                <x-ui.field :id="'line-'.$index.'-'.$key" :label="$label"><x-ui.input :id="'line-'.$index.'-'.$key" :name="'lines['.$index.']['.$key.']'" :value="$line[$key]" required :maxlength="$key === 'description' ? 200 : 19" :inputmode="$key === 'description' ? 'text' : 'decimal'" :data-line-field="$key" /></x-ui.field>
                @endforeach
                <x-ui.button variant="danger" data-remove-line>{{ $t('حذف البند', 'Remove line') }}</x-ui.button>
            </fieldset>
            @endforeach
        </div>
        <div class="ui-inline">
            <x-ui.button variant="secondary" data-add-line><x-ui.icon name="plus" />{{ $t('إضافة بند', 'Add line') }}</x-ui.button>
            <x-ui.button type="submit">{{ $t('حفظ المسودة', 'Save draft') }}</x-ui.button>
            <a class="ui-button ui-button--ghost" href="/crm/leads/{{ $lead['id'] }}/quotations?locale={{ $locale }}">{{ $t('إلغاء', 'Cancel') }}</a>
        </div>
    </form>
</div>
<script>
(() => {
    const form = document.querySelector('[data-quotation-form]');
    const list = form.querySelector('[data-quote-lines]');
    let nextIndex = list.children.length;
    const update = () => {
        form.querySelector('[data-add-line]').disabled = list.children.length >= 50;
        list.querySelectorAll('[data-remove-line]').forEach(button => { button.disabled = list.children.length === 1; });
    };
    form.querySelector('[data-add-line]').addEventListener('click', () => {
        if (list.children.length >= 50) return;
        const row = list.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-line-field]').forEach(input => {
            const key = input.dataset.lineField;
            const label = row.querySelector(`label[for="${input.id}"]`);
            input.name = `lines[${nextIndex}][${key}]`;
            input.id = `line-${nextIndex}-${key}`;
            if (label) label.htmlFor = input.id;
            input.value = key === 'quantity' ? '1' : key === 'discount' ? '0' : '';
        });
        nextIndex++;
        list.append(row);
        update();
        row.querySelector('input').focus();
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-line]');
        if (!button || list.children.length === 1) return;
        button.closest('[data-quote-line]').remove();
        update();
        form.querySelector('[data-add-line]').focus();
    });
    update();
})();
</script>
@endsection
