@php
    $stageLabels = $stageLabels ?? collect($stages ?? [])->pluck('label', 'id');
    $stageLabel = ($lead['stage_id'] ?? null) === null ? $t('بدون مرحلة', 'No stage') : ($stageLabels[$lead['stage_id']] ?? $lead['stage_id']);
    $pendingTransfer = (array) ($lead['pending_transfer'] ?? []);
    $revealedContacts = is_array($reveal ?? null) ? ($reveal['contacts'] ?? $reveal) : null;
    $currentRecipient = isset($pendingTransfer['recipient_id']) && (string) $pendingTransfer['recipient_id'] === (string) ($actor['id'] ?? '');
    $crmQueryKeys = ['locale', 'q', 'stage', 'archived', 'per_page', 'stage_q', 'stage_page', 'outcome_q', 'outcome_page', 'recipient_q', 'recipient_page', 'inbox_page'];
    $preferences = $lead['preferences'] ?? [];
    $notesContent = filled($lead['notes'] ?? null) ? $lead['notes'] : $t('لا توجد ملاحظات.', 'No notes recorded.');
    $consentContent = $lead['consent'] ? $t('الموافقة على التواصل مسجلة.', 'Consent to contact is recorded.') : $t('لم تُسجل موافقة على التواصل.', 'Consent to contact is not recorded.');
    $activityKindLabels = [
        'call' => $t('مكالمة', 'Call'), 'message' => $t('رسالة', 'Message'),
        'meeting' => $t('اجتماع', 'Meeting'), 'visit' => $t('زيارة', 'Visit'),
        'note' => $t('ملاحظة', 'Note'), 'quotation' => $t('عرض سعر', 'Quotation'),
    ];
    $historyLabels = [
        'lead.created' => $t('تم إنشاء السجل', 'Lead created'),
        'lead.updated' => $t('تم تحديث السجل', 'Lead updated'),
        'lead.assigned' => $t('تم إسناد السجل', 'Lead assigned'),
        'lead.activity.recorded' => $t('تم تسجيل نشاط', 'Activity recorded'),
        'lead.transfer.requested' => $t('طُلب نقل السجل', 'Transfer requested'),
        'lead.transfer.accepted' => $t('تم قبول نقل السجل', 'Transfer accepted'),
        'lead.transfer.rejected' => $t('تم رفض نقل السجل', 'Transfer rejected'),
        'lead.converted' => $t('تم تحويل السجل إلى عميل', 'Lead converted'),
        'lead.archived' => $t('تمت أرشفة السجل', 'Lead archived'),
        'lead.contacts.revealed' => $t('تم عرض بيانات التواصل', 'Contact details revealed'),
    ];
    $historyEntries = array_map(fn (array $entry): array => [
        'action' => $historyLabels[$entry['action']] ?? $entry['action'],
        'actor' => $t('معرّف الحساب', 'Account ID').': '.$entry['actor_id'],
        'date' => $entry['created_at'],
        'description' => isset($entry['metadata']['version']) ? $t('الإصدار', 'Version').': '.$entry['metadata']['version'] : null,
    ], $lead['history'] ?? []);
@endphp
@extends('layouts.shell')
@section('content')
<div class="ui-stack crm-page crm-lead-detail" data-lead-overview>
    @include('crm._entity-header', ['showOverviewLink' => false])
    @include('crm._workspace-nav', ['section' => 'overview'])
    @if (filled($lead['pending_transfer'] ?? null))
        <x-ui.detail-panel :title="$t('طلب نقل معلق', 'Pending transfer')">
            <p>{{ $pendingTransfer['context'] ?? '' }}</p>
            @if ($currentRecipient && in_array('crm.write', $permissions, true))
                <div class="ui-inline">
                    @foreach (['accept' => [$t('قبول النقل', 'Accept transfer'), 'primary'], 'reject' => [$t('رفض النقل', 'Reject transfer'), 'secondary']] as $decision => [$label, $variant])
                        <form method="post" action="/crm/transfers/{{ $pendingTransfer['id'] }}/response?locale={{ $locale }}">
                            @csrf
                            <input type="hidden" name="decision" value="{{ $decision }}">
                            <input type="hidden" name="version" value="{{ $lead['version'] }}">
                            <x-ui.button type="submit" :variant="$variant">{{ $label }}</x-ui.button>
                        </form>
                    @endforeach
                </div>
            @endif
        </x-ui.detail-panel>
    @endif


    @include('crm._overview-board')
    <x-ui.modal id="crm-lead-actions" :title="$t('إجراءات السجل', 'Record actions')" :close-label="$t('إغلاق', 'Close')">
    @if (! $lead['archived'] && in_array('crm.write', $permissions, true))
        <details class="ui-detail-panel crm-workflow-disclosure" @if ($errors->has('kind') || $errors->has('outcome_id') || $errors->has('occurred_at') || $errors->has('next_step') || $errors->has('note') || $errors->has('next_follow_up')) open @endif>
            <summary class="ui-detail-panel__title crm-workflow-disclosure__summary">{{ $t('تسجيل نشاط', 'Record activity') }}</summary>
            <div class="crm-workflow-disclosure__content">
                @include('crm._activity-form', ['activitySection' => 'activities', 'activityDialog' => 'crm-lead-actions'])
            </div>
        </details>
    @endif



    @if (! $lead['archived'] && $lead['assignee_id'] === null && in_array('crm.manage', $permissions, true) && in_array('crm.assign', $permissions, true))
        <details class="ui-detail-panel crm-workflow-disclosure" @if ($errors->has('assignee_id')) open @endif>
            <summary class="ui-detail-panel__title crm-workflow-disclosure__summary">{{ $t('إسناد السجل', 'Assign lead') }}</summary>
            <div class="crm-workflow-disclosure__content">
                <form method="get" action="{{ request()->url() }}" class="crm-option-search" role="search" aria-label="{{ $t('البحث عن مستخدم للإسناد', 'Find a user to assign') }}">
                    @foreach (request()->only(array_diff($crmQueryKeys, ['recipient_q', 'recipient_page'])) as $key => $value)
                        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <x-ui.field id="crm-assignee-option-search" :label="$t('ابحث عن مستخدم', 'Search users')"><x-ui.search-input name="recipient_q" :value="request('recipient_q')" /></x-ui.field>
                    <x-ui.button type="submit" variant="secondary">{{ $t('بحث عن مستخدم', 'Find user') }}</x-ui.button>
                </form>
                @if (count($recipients))
                    <form method="post" action="/crm/leads/{{ $lead['id'] }}/assignment?locale={{ $locale }}" class="crm-inline-form">
                        @csrf
                        <input type="hidden" name="version" value="{{ $lead['version'] }}">
                        <x-ui.field id="crm-assignee" :label="$t('المسند إليه', 'Assign to')" :error="$errors->first('assignee_id')">
                            <x-ui.select id="crm-assignee" name="assignee_id" required>
                                @if (filled(old('assignee_id')) && ! collect($recipients)->contains(fn ($recipient) => (string) $recipient['id'] === (string) old('assignee_id')))
                                    <option value="{{ old('assignee_id') }}" selected>{{ $t('المستخدم المحدد', 'Selected user') }} · <bdi>{{ old('assignee_id') }}</bdi></option>
                                @endif
                                <option value="">{{ $t('اختر مستخدماً', 'Choose a user') }}</option>
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient['id'] }}" @selected((string) old('assignee_id') === (string) $recipient['id'])>{{ $recipient['name'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.button type="submit">{{ $t('إسناد السجل', 'Assign lead') }}</x-ui.button>
                    </form>
                @else
                    <x-ui.empty-state :title="$t('لا يوجد مستخدمون متاحون', 'No eligible users available')" />
                @endif
                @if (isset($recipientPaginator))
                    <x-ui.list-pagination :paginator="$recipientPaginator" :locale="$locale" :aria-label="$t('صفحات المستخدمين', 'User pages')" />
                @endif
            </div>
        </details>
    @endif

    @if (! $lead['archived'] && ! filled($lead['pending_transfer'] ?? null) && $lead['assignee_id'] !== null && in_array('crm.transfer', $permissions, true))
        <details class="ui-detail-panel crm-workflow-disclosure" @if ($errors->has('recipient_id') || $errors->has('context')) open @endif>
            <summary class="ui-detail-panel__title crm-workflow-disclosure__summary">{{ $t('طلب نقل السجل', 'Request a lead transfer') }}</summary>
            <div class="crm-workflow-disclosure__content">
                <form method="get" action="{{ request()->url() }}" class="crm-option-search" role="search" aria-label="{{ $t('البحث عن مستلم', 'Find a transfer recipient') }}">
                    @foreach (request()->only(array_diff($crmQueryKeys, ['recipient_q', 'recipient_page'])) as $key => $value)
                        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <x-ui.field id="crm-transfer-recipient-search" :label="$t('ابحث عن مستلم', 'Search recipients')"><x-ui.search-input name="recipient_q" :value="request('recipient_q')" /></x-ui.field>
                    <x-ui.button type="submit" variant="secondary">{{ $t('بحث عن مستلم', 'Find recipient') }}</x-ui.button>
                </form>
                @if (count($recipients))
                    <form method="post" action="/crm/leads/{{ $lead['id'] }}/transfers?locale={{ $locale }}" class="crm-inline-form">
                        @csrf
                        <input type="hidden" name="version" value="{{ $lead['version'] }}">
                        <x-ui.field id="crm-transfer-recipient" :label="$t('المستلم', 'Recipient')" :error="$errors->first('recipient_id')">
                            <x-ui.select id="crm-transfer-recipient" name="recipient_id" required>
                                @if (filled(old('recipient_id')) && ! collect($recipients)->contains(fn ($recipient) => (string) $recipient['id'] === (string) old('recipient_id')))
                                    <option value="{{ old('recipient_id') }}" selected>{{ $t('المستخدم المحدد', 'Selected user') }} · <bdi>{{ old('recipient_id') }}</bdi></option>
                                @endif
                                <option value="">{{ $t('اختر مستخدماً', 'Choose a user') }}</option>
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient['id'] }}" @selected((string) old('recipient_id') === (string) $recipient['id'])>{{ $recipient['name'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field id="crm-transfer-context" :label="$t('سياق النقل', 'Transfer context')" :error="$errors->first('context')">
                            <x-ui.textarea id="crm-transfer-context" name="context" maxlength="10000" required>{{ old('context') }}</x-ui.textarea>
                        </x-ui.field>
                        <x-ui.button type="submit">{{ $t('إرسال طلب النقل', 'Send transfer request') }}</x-ui.button>
                    </form>
                @else
                    <x-ui.empty-state :title="$t('لا يوجد مستخدمون متاحون', 'No eligible users available')" />
                @endif
                @if (isset($recipientPaginator))
                    <x-ui.list-pagination :paginator="$recipientPaginator" :locale="$locale" :aria-label="$t('صفحات المستخدمين', 'User pages')" />
                @endif
            </div>
        </details>
    @endif

    @if (! $lead['archived'] && in_array('crm.manage', $permissions, true))
        <details class="ui-detail-panel crm-workflow-disclosure crm-workflow-disclosure--danger crm-archive-confirm">
            <summary class="ui-detail-panel__title crm-workflow-disclosure__summary">{{ $t('أرشفة السجل', 'Archive lead') }}</summary>
            <div class="crm-workflow-disclosure__content">
                    <p>{{ $t('لن تظهر السجلات المؤرشفة ضمن القائمة النشطة.', 'Archived leads are hidden from the active list.') }}</p>
                    <form method="post" action="/crm/leads/{{ $lead['id'] }}/archive?locale={{ $locale }}">
                        @csrf
                        <input type="hidden" name="version" value="{{ $lead['version'] }}">
                        <x-ui.button type="submit" variant="danger">{{ $t('تأكيد الأرشفة', 'Confirm archive') }}</x-ui.button>
                    </form>
            </div>
        </details>
    @endif


    </x-ui.modal>
    @if (! $lead['archived'] && $lead['customer_id'] === null && in_array('crm.convert', $permissions, true))
        <x-ui.modal id="crm-lead-convert" :title="$t('تحويل إلى عميل', 'Convert to customer')" :close-label="$t('إغلاق', 'Close')">
            <p>{{ $t('سيُنشأ سجل العميل من بيانات هذا السجل.', 'A customer record will be created from this lead.') }}</p>
            <form method="post" action="/crm/leads/{{ $lead['id'] }}/convert?locale={{ $locale }}">
                @csrf
                <input type="hidden" name="version" value="{{ $lead['version'] }}">
                <x-ui.button type="submit">{{ $t('تحويل إلى عميل', 'Convert to customer') }}</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
    @if (($errors->any() && ! $errors->has('reason')) || request()->hasAny(['outcome_q', 'outcome_page', 'recipient_q', 'recipient_page']))
        <script>document.addEventListener('DOMContentLoaded', () => document.querySelector('[data-open-dialog="crm-lead-actions"]')?.click());</script>
    @endif
    @if (request()->query('history') === '1')
        <script>document.addEventListener('DOMContentLoaded', () => document.querySelector('[data-open-dialog="crm-lead-history"]')?.click());</script>
    @endif
    @if (request()->has('page'))
        <script>document.addEventListener('DOMContentLoaded', () => document.querySelector('[data-open-dialog="crm-lead-activity-detail"]')?.click());</script>
    @endif
</div>
@endsection
