<form method="post" action="/crm/leads?locale={{ $locale }}" class="ui-stack">
 @csrf
 <x-ui.field id="crm-quick-name" :label="$t('الاسم الكامل','Full name')"><x-ui.input id="crm-quick-name" name="name" maxlength="200" required :placeholder="$t('أدخل الاسم الكامل للعميل المحتمل','Enter the lead name')" /></x-ui.field>
 <x-ui.field id="crm-quick-company" :label="$t('الشركة','Company')"><x-ui.input id="crm-quick-company" name="organization" maxlength="200" required :placeholder="$t('أدخل اسم الشركة','Enter the company name')" /></x-ui.field>
 <x-ui.field id="crm-quick-phone" :label="$t('الهاتف','Phone')"><x-ui.input id="crm-quick-phone" name="contacts[phone]" type="tel" maxlength="64" dir="ltr" placeholder="+966 50 123 4567" /></x-ui.field>
 <x-ui.field id="crm-quick-email" :label="$t('البريد الإلكتروني','Email')"><x-ui.input id="crm-quick-email" name="contacts[email]" type="email" maxlength="254" dir="ltr" placeholder="example@company.com" /></x-ui.field>
 <x-ui.field id="crm-quick-source" :label="$t('المصدر','Source')"><x-ui.input id="crm-quick-source" name="source" maxlength="60" required list="crm-source-options" :placeholder="$t('اختر المصدر أو اكتب مصدرًا','Choose or enter a source')" /><datalist id="crm-source-options">@foreach ($sources as $source)<option value="{{ $source }}">@endforeach</datalist></x-ui.field>
 <x-ui.field id="crm-quick-stage" :label="$t('المرحلة','Stage')"><x-ui.select id="crm-quick-stage" name="stage_id"><option value="">{{ $t('اختر المرحلة','Choose a stage') }}</option>@foreach ($stages as $stage)<option value="{{ $stage['id'] }}">{{ $stage['label'] }}</option>@endforeach</x-ui.select></x-ui.field>
 <x-ui.field id="crm-quick-priority" :label="$t('الأولوية','Priority')"><x-ui.select id="crm-quick-priority" name="priority">@foreach (['normal','high','low'] as $priority)<option value="{{ $priority }}">{{ $priorityLabels[$priority] }}</option>@endforeach</x-ui.select></x-ui.field>
 <x-ui.field id="crm-quick-notes" :label="$t('ملاحظات','Notes')"><x-ui.textarea id="crm-quick-notes" name="notes" maxlength="4000" rows="2" /></x-ui.field>
 <x-ui.form-actions><x-ui.button type="submit">{{ $t('حفظ العميل المحتمل','Save lead') }}</x-ui.button><x-ui.button type="button" variant="secondary" data-close-dialog>{{ $t('إلغاء','Cancel') }}</x-ui.button></x-ui.form-actions>
 <a href="/crm/leads/create?locale={{ $locale }}">{{ $t('فتح النموذج الكامل','Open full form') }}</a>
</form>
