@once
<script>
(() => {
    const load = async (picker, url, trigger) => {
        if (picker.getAttribute('aria-busy') === 'true') return;
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        const error = picker.querySelector('[data-crm-outcome-error]');
        error.hidden = true;
        picker.setAttribute('aria-busy', 'true');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' }, signal: controller.signal });
            if (!response.ok) throw new Error('Choice lookup failed');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const name = picker.dataset.crmOptionName;
            const next = document.querySelector(`[data-crm-outcome-picker][data-crm-option-name="${name}"]`);
            const select = picker.querySelector(`[name="${name}"]`);
            const nextSelect = next?.querySelector(`[name="${name}"]`);
            const nextPages = next?.querySelector('[data-crm-outcome-pages]');
            if (!select || !nextSelect || !nextPages) throw new Error('Choice lookup unavailable');
            const value = select.value;
            const selected = select.selectedOptions[0]?.cloneNode(true);
            select.replaceChildren(...Array.from(nextSelect.options));
            if (value && selected && !Array.from(select.options).some(option => option.value === value)) {
                select.append(selected);
            }
            select.value = value;
            picker.querySelector('[data-crm-outcome-pages]').replaceChildren(...Array.from(nextPages.childNodes));
            if (trigger.isConnected) trigger.focus();
            else select.focus();
        } catch {
            error.hidden = false;
        } finally {
            clearTimeout(timeout);
            picker.removeAttribute('aria-busy');
        }
    };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-crm-outcome-search]')) return;
        event.preventDefault();
        const url = new URL(form.action);
        new FormData(form).forEach((value, key) => { if (typeof value === 'string') url.searchParams.set(key, value); });
        load(form.closest('[data-crm-outcome-picker]'), url, form.querySelector('button'));
    });
    document.addEventListener('click', event => {
        const link = event.target instanceof Element ? event.target.closest('[data-crm-outcome-pages] a') : null;
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        load(link.closest('[data-crm-outcome-picker]'), new URL(link.href), link);
    });
    document.addEventListener('DOMContentLoaded', () => {
        const query = new URL(location.href).searchParams;
        if (!['outcome_q', 'outcome_page', 'lead_q', 'lead_page'].some(key => query.has(key))) return;
        const picker = document.querySelector('[data-crm-outcome-picker]');
        if (picker) document.querySelector(`[data-open-dialog="${picker.dataset.crmPickerDialog}"]`)?.click();
    });
})();
</script>
@endonce
