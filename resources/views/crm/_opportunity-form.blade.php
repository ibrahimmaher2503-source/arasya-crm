<form class="ui-stack" method="post" action="/crm/pipeline{{ $opportunity ? '/'.$opportunity->id : '' }}?locale={{ $locale }}">
    @csrf
    @if ($opportunity)
        @method('PATCH')
        <input type="hidden" name="version" value="{{ $opportunity->version }}">
        <input type="hidden" name="lead_id" value="{{ $opportunity->lead_id }}">
    @else
        <x-ui.field :id="$formId.'-lead'" :label="$t('العميل', 'Lead')"><x-ui.select :id="$formId.'-lead'" name="lead_id" required><option value="">{{ $t('اختر عميلاً', 'Choose a lead') }}</option>@foreach ($leadOptions as $option)<option value="{{ $option['id'] }}">{{ $option['organization'] ?: $option['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
    @endif
    <x-ui.field :id="$formId.'-title'" :label="$t('عنوان الصفقة', 'Opportunity title')"><x-ui.input :id="$formId.'-title'" name="title" :value="$opportunity->title ?? ''" required maxlength="200" /></x-ui.field>
    <x-ui.field :id="$formId.'-amount'" :label="$t('القيمة', 'Amount')"><x-ui.input :id="$formId.'-amount'" name="amount" :value="$opportunity->amount ?? ''" required inputmode="decimal" maxlength="19" /></x-ui.field>
    <x-ui.field :id="$formId.'-currency'" :label="$t('العملة', 'Currency')"><x-ui.input :id="$formId.'-currency'" name="currency" :value="$opportunity->currency ?? $currency" required pattern="[A-Z]{3}" maxlength="3" /></x-ui.field>
    <x-ui.field :id="$formId.'-stage'" :label="$t('المرحلة', 'Stage')"><x-ui.select :id="$formId.'-stage'" name="stage" required>@foreach ($stageNames as $key => $label)<option value="{{ $key }}" @selected(($opportunity->stage ?? 'lead') === $key)>{{ $label }}</option>@endforeach</x-ui.select></x-ui.field>
    <x-ui.field :id="$formId.'-probability'" :label="$t('احتمال الإغلاق %', 'Closing probability %')"><x-ui.input :id="$formId.'-probability'" name="probability" type="number" min="0" max="100" :value="$opportunity->probability ?? 0" required /></x-ui.field>
    <x-ui.field :id="$formId.'-expected'" :label="$t('تاريخ الإغلاق المتوقع', 'Expected close date')"><x-ui.input :id="$formId.'-expected'" name="expected_close" type="date" :value="$opportunity->expected_close ?? ''" /></x-ui.field>
    <x-ui.button type="submit">{{ $t('حفظ', 'Save') }}</x-ui.button>
</form>
