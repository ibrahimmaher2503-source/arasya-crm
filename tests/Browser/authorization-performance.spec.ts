import { expect, test } from '@playwright/test';

test.use({ storageState: process.env.ARASYA_BROWSER_AUTH_STATE });

test('CRM Employees Tasks and Access render with bounded request authorization SQL', async ({ page }) => {
  for (const [route, maximum] of [
    ['/crm', 24], ['/crm/leads', 24], ['/employees', 8], ['/tasks', 8], ['/access/users', 12], ['/access/audit', 28],
  ] as const) {
    const response = await page.goto(`${route}?locale=en`);
    expect(response?.status()).toBe(200);
    const metrics = JSON.parse(response!.headers()['x-local-perf']);
    expect(metrics.auth, route).toBeLessThanOrEqual(maximum);
    await expect(page.getByRole('main')).toHaveCount(1);
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  }
  const response = await page.goto('/crm/leads/312WHQ1Y15SK3VWN2T7QKZVWA5?locale=ar');
  expect(response?.status()).toBe(200);
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('#crm-lead-contact')).toContainText('مخفي');
  expect(JSON.parse(response!.headers()['x-local-perf']).auth).toBeLessThanOrEqual(50);
});

test('anonymous CRM requests remain denied after another context was authorized', async ({ browser, page: authorizedPage }) => {
  expect((await authorizedPage.goto('/crm'))?.status()).toBe(200);
  await expect(authorizedPage.getByRole('main')).toHaveCount(1);
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const page = await context.newPage();
  const response = await page.goto(`${process.env.ARASYA_BROWSER_BASE_URL}/crm`);
  expect(response?.status()).toBe(401);
  await expect(page.locator('html')).not.toContainText('Synthetic performance actor');
  await context.close();
});
