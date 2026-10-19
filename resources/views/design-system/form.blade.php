<form class="ui-stack" action="/dev/design-system" method="get">
    <input type="hidden" name="screen" value="form"><input type="hidden" name="locale" value="{{ $locale }}">
    <x-ui.form-section :title="$t('بيانات الطلب', 'Order information')" :description="$t('نموذج مكونات فقط؛ لا يُحفظ طلب أعمال.', 'Component demonstration only. No business order is saved.')">
        <div class="ui-grid">
            <x-ui.field id="customer-name" :label="$t('اسم العميل', 'Customer name')" :hint="$t('اسم تركيبي للمعاينة', 'Synthetic preview name')"><x-ui.input name="customer" required autocomplete="off" :value="$t('شركة النصر للتجارة', 'Al Nasr Trading')" /></x-ui.field>
            <x-ui.field id="order-date" :label="$t('تاريخ الطلب', 'Order date')"><x-ui.date-input name="date" value="2026-10-17" /></x-ui.field>
            <x-ui.field id="order-quantity" :label="$t('الكمية', 'Quantity')"><x-ui.number-input name="quantity" min="1" value="10" /></x-ui.field>
            <x-ui.field id="order-amount" :label="$t('المبلغ', 'Amount')"><x-ui.currency-input name="amount" value="1250.50" min="0" /></x-ui.field>
            <x-ui.field id="order-assignees" :label="$t('المسؤولون', 'Assignees')" :hint="$t('اختر مسؤولاً أو أكثر', 'Choose one or more assignees')"><x-ui.multi-select name="assignees[]"><option>{{ $t('أحمد محمد', 'Ahmed Mohamed') }}</option><option>{{ $t('سارة علي', 'Sara Ali') }}</option></x-ui.multi-select></x-ui.field>
            <x-ui.field id="order-email" :label="$t('البريد الإلكتروني', 'Email')" :error="$t('أدخل بريداً صالحاً للمتابعة.', 'Enter a valid email to continue.')"><x-ui.input name="email" type="email" value="invalid" /></x-ui.field>
        </div>
        <x-ui.field id="order-notes" :label="$t('ملاحظات', 'Notes')"><x-ui.textarea name="notes" rows="3" :value="$t('مثال للنصوص العربية وحقول الإدخال.', 'Sample input content.')" /></x-ui.field>
        <div class="ui-inline">
            <x-ui.field id="order-notify" :label="$t('إرسال إشعار', 'Send notification')"><x-ui.checkbox name="notify" /></x-ui.field>
            <x-ui.field id="order-priority" :label="$t('أولوية عادية', 'Normal priority')"><x-ui.radio name="priority" value="normal" checked /></x-ui.field>
            <x-ui.switch id="order-active" name="active" :label="$t('نشط', 'Active')" checked />
        </div>
    </x-ui.form-section>
    <x-ui.form-actions>
        <x-ui.button data-open-dialog="demo-modal">{{ $t('معاينة الطلب', 'Preview order') }}</x-ui.button>
        <x-ui.button variant="secondary" type="reset">{{ $t('إعادة تعيين', 'Reset') }}</x-ui.button>
        <x-ui.button disabled>{{ $t('الحفظ غير متاح في العرض', 'Saving unavailable in preview') }}</x-ui.button>
    </x-ui.form-actions>
</form>
