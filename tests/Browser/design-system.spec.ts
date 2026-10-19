import { expect, test } from '@playwright/test';

const authorizedState = process.env.ARASYA_BROWSER_AUTH_STATE || '.local/windows-qa/auth.json';
const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';

function relativeLuminance(color: string): number {
  const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
  if (!channels || channels.length !== 3) {
    throw new Error(`Expected a computed RGB color, received: ${color}`);
  }

  const [red, green, blue] = channels.map(channel => {
    const normalized = channel / 255;
    return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrastRatio(foreground: string, background: string): number {
  const luminances = [relativeLuminance(foreground), relativeLuminance(background)].sort((left, right) => right - left);

  return (luminances[0] + 0.05) / (luminances[1] + 0.05);
}

test.describe('authenticated design system showcase', () => {
  test.use({ storageState: authorizedState });

  test.beforeEach(() => {
    expect(authorizedState, 'Use the existing authorized browser session state').toBeTruthy();
  });

  test('starts Arabic RTL in light mode and toggles the computed theme', async ({ page }) => {
    const response = await page.goto('/dev/design-system');

    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).not.toHaveAttribute('data-theme', /.+/);

    const lightBackground = await page.locator('html').evaluate(element => getComputedStyle(element).backgroundColor);
    await page.getByTestId('theme-toggle').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    const darkBackground = await page.locator('html').evaluate(element => getComputedStyle(element).backgroundColor);
    expect(darkBackground).not.toBe(lightBackground);
  });

  test('keeps root text and primary-button text at 4.5:1 contrast in light and dark themes', async ({ page }) => {
    await page.goto('/dev/design-system');
    const primaryButton = page.locator('.ui-button--primary').first();

    for (const theme of ['light', 'dark']) {
      if (theme === 'dark') {
        await page.getByTestId('theme-toggle').click();

        const targetColors = await page.locator('html').evaluate(root => {
          const resolve = (token: string) => {
            const probe = document.createElement('span');
            probe.style.color = `var(${token})`;
            root.append(probe);
            const color = getComputedStyle(probe).color;
            probe.remove();

            return color;
          };

          return {
            buttonBackground: resolve('--color-primary'),
            buttonText: resolve('--color-on-primary'),
          };
        });

        await expect(primaryButton).toHaveCSS('background-color', targetColors.buttonBackground);
        await expect(primaryButton).toHaveCSS('color', targetColors.buttonText);
      }

      const colors = await page.evaluate(() => {
        const rootStyle = getComputedStyle(document.documentElement);
        const primaryButton = document.querySelector('.ui-button--primary');
        if (!(primaryButton instanceof HTMLElement)) {
          throw new Error('Expected a primary button on the catalog screen.');
        }
        const buttonStyle = getComputedStyle(primaryButton);

        return {
          rootText: rootStyle.color,
          rootBackground: rootStyle.backgroundColor,
          buttonText: buttonStyle.color,
          buttonBackground: buttonStyle.backgroundColor,
        };
      });

      expect(contrastRatio(colors.rootText, colors.rootBackground), `${theme} root text contrast`).toBeGreaterThanOrEqual(4.5);
      expect(contrastRatio(colors.buttonText, colors.buttonBackground), `${theme} primary button text contrast`).toBeGreaterThanOrEqual(4.5);
    }
  });

  test('direction control toggles language and direction, while the English URL serves English copy', async ({ page }) => {
    await page.goto('/dev/design-system');
    const directionToggle = page.getByTestId('direction-toggle');

    await directionToggle.click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await directionToggle.click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

    const response = await page.goto('/dev/design-system?screen=orders&locale=en');
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.getByText('Sales orders', { exact: true })).toBeVisible();
  });

  test('native modal and drawer trap keyboard focus, close with Escape, and restore the opener', async ({ page }) => {
    await page.goto('/dev/design-system?screen=catalog');

    for (const id of ['demo-modal', 'demo-drawer']) {
      const opener = page.locator(`[data-open-dialog="${id}"]`).first();
      const dialog = page.locator(`dialog#${id}`);

      await opener.click();
      await expect(dialog).toHaveAttribute('open', '');

      for (let index = 0; index < 8; index += 1) {
        await page.keyboard.press('Tab');
        expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
      }

      await page.keyboard.press('Escape');
      await expect(dialog).not.toHaveAttribute('open', '');
      await expect(opener).toBeFocused();
    }
  });

  test('order tabs follow RTL and LTR arrow-key direction', async ({ page }) => {
    await page.goto('/dev/design-system?screen=order');
    const rtlProducts = page.getByRole('tab', { name: 'المنتجات' });
    const rtlActivity = page.getByRole('tab', { name: 'سجل العمليات' });

    await rtlProducts.focus();
    await page.keyboard.press('ArrowLeft');
    await expect(rtlActivity).toBeFocused();
    await expect(rtlActivity).toHaveAttribute('aria-selected', 'true');
    const activityPanelId = await rtlActivity.getAttribute('aria-controls');
    expect(activityPanelId).toBeTruthy();
    await expect(page.locator(`#${activityPanelId}`)).toBeVisible();

    await page.goto('/dev/design-system?screen=order&locale=en');
    const ltrProducts = page.getByRole('tab', { name: 'Products' });
    const ltrActivity = page.getByRole('tab', { name: 'Activity' });

    await ltrProducts.focus();
    await page.keyboard.press('ArrowRight');
    await expect(ltrActivity).toBeFocused();
    await expect(ltrActivity).toHaveAttribute('aria-selected', 'true');
  });

  test('mobile navigation toggles its controlled region and expanded state', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-chromium', 'The navigation toggle is shown at the mobile breakpoint.');

    await page.goto('/dev/design-system');
    const toggle = page.getByRole('button', { name: 'فتح أو إغلاق القائمة' });
    const navigation = page.locator('#primary-navigation');

    await expect(toggle).toHaveAttribute('aria-controls', 'primary-navigation');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(navigation).toBeHidden();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(navigation).toBeVisible();
  });

  test('orders search submits the real filter and displays its matching row count', async ({ page }) => {
    await page.goto('/dev/design-system?screen=orders');
    await page.getByLabel('البحث في الطلبات').fill('النصر');
    await page.getByRole('button', { name: 'تطبيق', exact: true }).click();

    await expect(page).toHaveURL(/screen=orders.*search=/);
    await expect(page.getByTestId('orders-table').locator('[data-order-id]')).toHaveCount(1);
    await expect(page.getByTestId('orders-table').getByText('شركة النصر للتجارة')).toBeVisible();
    await expect(page.getByTestId('order-count')).toContainText('1');
  });

  test('all five showcase screens avoid page-level horizontal overflow and tables scroll locally', async ({ page }, testInfo) => {
    for (const screen of ['dashboard', 'orders', 'order', 'form', 'approvals']) {
      const response = await page.goto(`/dev/design-system?screen=${screen}`);
      expect(response?.status(), `${screen} should render successfully`).toBe(200);

      const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
      }));
      expect(dimensions.document, `${screen} document width`).toBeLessThanOrEqual(dimensions.viewport);
      expect(dimensions.body, `${screen} body width`).toBeLessThanOrEqual(dimensions.viewport);

      if (screen === 'orders' && testInfo.project.name === 'mobile-chromium') {
        const table = page.getByTestId('orders-table');
        const tableOverflow = await table.evaluate(element => ({
          hasOverflow: element.scrollWidth > element.clientWidth,
          overflowX: getComputedStyle(element).overflowX,
        }));
        expect(tableOverflow.hasOverflow).toBe(true);
        expect(tableOverflow.overflowX).toMatch(/auto|scroll/);
      }
    }
  });

  test('AI controls stay preview-only and make no provider requests', async ({ page }) => {
    const appOrigin = new URL(baseURL).origin;
    const externalRequests: string[] = [];
    const aiRequests: string[] = [];
    page.on('request', request => {
      const url = new URL(request.url());

      if (url.origin !== appOrigin) {
        externalRequests.push(url.origin);
      }

      if (/\/(?:api\/)?(?:ai|assistant|provider|providers)(?:\/|$)/i.test(url.pathname)) {
        aiRequests.push(`${request.method()} ${url.pathname}`);
      }
    });

    await page.goto('/dev/design-system?screen=catalog');
    await expect(page.getByText('معاينة فقط', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'غير متاح — بوابة AI غير موصلة' })).toBeDisabled();
    expect(externalRequests).toEqual([]);
    expect(aiRequests).toEqual([]);
  });

  test('tooltip appears on keyboard focus and references its accessible description', async ({ page }) => {
    await page.goto('/dev/design-system?screen=catalog');
    const trigger = page.locator('[data-ui-tooltip] .ui-tooltip__trigger').first();
    const tooltipId = await trigger.getAttribute('aria-describedby');

    expect(tooltipId).toBeTruthy();
    await trigger.focus();
    const tooltip = page.locator(`#${tooltipId}`);
    await expect(tooltip).toHaveRole('tooltip');
    await expect(tooltip).toBeVisible();
  });

  test('reduced-motion preference shortens UI transitions and animations', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/dev/design-system');

    const durations = await page.locator('.sidebar nav a').first().evaluate(element => {
      const style = getComputedStyle(element);
      const seconds = (value: string) => {
        const duration = Number.parseFloat(value);
        return value.trim().endsWith('ms') ? duration / 1000 : duration;
      };

      return {
        transition: style.transitionDuration.split(',').map(seconds),
        animation: style.animationDuration.split(',').map(seconds),
      };
    });

    expect(durations.transition.length).toBeGreaterThan(0);
    expect(durations.transition.every(duration => duration <= 0.0001)).toBe(true);
    expect(durations.animation.every(duration => duration <= 0.0001)).toBe(true);
  });

  test('catalog has basic accessible names, unique IDs, labels, and visible keyboard focus', async ({ page }) => {
    await page.goto('/dev/design-system?screen=catalog');
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);

    const ids = await page.locator('[id]').evaluateAll(elements => elements.map(element => element.id));
    expect(new Set(ids).size).toBe(ids.length);

    const controls = await page.locator('input:not([type="hidden"]), select, textarea').evaluateAll(elements =>
      elements.map(element => ({
        accessibleName: element.getAttribute('aria-label')?.trim() || (element as HTMLInputElement).labels?.[0]?.textContent?.trim() || '',
      })),
    );
    expect(controls.length).toBeGreaterThan(0);
    expect(controls.every(control => control.accessibleName.length > 0)).toBe(true);

    const buttons = page.getByRole('button');
    const buttonCount = await buttons.count();
    expect(buttonCount).toBeGreaterThan(0);
    for (let index = 0; index < buttonCount; index += 1) {
      await expect(buttons.nth(index), `button ${index + 1} should have an accessible name`).toHaveAccessibleName(/\S+/);
    }

    await page.keyboard.press('Tab');
    const skipLink = page.locator('.skip-link');
    await expect(skipLink).toBeFocused();
    expect(await skipLink.evaluate(element => element.matches(':focus-visible'))).toBe(true);
  });

  test('captures catalog, order, and orders views for desktop and mobile review', async ({ page }, testInfo) => {
    for (const screen of ['catalog', 'order', 'orders']) {
      const response = await page.goto(`/dev/design-system?screen=${screen}`);
      expect(response?.status(), `${screen} should render successfully`).toBe(200);
      await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
      await page.screenshot({
        path: testInfo.outputPath(`design-system-${screen}.png`),
        fullPage: true,
      });
    }

    await page.goto('/dev/design-system?screen=order');
    await page.getByTestId('theme-toggle').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.screenshot({
      path: testInfo.outputPath('design-system-order-dark.png'),
      fullPage: true,
    });

    const englishOrders = await page.goto('/dev/design-system?screen=orders&locale=en');
    expect(englishOrders?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.getByText('Sales orders', { exact: true })).toBeVisible();
    await page.screenshot({
      path: testInfo.outputPath('design-system-orders-en.png'),
      fullPage: true,
    });
  });
});
