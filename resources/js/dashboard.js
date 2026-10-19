(() => {
    const shell = document.querySelector('[data-dashboard-shell]');
    if (!shell) return;

    const collapse = shell.querySelector('[data-sidebar-collapse]');
    collapse?.addEventListener('click', () => {
        const compact = shell.classList.toggle('is-sidebar-compact');
        collapse.setAttribute('aria-expanded', String(!compact));
    });
    shell.querySelectorAll('.nav-group > summary').forEach(summary => {
        summary.setAttribute('title', summary.textContent.trim());
        summary.addEventListener('click', () => {
            shell.classList.remove('is-sidebar-compact');
            collapse?.setAttribute('aria-expanded', 'true');
        });
    });
    const navToggles = [...shell.querySelectorAll('[data-nav-toggle]')];
    const mobileToggle = shell.querySelector('.mobile-menu');
    const navigation = shell.querySelector('#primary-navigation');
    const closeNavigation = (restoreFocus = false) => {
        shell.classList.remove('is-nav-open');
        navToggles.forEach((button) => button.setAttribute('aria-expanded', 'false'));
        if (restoreFocus) mobileToggle?.focus();
    };
    const openNavigation = () => {
        shell.classList.add('is-nav-open');
        navToggles.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        navigation?.querySelector('a:not([hidden])')?.focus();
    };

    navToggles.forEach((button) => button.addEventListener('click', () => {
        if (button.classList.contains('sidebar-close')) closeNavigation();
        else if (shell.classList.contains('is-nav-open')) closeNavigation();
        else openNavigation();
    }));

    const navItems = [...shell.querySelectorAll('[data-nav-item]')];
    const searchInputs = [...shell.querySelectorAll('[data-nav-search]')];
    const searchEmpty = shell.querySelector('[data-search-empty]');
    const normalize = (value) => value.normalize('NFKC').toLocaleLowerCase().replace(/[\u064B-\u065F\u0670]/g, '');
    searchInputs.forEach((input) => input.addEventListener('input', () => {
        const query = normalize(input.value.trim());
        searchInputs.forEach((other) => { if (other !== input) other.value = input.value; });
        let visible = 0;
        navItems.forEach((item) => {
            const matches = normalize(item.dataset.navText || item.textContent).includes(query);
            item.hidden = query !== '' && !matches;
            const group = item.querySelector('.nav-group');
            if (group) {
                if (query && group.dataset.searchOpen === undefined) group.dataset.searchOpen = String(group.open);
                if (query && matches) group.open = true;
                if (!query && group.dataset.searchOpen !== undefined) {
                    group.open = group.dataset.searchOpen === 'true';
                    delete group.dataset.searchOpen;
                }
            }
            if (!item.hidden) visible++;
        });
        if (searchEmpty) {
            searchEmpty.hidden = query === '' || visible > 0;
            searchEmpty.setAttribute('role', 'status');
        }
    }));

    shell.querySelector('[data-theme-toggle]')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        const dark = document.documentElement.dataset.theme !== 'dark';
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        button.setAttribute('aria-pressed', String(dark));
    });

    const closePopovers = (except = null) => {
        shell.querySelectorAll('[data-popover]').forEach((popover) => {
            if (popover === except) return;
            popover.hidden = true;
            shell.querySelector(`[aria-controls="${popover.id}"]`)?.setAttribute('aria-expanded', 'false');
        });
    };
    shell.querySelectorAll('[data-popover-trigger]').forEach((button) => button.addEventListener('click', () => {
        const popover = document.getElementById(button.dataset.popoverTrigger);
        if (!popover) return;
        const opening = popover.hidden;
        closePopovers(popover);
        popover.hidden = !opening;
        button.setAttribute('aria-expanded', String(opening));
    }));
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.popover-wrap')) closePopovers();
        if (shell.classList.contains('is-nav-open') && event.target === shell) closeNavigation();
    });

    const donutPeriod = shell.querySelector('[data-donut-period]');
    const updateDonut = () => {
        const period = donutPeriod?.value === 'previous' ? 'previous' : 'current';
        const total = shell.querySelector('[data-donut-total]');
        if (total) total.textContent = period === 'previous' ? '1.18M' : '1.25M';
        let offset = 0;
        const segments = [...shell.querySelectorAll('.donut-segment')];
        shell.querySelectorAll('[data-donut-share]').forEach((share, index) => {
            share.textContent = share.dataset[period] || share.dataset.current;
            const percentage = Number.parseFloat(share.textContent);
            segments[index]?.setAttribute('stroke-dasharray', `${percentage - 1} ${101 - percentage}`);
            segments[index]?.setAttribute('stroke-dashoffset', String(-offset));
            offset += percentage;
        });
    };
    donutPeriod?.addEventListener('change', updateDonut);

    const linePeriod = shell.querySelector('[data-line-period]');
    const lineChart = shell.querySelector('.line-chart');
    const updateLinePeriod = () => {
        const period = linePeriod?.value === 'previous' ? 'previous' : 'current';
        lineChart?.querySelectorAll('[data-period-graphic]').forEach((graphic) => {
            graphic.toggleAttribute('hidden', graphic.dataset.periodGraphic !== period);
        });
    };
    linePeriod?.addEventListener('change', updateLinePeriod);

    const updateSeries = (series) => {
        shell.querySelectorAll('[data-series-choice]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.seriesChoice === series));
        });
        lineChart?.querySelectorAll('[data-series-graphic]').forEach((graphic) => {
            graphic.toggleAttribute('hidden', graphic.dataset.seriesGraphic !== series);
        });
    };
    shell.querySelectorAll('[data-series-choice]').forEach((button) => button.addEventListener('click', () => updateSeries(button.dataset.seriesChoice)));
    updateLinePeriod();
    updateSeries('sales');

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            (searchInputs.find((input) => input.closest('.top-search')) || searchInputs[0])?.focus();
        }
        if (event.key === 'Escape') {
            closePopovers();
            if (shell.classList.contains('is-nav-open')) closeNavigation(true);
        }
    });
})();

