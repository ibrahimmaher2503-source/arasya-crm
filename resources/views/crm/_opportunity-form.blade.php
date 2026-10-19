<form class="ui-stack" method="post" action="/crm/pipeline{{ $opportunity ? '/'.$opportunity->id : '' }}?locale={{ $locale }}">
    @csrf
    @if ($opportunity)
        @method('PATCH')
        <input type="hidden" name="version" value="{{ $opportunity->version }}">
        <input type="hidden" name="lead_id" value="{{ $opportunity->lead_id }}">
    @else
        <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <x-ui.field :id="$formId.'-lead'" :label="request('source') === 'customer' ? $t('العميل المحول', 'Customer') : $t('العميل المحتمل', 'Lead')"><x-ui.select :id="$formId.'-lead'" :name="request('source') === 'customer' ? 'customer_id' : 'lead_id'" required><option value="">{{ request('source') === 'customer' ? $t('اختر عميلاً محولاً', 'Choose a customer') : $t('اختر عميلاً', 'Choose a lead') }}</option>@foreach ($leadOptions as $option)<option value="{{ request('source') === 'customer' ? $option['customer_id'] : $option['id'] }}">{{ $option['organization'] ?: $option['name'] }}</option>@endforeach</x-ui.select></x-ui.field>
    @endif
    <x-ui.field :id="$formId.'-title'" :label="$t('عنوان الصفقة', 'Opportunity title')"><x-ui.input :id="$formId.'-title'" name="title" :value="$opportunity->title ?? ''" required maxlength="200" /></x-ui.field>
    <x-ui.field :id="$formId.'-amount'" :label="$t('القيمة', 'Amount')"><x-ui.input :id="$formId.'-amount'" name="amount" :value="$opportunity->amount ?? ''" required inputmode="decimal" maxlength="19" /></x-ui.field>
    <x-ui.field :id="$formId.'-currency'" :label="$t('العملة', 'Currency')"><x-ui.input :id="$formId.'-currency'" name="currency" :value="$opportunity->currency ?? $currency" required pattern="[A-Z]{3}" maxlength="3" /></x-ui.field>
    <input type="hidden" name="stage" value="{{ $opportunity->stage ?? 'lead' }}">
    <p class="ui-muted">{{ $t('المرحلة', 'Stage') }}: {{ $stageNames[$opportunity->stage ?? 'lead'] }}</p>
    <x-ui.field :id="$formId.'-probability'" :label="$t('احتمال الإغلاق %', 'Closing probability %')"><x-ui.input :id="$formId.'-probability'" name="probability" type="number" min="0" max="100" :value="$opportunity->probability ?? 0" required /></x-ui.field>
    <x-ui.field :id="$formId.'-expected'" :label="$t('تاريخ الإغلاق المتوقع', 'Expected close date')"><x-ui.input :id="$formId.'-expected'" name="expected_close" type="date" :value="$opportunity->expected_close ?? ''" /></x-ui.field>
    <x-ui.field :id="$formId.'-work-type'" :label="$t('نوع العمل', 'Work type')">
        <x-ui.select :id="$formId.'-work-type'" name="work_type">
            <option value="">{{ $t('غير محدد', 'Not specified') }}</option>
            @foreach ($workTypes as $key => $label)<option value="{{ $key }}" @selected(old('work_type', $opportunity->work_type ?? '') === $key)>{{ $label }}</option>@endforeach
        </x-ui.select>
    </x-ui.field>
    <x-ui.field :id="$formId.'-requirements'" :label="$t('متطلبات العمل', 'Work requirements')"><x-ui.textarea :id="$formId.'-requirements'" name="requirements" maxlength="4000">{{ old('requirements', $opportunity ? $selectedContext->requirements : '') }}</x-ui.textarea></x-ui.field>
    <x-ui.button type="submit">{{ $t('حفظ', 'Save') }}</x-ui.button>
</form>
