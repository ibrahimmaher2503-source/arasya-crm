@php
    $activitySection = $activitySection ?? 'activities';
    $activityDialog = $activityDialog ?? 'crm-lead-actions';
    $activityQueryKeys = ['locale', 'q', 'kind', 'page', 'per_page', 'outcome_q', 'outcome_page', 'recipient_q', 'recipient_page'];
@endphp
<div data-crm-outcome-picker data-crm-option-name="outcome_id" data-crm-picker-dialog="{{ $activityDialog }}">
<form method="get" action="{{ request()->url() }}" class="crm-option-search" data-crm-outcome-search role="search" aria-label="{{ $t('البحث عن نتيجة نشاط', 'Find an activity outcome') }}">
    @foreach (request()->only(array_diff($activityQueryKeys, ['outcome_q', 'outcome_page'])) as $key => $value)
        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <x-ui.field id="crm-outcome-option-search" :label="$t('ابحث في النتائج', 'Search outcomes')"><x-ui.search-input name="outcome_q" :value="request('outcome_q')" /></x-ui.field>
    <x-ui.button type="submit" variant="secondary">{{ $t('بحث عن نتيجة', 'Find outcome') }}</x-ui.button>
</form>
@if (count($outcomes))
    <form method="post" action="/crm/leads/{{ $lead['id'] }}/activities?locale={{ $locale }}" class="ui-stack crm-form">
        @csrf
        <input type="hidden" name="version" value="{{ $lead['version'] }}">
        <div class="ui-grid">
            @if ($activitySection === 'notes')<input type="hidden" name="kind" value="note">@else
<x-ui.field id="crm-activity-kind" :label="$t('نوع النشاط', 'Activity type')" :error="$errors->first('kind')">
                <x-ui.select id="crm-activity-kind" name="kind" required>
                    @foreach (['call' => $t('مكالمة', 'Call'), 'message' => $t('رسالة', 'Message'), 'meeting' => $t('اجتماع', 'Meeting'), 'visit' => $t('زيارة', 'Visit'), 'note' => $t('ملاحظة', 'Note'), 'quotation' => $t('عرض سعر', 'Quotation')] as $kind => $label)
                        <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
@endif
            <x-ui.field id="crm-activity-outcome" :label="$t('النتيجة', 'Outcome')" :error="$errors->first('outcome_id')">
                <x-ui.select id="crm-activity-outcome" name="outcome_id" required>
                    <option value="">{{ $t('اختر النتيجة', 'Choose an outcome') }}</option>
                    @if (filled(old('outcome_id')) && ! collect($outcomes)->contains(fn ($outcome) => (string) $outcome['id'] === (string) old('outcome_id')))
                        <option value="{{ old('outcome_id') }}" selected>{{ $t('النتيجة المحددة', 'Selected outcome') }} · <bdi>{{ old('outcome_id') }}</bdi></option>
                    @endif
                    @foreach ($outcomes as $outcome)
                        <option value="{{ $outcome['id'] }}" @selected((string) old('outcome_id') === (string) $outcome['id'])>{{ $outcome['label'] }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field id="crm-activity-occurred" :label="$t('وقت النشاط مع المنطقة الزمنية', 'Activity time with timezone')" :hint="$t('استخدم صيغة مثل 2026-10-18T10:30+03:00.', 'Use a format such as 2026-10-18T10:30+03:00.')" :error="$errors->first('occurred_at')">
            <x-ui.input id="crm-activity-occurred" name="occurred_at" :value="old('occurred_at', now()->format(DATE_ATOM))" maxlength="64" dir="ltr" required />
            </x-ui.field>
        </div>
        <x-ui.field id="crm-activity-next-step" :label="$t('الخطوة التالية', 'Next step')" :error="$errors->first('next_step')">
            <x-ui.input id="crm-activity-next-step" name="next_step" :value="old('next_step')" maxlength="200" required />
        </x-ui.field>
        <x-ui.field id="crm-activity-note" :label="$t('ملاحظة', 'Note')" :error="$errors->first('note')">
            <x-ui.textarea id="crm-activity-note" name="note" maxlength="10000" :required="$activitySection === 'notes'">{{ old('note') }}</x-ui.textarea>
        </x-ui.field>
        @if (in_array('tasks.write', $permissions, true))
            <x-ui.field id="crm-activity-follow-up" :label="$t('موعد المتابعة مع المنطقة الزمنية', 'Follow-up time with timezone')" :hint="$t('اتركه فارغاً أو استخدم صيغة مثل 2026-10-18T10:30+03:00.', 'Leave blank or use a format such as 2026-10-18T10:30+03:00.')" :error="$errors->first('next_follow_up')">
                <x-ui.input id="crm-activity-follow-up" name="next_follow_up" :required="$activitySection === 'tasks'" :value="old('next_follow_up')" maxlength="64" dir="ltr" />
            </x-ui.field>
        @endif
        <x-ui.form-actions><x-ui.button type="submit">{{ $t('تسجيل النشاط', 'Record activity') }}</x-ui.button></x-ui.form-actions>
    </form>
@else
    <x-ui.empty-state :title="$t('لا توجد نتائج متاحة', 'No outcomes available')" :description="$t('لا يمكن تسجيل نشاط حتى تتوفر نتيجة صالحة.', 'An activity cannot be recorded until a valid outcome is available.')" />
@endif
<div data-crm-outcome-pages>
@if (isset($outcomePaginator))
    <x-ui.list-pagination :paginator="$outcomePaginator" :locale="$locale" :aria-label="$t('صفحات النتائج', 'Outcome pages')" />
@endif
</div>
<p role="alert" data-crm-outcome-error hidden>{{ $t('تعذر تحديث الخيارات.', 'Could not update choices. Try again.') }}</p>
</div>
@include('crm._outcome-picker-script')
