import { expect, test } from '@playwright/test';

test('reference login is responsive, localized, keyboard accessible and keeps native validation', async ({ page }, testInfo) => {
  for (const locale of ['ar', 'en']) {
    await page.goto('/login?locale=' + locale);
    await expect(page.locator('[data-auth-layout]')).toHaveCount(1);
    const image = await page.request.get('/images/login-materials.png');
    expect(image.status()).toBe(200);
    expect(image.headers()['content-type']).toContain('image/png');
    await expect(page.locator('.dashboard-sidebar, .sidebar')).toHaveCount(0);
    await expect(page.locator('h1')).toHaveCount(1);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
    const email = page.locator('[name="email"]');
    const password = page.locator('[name="password"]');
    await expect(email).toHaveJSProperty('required', true);
    await expect(password).toHaveJSProperty('required', true);
    await expect(email).toHaveAccessibleName(/\S+/);
    await expect(password).toHaveAccessibleName(/\S+/);
    await expect(password).toHaveAttribute('type', 'password');
    await password.fill('only-a-synthetic-ui-check');
    const toggle = page.locator('[data-password-toggle]');
    await toggle.click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await toggle.press('Enter');
    await expect(password).toHaveAttribute('type', 'password');
    await password.fill('');
    await page.locator('button[type="submit"]').click();
    await expect(email).toBeFocused();
    expect(await email.evaluate((e: HTMLInputElement) => e.validity.valueMissing)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.getByRole('heading', { level: 1 }).click();
    await page.screenshot({ path: `.local/windows-qa/login-${testInfo.project.name}-${locale}.png`, fullPage: true });
    await page.locator('html').evaluate(element => element.setAttribute('data-theme', 'dark'));
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.screenshot({ path: `.local/windows-qa/login-${testInfo.project.name}-${locale}-dark.png`, fullPage: true });
  }
});
