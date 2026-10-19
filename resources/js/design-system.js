(() => {
    const dialogOpeners = new WeakMap();
    const tooltipTriggers = new WeakMap();
    const root = document.documentElement;
    document.querySelectorAll('[data-testid="theme-toggle"], [data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(root.dataset.theme === 'dark'));
    });

    const closestElement = (target, selector) => {
        if (!(target instanceof Element)) {
            return null;
        }

        return target.closest(selector);
    };

    const dialogFor = (id) => {
        if (!id) {
            return null;
        }

        const dialog = document.getElementById(id);

        return dialog instanceof HTMLDialogElement ? dialog : null;
    };

    const restoreDialogFocus = (dialog) => {
        const opener = dialogOpeners.get(dialog);
        dialogOpeners.delete(dialog);

        if (opener instanceof HTMLElement && opener.isConnected) {
            opener.focus();
        }
    };

    const cycleDialogFocusAtTabBoundary = (event, dialog) => {
        const focusableElements = Array.from(dialog.querySelectorAll(
            'a[href], area[href], button, input:not([type="hidden"]), select, textarea, iframe, object, embed, [contenteditable]:not([contenteditable="false"]), [tabindex], summary, audio[controls], video[controls]'
        )).filter((element) => {
            if (!(element instanceof HTMLElement)
                || element.tabIndex < 0
                || element.matches(':disabled')
                || element.closest('[hidden], [inert], [aria-hidden="true"]')) {
                return false;
            }

            const style = window.getComputedStyle(element);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.visibility !== 'collapse'
                && element.getClientRects().length > 0;
        });

        if (focusableElements.length === 0) {
            event.preventDefault();
            dialog.focus();

            return true;
        }

        const currentIndex = focusableElements.indexOf(document.activeElement);

        if (event.shiftKey && currentIndex <= 0) {
            event.preventDefault();
            focusableElements[focusableElements.length - 1].focus();

            return true;
        }

        if (!event.shiftKey && (currentIndex < 0 || currentIndex === focusableElements.length - 1)) {
            event.preventDefault();
            focusableElements[0].focus();

            return true;
        }

        return false;
    };

    const activateTab = (tabsRoot, selectedTab, focus = false) => {
        const tabs = Array.from(tabsRoot.querySelectorAll('[role="tab"]'))
            .filter((tab) => tab.closest('[data-tabs]') === tabsRoot);

        if (!tabs.includes(selectedTab)) {
            return;
        }

        const selectedPanelId = selectedTab.getAttribute('aria-controls');
        const selectedPanel = selectedPanelId ? document.getElementById(selectedPanelId) : null;

        if (!(selectedPanel instanceof HTMLElement) || !tabsRoot.contains(selectedPanel)) {
            return;
        }

        tabs.forEach((tab) => {
            const isSelected = tab === selectedTab;
            const panelId = tab.getAttribute('aria-controls');
            const panel = panelId ? document.getElementById(panelId) : null;

            tab.setAttribute('aria-selected', String(isSelected));
            tab.tabIndex = isSelected ? 0 : -1;

            if (panel instanceof HTMLElement && tabsRoot.contains(panel)) {
                panel.hidden = !isSelected;
            }
        });

        if (focus) {
            selectedTab.focus();
        }
    };

    const positionTooltip = (trigger) => {
        const tooltipRoot = trigger.closest('[data-ui-tooltip]');
        const tooltip = tooltipRoot?.querySelector('[role="tooltip"]');

        if (!(tooltip instanceof HTMLElement)) {
            return;
        }

        const triggerBox = trigger.getBoundingClientRect();
        const tooltipBox = tooltip.getBoundingClientRect();
        const gap = 8;
        const padding = 12;
        const centeredLeft = triggerBox.left + (triggerBox.width - tooltipBox.width) / 2;
        const left = Math.max(padding, Math.min(centeredLeft, window.innerWidth - tooltipBox.width - padding));
        const above = triggerBox.top - tooltipBox.height - gap;
        const top = above >= padding
            ? above
            : Math.min(triggerBox.bottom + gap, window.innerHeight - tooltipBox.height - padding);

        tooltip.style.left = left + 'px';
        tooltip.style.top = Math.max(padding, top) + 'px';
        tooltipRoot.setAttribute('data-positioned', 'true');
        tooltipTriggers.set(tooltipRoot, trigger);
    };

    const repositionVisibleTooltips = () => {
        document.querySelectorAll('[data-ui-tooltip]:hover, [data-ui-tooltip]:focus-within').forEach((tooltipRoot) => {
            const trigger = tooltipTriggers.get(tooltipRoot) || tooltipRoot.querySelector('.ui-tooltip__trigger');

            if (trigger instanceof HTMLElement) {
                positionTooltip(trigger);
            }
        });
    };

    document.addEventListener('click', (event) => {
        const passwordToggle = closestElement(event.target, '[data-password-toggle]');
        if (passwordToggle instanceof HTMLButtonElement) {
            const input = document.getElementById(passwordToggle.dataset.passwordToggle);
            if (input instanceof HTMLInputElement && ['password', 'text'].includes(input.type)) {
                const revealed = input.type === 'password';
                input.type = revealed ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', String(revealed));
                passwordToggle.setAttribute('aria-label', revealed ? passwordToggle.dataset.hideLabel : passwordToggle.dataset.showLabel);
            }
            return;
        }
        const opener = closestElement(event.target, '[data-open-dialog]');

        if (opener) {
            const dialog = dialogFor(opener.getAttribute('data-open-dialog'));

            if (dialog && opener.hasAttribute('data-edit-record')) {
                const form = dialog.querySelector('[data-record-form]');
                try {
                    const record = JSON.parse(opener.dataset.record || '{}');
                    const action = new URL(opener.dataset.formAction || '', window.location.href);
                    if (form instanceof HTMLFormElement && action.origin === window.location.origin && action.pathname.startsWith('/access/')) {
                        form.reset();
                        form.action = action.href;
                        form.querySelectorAll('[data-preserved-role]').forEach(element => element.remove());
                        Array.from(form.elements).forEach(input => {
                            if (!input.name || input.name === '_token' || !Object.hasOwn(record, input.name.replace(/\[\]$/, ''))) return;
                            const value = record[input.name.replace(/\[\]$/, '')];
                            if (input instanceof HTMLInputElement && input.type === 'checkbox') {
                                input.checked = Array.isArray(value) ? value.includes(input.value) : Boolean(value);
                            } else if (input instanceof HTMLSelectElement && input.multiple && Array.isArray(value)) {
                                Array.from(input.options).forEach(option => { option.selected = value.includes(option.value); });
                            } else if (input instanceof HTMLSelectElement) {
                                if (value && !Array.from(input.options).some(option => option.value === String(value))) {
                                    const option = document.createElement('option');
                                    option.value = String(value);
                                    option.textContent = record[input.name.replace(/_id$/, '_name')] || String(value);
                                    input.append(option);
                                }
                                input.value = value ?? '';
                            } else if (!(input instanceof HTMLInputElement && input.type === 'hidden' && input.name === 'active')) {
                                input.value = value ?? '';
                            }
                        });
                        const preservedRoles = form.querySelector('[data-preserved-role-values]');
                        if (preservedRoles && Array.isArray(record.role_ids)) {
                            const displayed = Array.from(form.querySelectorAll('[name="role_ids[]"]')).flatMap(input => input instanceof HTMLSelectElement ? Array.from(input.options).map(option => option.value) : [input.value]);
                            record.role_ids.filter(id => !displayed.includes(id)).forEach(id => {
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden'; hidden.name = 'role_ids[]'; hidden.value = id; hidden.dataset.preservedRole = '';
                                preservedRoles.append(hidden);
                            });
                        }
                    }
                } catch { return; }
            }

            if (dialog && opener.hasAttribute('data-audit-detail')) {
                const detail = dialog.querySelector('[data-audit-content]');
                if (detail) {
                    try { detail.textContent = JSON.stringify(JSON.parse(opener.dataset.auditDetail || '{}'), null, 2); }
                    catch { detail.textContent = ''; }
                }
            }

            if (dialog && !dialog.open && typeof dialog.showModal === 'function') {
                dialogOpeners.set(dialog, opener);
                dialog.addEventListener('close', () => restoreDialogFocus(dialog), { once: true });

                try {
                    if (dialog.hasAttribute('data-docked-drawer') && window.matchMedia('(min-width: 1250px)').matches) {
                        dialog.show();
                        dialog.querySelector('input:not([type="hidden"] )')?.focus();
                    } else {
                        dialog.showModal();
                    }
                } catch {
                    dialogOpeners.delete(dialog);
                }
            }

            return;
        }

        const closer = closestElement(event.target, '[data-close-dialog]');

        if (closer) {
            const dialog = closer.closest('dialog');

            if (dialog instanceof HTMLDialogElement && dialog.open) {
                dialog.close();
            }

            return;
        }

        const toastDismiss = closestElement(event.target, '[data-dismiss-toast]');

        if (toastDismiss) {
            toastDismiss.closest('[data-ui-toast]')?.remove();
            return;
        }

        const tab = closestElement(event.target, '[data-tabs] [role="tab"]');

        if (tab) {
            const tabsRoot = tab.closest('[data-tabs]');

            if (tabsRoot) {
                activateTab(tabsRoot, tab);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const docked = document.querySelector('[data-docked-drawer][open]:not(:modal)');
            if (docked instanceof HTMLDialogElement) docked.close();
        }
        if (event.key === 'Tab') {
            const openDialog = document.querySelector('dialog.ui-dialog[open]');

            if (openDialog instanceof HTMLDialogElement && cycleDialogFocusAtTabBoundary(event, openDialog)) {
                return;
            }
        }

        const tab = closestElement(event.target, '[data-tabs] [role="tab"]');

        if (!tab) {
            return;
        }

        const tabsRoot = tab.closest('[data-tabs]');

        if (!tabsRoot) {
            return;
        }

        const tabs = Array.from(tabsRoot.querySelectorAll('[role="tab"]'))
            .filter((candidate) => candidate.closest('[data-tabs]') === tabsRoot);
        const currentIndex = tabs.indexOf(tab);

        if (currentIndex < 0 || tabs.length === 0) {
            return;
        }

        let nextIndex = currentIndex;
        const direction = window.getComputedStyle(tab).direction;

        if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = tabs.length - 1;
        } else if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + (direction === 'rtl' ? -1 : 1) + tabs.length) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = (currentIndex + (direction === 'rtl' ? 1 : -1) + tabs.length) % tabs.length;
        } else {
            return;
        }

        event.preventDefault();
        activateTab(tabsRoot, tabs[nextIndex], true);
    });

    document.addEventListener('click', (event) => {
        const focusButton = closestElement(event.target, '[data-focus-form]');
        if (focusButton) {
            const form = document.getElementById(focusButton.dataset.focusForm);
            form?.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
        }
        const selection = closestElement(event.target, '[data-permission-select]');
        if (selection) {
            const group = selection.closest('[data-permission-group]');
            group?.querySelectorAll('[data-permission-option]:not([hidden]) input[type="checkbox"]:not(:disabled)').forEach(input => { input.checked = selection.dataset.permissionSelect === 'all'; });
            updatePermissionCount(selection.closest('[data-permission-groups]'));
        }
        const themeToggle = closestElement(event.target, '[data-testid="theme-toggle"], [data-theme-toggle]');

        if (themeToggle) {
            const nextTheme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', nextTheme);
            themeToggle.setAttribute('aria-pressed', String(nextTheme === 'dark'));
            return;
        }

        const directionToggle = closestElement(event.target, '[data-testid="direction-toggle"]');

        if (directionToggle) {
            const nextDirection = root.getAttribute('dir') === 'rtl' ? 'ltr' : 'rtl';
            root.setAttribute('dir', nextDirection);
            root.setAttribute('lang', nextDirection === 'rtl' ? 'ar' : 'en');
            directionToggle.setAttribute('aria-pressed', String(nextDirection === 'rtl'));
            return;
        }

        const navToggle = closestElement(event.target, '[data-nav-toggle]');

        if (navToggle) {
            const nav = document.getElementById(navToggle.getAttribute('aria-controls'));

            if (nav instanceof HTMLElement) {
                nav.hidden = !nav.hidden;
                navToggle.setAttribute('aria-expanded', String(!nav.hidden));
            }
        }
    });

    const updatePermissionCount = (container) => {
        const count = container?.querySelector('[data-permission-count]');
        if (!container) return;
        const selected = container.querySelectorAll('input[type="checkbox"]:checked').length;
        if (count) count.textContent = String(selected);
        container.closest('form')?.querySelectorAll('[data-permission-total]').forEach(total => { total.textContent = String(selected); });
        container.querySelectorAll('[data-permission-group]').forEach(group => {
            group.querySelector('[data-permission-state]')?.classList.toggle('is-selected', Boolean(group.querySelector('input[type="checkbox"]:checked')));
        });
    };
    document.querySelectorAll('[data-permission-groups]').forEach(updatePermissionCount);
    document.addEventListener('reset', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        setTimeout(() => form.querySelectorAll('[data-permission-groups]').forEach(updatePermissionCount), 0);
    });
    document.addEventListener('change', event => {
        const container = closestElement(event.target, '[data-permission-groups]');
        if (container) updatePermissionCount(container);
    });
    document.addEventListener('input', event => {
        const search = closestElement(event.target, '[data-permission-search]');
        if (!search) return;
        const query = search.value.trim().toLocaleLowerCase();
        search.closest('[data-permission-groups]').querySelectorAll('[data-permission-group]').forEach(group => {
            const groupMatch = group.querySelector('summary').textContent.toLocaleLowerCase().includes(query);
            group.querySelectorAll('[data-permission-option]').forEach(option => { option.hidden = !groupMatch && !option.textContent.toLocaleLowerCase().includes(query); });
            group.hidden = !group.querySelector('[data-permission-option]:not([hidden])');
            if (query && !group.hidden) group.open = true;
        });
    });

    document.addEventListener('pointerover', (event) => {
        const trigger = closestElement(event.target, '[data-ui-tooltip] .ui-tooltip__trigger');

        if (trigger instanceof HTMLElement) {
            positionTooltip(trigger);
        }
    });

    document.addEventListener('focusin', (event) => {
        const trigger = closestElement(event.target, '[data-ui-tooltip] .ui-tooltip__trigger');

        if (trigger instanceof HTMLElement) {
            positionTooltip(trigger);
        }
    });

    window.addEventListener('resize', repositionVisibleTooltips);
    window.addEventListener('scroll', repositionVisibleTooltips, true);
})();

