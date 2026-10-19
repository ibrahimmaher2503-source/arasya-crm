@php
    $activityIcons = ['call' => 'phone', 'message' => 'mail', 'meeting' => 'users', 'visit' => 'building', 'note' => 'audit', 'quotation' => 'audit'];
@endphp
<div class="crm-overview-board">
    <div class="crm-lead-overview-main">
        <div class="crm-overview-information">
            <x-ui.detail-panel id="crm-lead-summary" class="crm-overview-company" :title="$t('الشركة', 'Company')">
                <x-ui.icon name="building" class="crm-panel-symbol" />
                <dl class="crm-overview-facts">
                    @foreach ([
                        [$t('اسم الشركة', 'Company name'), $lead['organization'] ?: '—', 'building'],
                        [$t('الشخص المسؤول', 'Contact person'), $lead['name'], 'users'],
                        [$t('المصدر', 'Source'), $lead['source'], 'audit'],
                        [$t('المرحلة', 'Stage'), $stageLabel, 'grid'],
                        [$t('تاريخ الإضافة', 'Date added'), substr($lead['created_at'], 0, 10), 'clock'],
                    ] as [$label, $value, $icon])
                        <div><dt><x-ui.icon :name="$icon" />{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </x-ui.detail-panel>

            <x-ui.detail-panel id="crm-lead-contact" class="crm-overview-contact" :title="$t('بيانات التواصل', 'Contact details')">
                <x-ui.icon name="users" class="crm-panel-symbol" />
                <dl class="crm-overview-facts">
                    @foreach (['phone' => [$t('الهاتف الرئيسي', 'Primary phone'), 'phone'], 'email' => [$t('البريد الإلكتروني', 'Email'), 'mail'], 'whatsapp' => [$t('واتساب', 'WhatsApp'), 'phone']] as $channel => [$label, $icon])
                        <div><dt><x-ui.icon :name="$icon" />{{ $label }}</dt><dd><bdi dir="ltr">{{ is_array($revealedContacts) ? ($revealedContacts[$channel] ?? '—') : (filled($lead['contacts'][$channel] ?? null) ? $t('مخفي', 'Hidden') : '—') }}</bdi>@if (! is_array($revealedContacts) && filled($lead['contacts'][$channel] ?? null))<x-ui.icon name="lock" />@endif</dd></div>
                    @endforeach
                    <div><dt><x-ui.icon name="shield" />{{ $t('الموافقة', 'Consent') }}</dt><dd>{{ $lead['consent'] ? $t('مسجلة', 'Recorded') : $t('غير مسجلة', 'Not recorded') }}</dd></div>
                </dl>
                @if (in_array('contact.reveal', $permissions, true))
                    <details class="crm-contact-reveal" @if ($errors->has('reason')) open @endif>
                        <summary><x-ui.icon name="eye" />{{ $t('عرض بيانات التواصل', 'Reveal contact details') }}</summary>
                        <form method="post" action="/crm/leads/{{ $lead['id'] }}/reveal?locale={{ $locale }}" class="crm-inline-form">
                            @csrf
                            <x-ui.field id="crm-reveal-reason" :label="$t('سبب عرض بيانات التواصل', 'Reason for revealing contact details')" :error="$errors->first('reason')"><x-ui.input id="crm-reveal-reason" name="reason" maxlength="500" required /></x-ui.field>
                            <x-ui.button type="submit" variant="secondary">{{ $t('عرض بيانات التواصل', 'Reveal contact details') }}</x-ui.button>
                        </form>
                    </details>
                @endif
                @if (is_array($revealedContacts))
                    <p class="crm-contact-reveal-status" role="status">{{ $t('بيانات التواصل المعروضة', 'Revealed contact details') }}</p>
                @endif
            </x-ui.detail-panel>
        </div>

        <div class="crm-overview-middle">
            <x-ui.detail-panel class="crm-overview-needs" :title="$t('الملاحظات والتفضيلات', 'Notes and preferences')">
                <x-ui.icon name="help" class="crm-panel-symbol" />
                <p>{{ $notesContent }}</p>
                <div class="crm-overview-preferences">
                    @foreach (['email' => $t('البريد الإلكتروني', 'Email'), 'phone' => $t('الهاتف', 'Phone'), 'whatsapp' => $t('واتساب', 'WhatsApp')] as $channel => $label)
                        @if ($preferences[$channel] ?? false)<x-ui.status-badge status="info">{{ $label }}</x-ui.status-badge>@endif
                    @endforeach
                </div>
            </x-ui.detail-panel>
            <div class="crm-overview-stats">
                @foreach ([
                    [$t('الأنشطة', 'Activities'), $activities->total(), 'clock', 'activities', $t('عرض الأنشطة', 'View activities')],
                    [$t('المتابعات المرتبطة', 'Linked follow-ups'), $activitySummary['meta']['total'], 'shield', 'tasks', $t('عرض المتابعات', 'View follow-ups')],
                    [$t('الملاحظات', 'Notes'), $activitySummary['kind_counts']['note'] ?? 0, 'audit', 'notes', $t('عرض الملاحظات', 'View notes')],
                ] as [$label, $value, $icon, $sectionName, $linkLabel])
                    <div class="crm-overview-stat">
                        <span class="crm-overview-stat-label"><x-ui.icon :name="$icon" />{{ $label }}</span>
                        <x-ui.stat-card :label="$label" :value="$value" />
                        <a href="/crm/leads/{{ $lead['id'] }}/workspace/{{ $sectionName }}?locale={{ $locale }}">{{ $linkLabel }} <x-ui.icon name="arrow" /></a>
                    </div>
                @endforeach
            </div>
        </div>


    </div>

    <div class="crm-overview-aside">
        <x-ui.detail-panel id="crm-lead-activities" class="crm-overview-timeline" :title="$t('الخط الزمني للأنشطة', 'Activity timeline')">
            <x-ui.icon name="clock" class="crm-panel-symbol" />
            <a class="crm-overview-panel-action" href="/crm/leads/{{ $lead['id'] }}/workspace/activities?locale={{ $locale }}">{{ $t('جميع الأنشطة', 'All activities') }} <span aria-hidden="true">⌄</span></a>
            @if ($activities->isEmpty())
                <x-ui.empty-state :title="$t('لا توجد أنشطة بعد', 'No activity yet')" :description="$t('سجّل أول نشاط على هذا العميل المحتمل.', 'Record the first activity for this lead.')" />
            @else
                <ol class="crm-overview-events" aria-label="{{ $t('النشاطات مرتبة من الأحدث إلى الأقدم', 'Activities from newest to oldest') }}">
                    @foreach ($activities->take(6) as $activity)
                        <li class="crm-overview-event crm-overview-event--{{ $activity['kind'] }}">
                            <span class="crm-event-icon"><x-ui.icon :name="$activityIcons[$activity['kind']] ?? 'audit'" /></span>
                            <div class="crm-event-copy"><strong>{{ $activityKindLabels[$activity['kind']] ?? $activity['kind'] }}</strong><p>{{ $activity['next_step'] }}</p></div>
                            <time datetime="{{ $activity['occurred_at'] }}"><bdi>{{ substr($activity['occurred_at'], 0, 10) }}</bdi><bdi>{{ substr($activity['occurred_at'], 11, 5) }}</bdi></time>
                        </li>
                    @endforeach
                </ol>
                <x-ui.button variant="ghost" class="crm-overview-all-activities" data-open-dialog="crm-lead-activity-detail">{{ $t('عرض كل الأنشطة', 'View all activities') }} <x-ui.icon name="arrow" /></x-ui.button>
            @endif
        </x-ui.detail-panel>


    </div>
</div>

<x-ui.modal id="crm-lead-activity-detail" :title="$t('كل الأنشطة', 'All activities')" :close-label="$t('إغلاق', 'Close')">
    <ol class="crm-timeline" aria-label="{{ $t('النشاطات مرتبة من الأحدث إلى الأقدم', 'Activities from newest to oldest') }}">
        @foreach ($activities as $activity)
            <li><div class="crm-timeline__entry">
                <div class="crm-timeline__header"><strong>{{ $activityKindLabels[$activity['kind']] ?? $activity['kind'] }}</strong><time datetime="{{ $activity['occurred_at'] }}" dir="ltr">{{ $activity['occurred_at'] }}</time></div>
                <p class="crm-timeline__outcome">{{ $activity['outcome_label'] }}</p>
                <dl class="crm-timeline__facts"><div><dt>{{ $t('الخطوة التالية', 'Next step') }}</dt><dd>{{ $activity['next_step'] }}</dd></div>
                    @if (filled($activity['next_follow_up'] ?? null))<div><dt>{{ $t('المتابعة', 'Follow-up') }}</dt><dd><time datetime="{{ $activity['next_follow_up'] }}" dir="ltr">{{ $activity['next_follow_up'] }}</time></dd></div>@endif
                </dl>
                @if (filled($activity['note'] ?? null))<p class="crm-timeline__note"><span class="ui-muted">{{ $t('ملاحظة', 'Note') }}:</span> {{ $activity['note'] }}</p>@endif
            </div></li>
        @endforeach
    </ol>
    <x-ui.list-pagination :paginator="$activities" :locale="$locale" :aria-label="$t('صفحات الأنشطة', 'Activity pages')" />
</x-ui.modal>

<x-ui.modal id="crm-lead-history" :title="$t('سجل التغييرات', 'Change history')" :close-label="$t('إغلاق', 'Close')">
    @if (count($historyEntries))<x-erp.audit-history :entries="$historyEntries" />@else<x-ui.empty-state :title="$t('لا توجد تغييرات مسجلة', 'No changes recorded')" />@endif
</x-ui.modal>
