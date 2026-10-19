@php
    $editing = isset($lead['id']);
    $stageLabels = $stageLabels ?? collect($stages)->pluck('label', 'id');
    $consentValue = old('consent', $lead['consent'] ?? false);
    $selectedStageId = old('stage_id', $lead['stage_id'] ?? '');
    $selectedStageVisible = collect($stages)->contains(fn ($stage) => (string) $stage['id'] === (string) $selectedStageId);
    $preferences = old('preferences', $lead['preferences'] ?? ['email' => false, 'phone' => false, 'whatsapp' => false]);
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page">
    @include('crm._nav', ['current' => 'leads'])

    <form id="crm-stage-option-search-form" method="get" action="{{ request()->url() }}">
        <input type="hidden" name="locale" value="{{ $locale }}">
    </form>

    <x-ui.form-actions class="crm-form-header-actions">
        <a href="{{ $editing ? '/crm/leads/'.$lead['id'].'?locale='.$locale : '/crm/leads?locale='.$locale }}">{{ $t('إلغاء', 'Cancel') }}</a>
        <x-ui.button type="submit" form="crm-lead-form">{{ $editing ? $t('حفظ التغييرات', 'Save changes') : $t('حفظ العميل المحتمل', 'Save lead') }}</x-ui.button>
    </x-ui.form-actions>

    <div class="crm-form-layout">
    <form id="crm-lead-form" method="post" action="/crm/leads{{ $editing ? '/'.$lead['id'] : '' }}?locale={{ $locale }}" class="ui-stack crm-form">
        @csrf
        @if ($editing)
            @method('PATCH')
            <input type="hidden" name="version" value="{{ $lead['version'] }}">
        @endif

        <x-ui.form-section :title="$t('بيانات العميل المحتمل', 'Lead details')" :description="$t('سجّل معلومات العميل المحتمل والمرحلة الحالية.', 'Record the lead details and current stage.')">
            <div class="ui-grid">
                <x-ui.field id="crm-lead-name" :label="$t('الاسم', 'Name')" :error="$errors->first('name')">
                    <x-ui.input id="crm-lead-name" name="name" :value="old('name', $lead['name'] ?? '')" maxlength="200" required />
                </x-ui.field>
                <x-ui.field id="crm-lead-organization" :label="$t('الجهة', 'Organization')" :error="$errors->first('organization')">
                    <x-ui.input id="crm-lead-organization" name="organization" :value="old('organization', $lead['organization'] ?? '')" maxlength="200" required />
                </x-ui.field>
                <x-ui.field id="crm-lead-source" :label="$t('المصدر', 'Source')" :error="$errors->first('source')">
                    <x-ui.input id="crm-lead-source" name="source" :value="old('source', $lead['source'] ?? '')" maxlength="60" required />
                </x-ui.field>
                <x-ui.field id="crm-lead-stage" :label="$t('المرحلة', 'Stage')" :error="$errors->first('stage_id')">
                    <div class="crm-stage-controls">
                        <x-ui.select id="crm-lead-stage" name="stage_id">
                            <option value="">{{ $t('بدون مرحلة', 'No stage') }}</option>
                            @if (filled($selectedStageId) && ! $selectedStageVisible)
                                <option value="{{ $selectedStageId }}" selected>{{ $stageLabels[$selectedStageId] ?? $t('المرحلة الحالية أو المحددة', 'Current or selected stage') }}</option>
                            @endif
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}" @selected((string) $selectedStageId === (string) $stage['id'])>{{ $stage['label'] }}</option>
                            @endforeach
                        </x-ui.select>
                        <div class="crm-stage-search">
                            <x-ui.field id="crm-stage-option-search" :label="$t('ابحث في المراحل', 'Search stages')">
                                <x-ui.search-input form="crm-stage-option-search-form" name="stage_q" :value="request('stage_q')" />
                            </x-ui.field>
                            <x-ui.button type="submit" form="crm-stage-option-search-form" variant="secondary">{{ $t('بحث', 'Search') }}</x-ui.button>
                        </div>
                        @if (isset($stagePaginator) && $stagePaginator->hasPages())
                            <div class="crm-stage-controls__pagination">
                                <x-ui.list-pagination :paginator="$stagePaginator" :locale="$locale" :aria-label="$t('صفحات خيارات المرحلة', 'Stage option pages')" />
                            </div>
                        @endif
                    </div>
                </x-ui.field>
            </div>
        </x-ui.form-section>

        @if (! $editing)
            <x-ui.form-section :title="$t('بيانات التواصل', 'Contact details')" :description="$t('تُحفظ بيانات التواصل بشكل محمي. لا يلزم إدخالها الآن.', 'Contact details are stored securely and are optional.')">
                <div class="ui-grid">
                    <x-ui.field id="crm-contact-email" :label="$t('البريد الإلكتروني', 'Email')" :error="$errors->first('contacts.email')">
                        <x-ui.input id="crm-contact-email" type="email" name="contacts[email]" :value="old('contacts.email')" maxlength="254" dir="ltr" />
                    </x-ui.field>
                    <x-ui.field id="crm-contact-phone" :label="$t('الهاتف', 'Phone')" :error="$errors->first('contacts.phone')">
                        <x-ui.input id="crm-contact-phone" name="contacts[phone]" :value="old('contacts.phone')" maxlength="64" dir="ltr" />
                    </x-ui.field>
                    <x-ui.field id="crm-contact-whatsapp" :label="$t('واتساب', 'WhatsApp')" :error="$errors->first('contacts.whatsapp')">
                        <x-ui.input id="crm-contact-whatsapp" name="contacts[whatsapp]" :value="old('contacts.whatsapp')" maxlength="64" dir="ltr" />
                    </x-ui.field>
                </div>
            </x-ui.form-section>
        @endif

        <x-ui.form-section :title="$t('معلومات إضافية', 'Additional information')">
            <x-ui.field id="crm-lead-notes" :label="$t('ملاحظات', 'Notes')" :error="$errors->first('notes')">
                <x-ui.textarea id="crm-lead-notes" name="notes" maxlength="4000">{{ old('notes', $lead['notes'] ?? '') }}</x-ui.textarea>
            </x-ui.field>
            <input type="hidden" name="consent" value="0">
            <x-ui.field id="crm-lead-consent" :label="$t('تم الحصول على الموافقة للتواصل', 'Consent to contact is recorded')" :error="$errors->first('consent')">
                <x-ui.checkbox id="crm-lead-consent" name="consent" value="1" :checked="(bool) $consentValue" />
            </x-ui.field>
            <fieldset class="crm-preferences">
                <legend>{{ $t('قنوات التواصل المفضلة', 'Preferred communication channels') }}</legend>
                <p class="ui-muted">{{ $t('اختر القنوات التي وافق العميل على استخدامها.', 'Select channels the lead has agreed to use.') }}</p>
                <div class="ui-grid">
                    @foreach (['email' => $t('البريد الإلكتروني', 'Email'), 'phone' => $t('الهاتف', 'Phone'), 'whatsapp' => $t('واتساب', 'WhatsApp')] as $channel => $label)
                        <input type="hidden" name="preferences[{{ $channel }}]" value="0">
                        <x-ui.field :id="'crm-preference-'.$channel" :label="$label" :error="$errors->first('preferences.'.$channel)">
                            <x-ui.checkbox :id="'crm-preference-'.$channel" :name="'preferences['.$channel.']'" value="1" :checked="(bool) ($preferences[$channel] ?? false)" />
                        </x-ui.field>
                    @endforeach
                </div>
                @error('preferences')<x-ui.validation-message>{{ $message }}</x-ui.validation-message>@enderror
            </fieldset>
        </x-ui.form-section>

        <x-ui.form-actions>
            <x-ui.button type="submit">{{ $editing ? $t('حفظ التغييرات', 'Save changes') : $t('حفظ العميل المحتمل', 'Save lead') }}</x-ui.button>
            <a href="{{ $editing ? '/crm/leads/'.$lead['id'].'?locale='.$locale : '/crm/leads?locale='.$locale }}">{{ $t('إلغاء', 'Cancel') }}</a>
        </x-ui.form-actions>
    </form>
    <aside class="crm-form-summary">
        <x-ui.detail-panel :title="$editing ? $t('ملخص السجل الحالي', 'Current record summary') : $t('قبل الحفظ', 'Before you save')">
            @if ($editing)
                <dl class="crm-form-summary__facts">
                    <div><dt>{{ $t('الاسم', 'Name') }}</dt><dd>{{ $lead['name'] }}</dd></div>
                    <div><dt>{{ $t('الجهة', 'Organization') }}</dt><dd>{{ $lead['organization'] ?: '—' }}</dd></div>
                    <div><dt>{{ $t('المصدر', 'Source') }}</dt><dd>{{ $lead['source'] }}</dd></div>
                    <div><dt>{{ $t('المرحلة', 'Stage') }}</dt><dd>{{ $lead['stage_id'] !== null ? ($stageLabels[$lead['stage_id']] ?? $lead['stage_id']) : $t('بدون مرحلة', 'No stage') }}</dd></div>
                    <div><dt>{{ $t('الإسناد', 'Assignment') }}</dt><dd>{{ $lead['assignee_id'] !== null ? $t('مسند', 'Assigned') : $t('غير مسند', 'Unassigned') }}</dd></div>
                    <div><dt>{{ $t('المعرّف', 'Record ID') }}</dt><dd><bdi>{{ $lead['id'] }}</bdi></dd></div>
                </dl>
            @else
                <p>{{ $t('الاسم والجهة والمصدر مطلوبة. المرحلة اختيارية.', 'Name, organization, and source are required. Stage is optional.') }}</p>
                <p class="ui-muted">{{ $t('بيانات التواصل اختيارية وتُحفظ بشكل محمي. سجّل الموافقة والقنوات التي وافق العميل على استخدامها.', 'Contact details are optional and stored securely. Record consent and only the channels the lead agreed to use.') }}</p>
            @endif
        </x-ui.detail-panel>
    </aside>
    </div>
</div>
@endsection
