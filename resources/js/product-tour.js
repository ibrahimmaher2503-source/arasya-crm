(() => {
    'use strict';

    const roots = document.querySelectorAll('[data-product-tour]');

    roots.forEach((root) => {
        const configNode = root.querySelector('[data-product-tour-config]');
        const startButton = root.querySelector('[data-tour-start]');
        const restartButton = root.querySelector('[data-tour-restart]');
        const statusNode = root.querySelector('[data-tour-status]');
        const messageNode = root.querySelector('[data-tour-message]');
        const popover = root.querySelector('[data-tour-popover]');
        const titleNode = root.querySelector('[data-tour-title]');
        const bodyNode = root.querySelector('[data-tour-body]');
        const countNode = root.querySelector('[data-tour-step-count]');
        const previousButton = root.querySelector('[data-tour-previous]');
        const nextButton = root.querySelector('[data-tour-next]');
        const skipButton = root.querySelector('[data-tour-skip]');
        const pauseButton = root.querySelector('[data-tour-pause]');
        const closeButton = root.querySelector('[data-tour-close]');
        const spotlight = root.querySelector('[data-tour-spotlight]');
        const shades = {
            top: root.querySelector('[data-tour-shade="top"]'),
            right: root.querySelector('[data-tour-shade="right"]'),
            bottom: root.querySelector('[data-tour-shade="bottom"]'),
            left: root.querySelector('[data-tour-shade="left"]'),
        };

        let config;
        try {
            config = JSON.parse(configNode?.textContent ?? '');
        } catch {
            return;
        }

        if (!config
            || !/^[a-z0-9-]{1,80}$/.test(config.tour_id)
            || !Number.isInteger(config.version)
            || !Array.isArray(config.steps)
            || config.steps.length < 1
            || config.steps.length > 12
            || config.steps.some((step) => !/^[a-z][a-z0-9-]{0,79}$/.test(step.key)
                || !/^[a-z][a-z0-9.-]{0,119}$/.test(step.anchor)
                || typeof step.title !== 'string'
                || typeof step.body !== 'string')) {
            return;
        }

        const state = {
            active: false,
            index: 0,
            currentStep: null,
            target: null,
            hadSkipped: false,
            trigger: null,
            progress: null,
            raf: 0,
        };

        const statusLabels = config.messages?.status ?? {};
        const label = (key) => statusLabels[key] ?? statusLabels.not_started ?? '';
        const announce = (message) => {
            messageNode.textContent = message;
        };
        const updateStatus = (progress) => {
            state.progress = progress;
            statusNode.textContent = label(progress?.status ?? 'not_started');
            startButton.textContent = ['paused', 'in_progress'].includes(progress?.status)
                ? config.messages.resume
                : config.messages.start;
        };
        const endpoint = `/help/api/progress/${encodeURIComponent(config.tour_id)}`;

        const request = async (method, payload, keepalive = false) => {
            const options = {
                method,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            };
            if (keepalive) options.keepalive = true;
            if (method === 'PATCH') {
                options.headers['Content-Type'] = 'application/json';
                options.headers['X-CSRF-TOKEN'] = config.csrf_token;
                options.body = JSON.stringify(payload);
            }

            const response = await fetch(endpoint, options);
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.data) throw new Error(result?.error?.code ?? 'progress_unavailable');
            return result.data;
        };

        const saveProgress = async (step, status, keepalive = false) => {
            if (!step) return false;
            try {
                const progress = await request('PATCH', {
                    version: config.version,
                    step_key: step.key,
                    status,
                }, keepalive);
                updateStatus(progress);
                return true;
            } catch {
                announce(config.messages.save_failed);
                return false;
            }
        };

        const readProgress = async () => {
            try {
                const progress = await request('GET');
                updateStatus(progress);
                return progress;
            } catch {
                statusNode.textContent = config.messages.status_failed;
                announce(config.messages.status_failed);
                return null;
            }
        };

        const findTarget = (anchor) => {
            const candidates = document.querySelectorAll('[data-tour]');
            for (const candidate of candidates) {
                if (candidate.dataset.tour !== anchor || !candidate.isConnected) continue;
                if (candidate.closest('[hidden], [aria-hidden="true"]')) continue;
                const style = window.getComputedStyle(candidate);
                const rect = candidate.getBoundingClientRect();
                if (style.display === 'none' || style.visibility === 'hidden' || rect.width < 1 || rect.height < 1) continue;
                return candidate;
            }
            return null;
        };

        const setRect = (element, left, top, width, height) => {
            element.style.left = `${Math.max(0, left)}px`;
            element.style.top = `${Math.max(0, top)}px`;
            element.style.width = `${Math.max(0, width)}px`;
            element.style.height = `${Math.max(0, height)}px`;
        };

        const positionTour = () => {
            if (!state.active || !state.target?.isConnected) return;
            const rect = state.target.getBoundingClientRect();
            const viewport = window.visualViewport;
            const viewportWidth = viewport?.width ?? document.documentElement.clientWidth;
            const viewportHeight = viewport?.height ?? document.documentElement.clientHeight;
            const pad = 8;
            const gap = 12;
            const left = Math.max(0, rect.left - pad);
            const top = Math.max(0, rect.top - pad);
            const right = Math.min(viewportWidth, rect.right + pad);
            const bottom = Math.min(viewportHeight, rect.bottom + pad);

            setRect(shades.top, 0, 0, viewportWidth, top);
            setRect(shades.left, 0, top, left, Math.max(0, bottom - top));
            setRect(shades.right, right, top, Math.max(0, viewportWidth - right), Math.max(0, bottom - top));
            setRect(shades.bottom, 0, bottom, viewportWidth, Math.max(0, viewportHeight - bottom));
            setRect(spotlight, left, top, Math.max(0, right - left), Math.max(0, bottom - top));

            const pop = popover.getBoundingClientRect();
            const dir = document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
            const preferredLeft = dir === 'rtl' ? rect.right - pop.width : rect.left;
            const x = Math.min(Math.max(gap, preferredLeft), Math.max(gap, viewportWidth - pop.width - gap));
            let y = rect.bottom + gap;
            if (y + pop.height > viewportHeight - gap && rect.top - pop.height - gap >= gap) {
                y = rect.top - pop.height - gap;
            }
            y = Math.min(Math.max(gap, y), Math.max(gap, viewportHeight - pop.height - gap));
            popover.style.left = `${x}px`;
            popover.style.top = `${y}px`;
        };

        const schedulePosition = () => {
            if (state.raf) cancelAnimationFrame(state.raf);
            state.raf = requestAnimationFrame(() => {
                state.raf = 0;
                positionTour();
            });
        };

        const inertSiblings = new Map();
        const isolateTour = () => {
            for (let branch = root; branch?.parentElement; branch = branch.parentElement) {
                for (const sibling of branch.parentElement.children) {
                    if (sibling === branch || !(sibling instanceof HTMLElement) || inertSiblings.has(sibling)) continue;
                    inertSiblings.set(sibling, sibling.inert);
                    sibling.inert = true;
                }
                if (branch.parentElement === document.body) break;
            }
        };

        const hideTour = () => {
            state.active = false;
            state.target = null;
            root.removeAttribute('data-tour-active');
            popover.hidden = true;
            inertSiblings.forEach((wasInert, element) => { element.inert = wasInert; });
            inertSiblings.clear();
            if (state.trigger?.isConnected) state.trigger.focus({ preventScroll: true });
            state.trigger = null;
        };

        const showCurrentStep = async () => {
            while (state.index < config.steps.length) {
                const step = config.steps[state.index];
                const target = findTarget(step.anchor);
                if (!target) {
                    state.hadSkipped = true;
                    await saveProgress(step, 'skipped');
                    state.index += 1;
                    continue;
                }

                state.currentStep = step;
                state.target = target;
                titleNode.textContent = step.title;
                bodyNode.textContent = step.body;
                countNode.textContent = config.messages.step_count
                    .replace(':current', String(state.index + 1))
                    .replace(':total', String(config.steps.length));
                previousButton.disabled = state.index === 0;
                nextButton.textContent = state.index === config.steps.length - 1
                    ? config.messages.finish
                    : config.messages.next;
                target.scrollIntoView({
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    block: 'center',
                    inline: 'nearest',
                });
                isolateTour();
                popover.hidden = false;
                root.setAttribute('data-tour-active', '');
                requestAnimationFrame(() => {
                    positionTour();
                    closeButton.focus({ preventScroll: true });
                });
                await saveProgress(step, state.hadSkipped ? 'skipped' : 'in_progress');
                return;
            }

            const finalStep = config.steps[config.steps.length - 1];
            const finalStatus = state.hadSkipped ? 'skipped' : 'completed';
            await saveProgress(finalStep, finalStatus);
            announce(state.hadSkipped ? config.messages.missing_anchors : config.messages.done);
            hideTour();
        };

        const startTour = async (restart = false) => {
            startButton.disabled = true;
            restartButton.disabled = true;
            announce('');
            state.trigger = document.activeElement;
            state.active = true;

            let progress = null;
            if (!restart) progress = await readProgress();
            if (restart || !progress) {
                state.index = 0;
                state.hadSkipped = false;
            } else {
                const savedIndex = config.steps.findIndex((step) => step.key === progress.step_key);
                state.index = savedIndex < 0 ? 0 : savedIndex;
                state.hadSkipped = progress.status === 'skipped';
                if (progress.status === 'completed') state.index = 0;
            }

            await showCurrentStep();
            startButton.disabled = false;
            restartButton.disabled = false;
        };

        const pauseTour = async () => {
            const status = state.hadSkipped ? 'skipped' : 'paused';
            await saveProgress(state.currentStep, status);
            announce(status === 'skipped' ? config.messages.missing_anchors : label('paused'));
            hideTour();
        };

        const skipStep = async () => {
            if (!state.currentStep) return;
            state.hadSkipped = true;
            await saveProgress(state.currentStep, 'skipped');
            state.index += 1;
            await showCurrentStep();
        };

        startButton.addEventListener('click', () => startTour(false));
        restartButton.addEventListener('click', () => startTour(true));
        previousButton.addEventListener('click', async () => {
            if (state.index < 1) return;
            state.index -= 1;
            await showCurrentStep();
        });
        nextButton.addEventListener('click', async () => {
            if (!state.active) return;
            state.index += 1;
            await showCurrentStep();
        });
        skipButton.addEventListener('click', skipStep);
        pauseButton.addEventListener('click', pauseTour);
        closeButton.addEventListener('click', pauseTour);

        popover.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                pauseTour();
                return;
            }
            if (event.key !== 'Tab') return;
            const focusable = Array.from(popover.querySelectorAll('button:not([disabled])'));
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!state.active || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const rtl = document.documentElement.dir === 'rtl';
            const forward = (event.key === 'ArrowRight') !== rtl;
            if (forward) nextButton.click();
            else previousButton.click();
        });

        window.addEventListener('resize', schedulePosition, { passive: true });
        window.addEventListener('scroll', schedulePosition, { passive: true, capture: true });
        window.visualViewport?.addEventListener('resize', schedulePosition, { passive: true });
        window.visualViewport?.addEventListener('scroll', schedulePosition, { passive: true });
        window.addEventListener('pagehide', () => {
            if (!state.active) return;
            saveProgress(state.currentStep, state.hadSkipped ? 'skipped' : 'paused', true);
            hideTour();
        });

        const bootTour = async () => {
            const progress = await readProgress();
            if (!root.hasAttribute('data-tour-autostart') || progress === null || progress.status !== null) return;
            await startTour(false);
        };

        bootTour();
    });
})();
