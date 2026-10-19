import { test, expect } from '@playwright/test';
import { randomUUID } from 'node:crypto';

const authorized = process.env.ARASYA_BROWSER_AUTH_STATE;
const denied = process.env.ARASYA_BROWSER_DENIED_STATE;
const otherEntity = process.env.ARASYA_BROWSER_OTHER_STATE;

test.describe('authenticated synthetic cycle', () => {
  test.use({ storageState: authorized });
  test.beforeEach(() => {
    expect(authorized, 'Generate authorized Laravel session storage state first').toBeTruthy();
  });

  test('Arabic RTL form saves a scoped cycle through HTTP', async ({ page }, testInfo) => {
    const response = await page.goto('/vertical/cycle-records');
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { name: 'دورة تشغيلية', level: 1 })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await page.getByLabel('نوع السجل').fill(`synthetic-browser-${randomUUID()}`);
    await page.getByLabel('الحالة', { exact: true }).fill('created');
    await page.screenshot({ path: testInfo.outputPath('rtl-form.png'), fullPage: true });
    const saved = page.waitForResponse(r => r.url().endsWith('/vertical/cycle-records') && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    const result = await saved;
    expect(result.status()).toBe(201);
    const body = await result.json();
    expect(body.data.record_id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
    expect(body.data.entity_id).toBe('synthetic-browser-entity');
    expect(body.data.branch_id).toBe('synthetic-browser-branch');
    await testInfo.attach('saved-cycle', { body: JSON.stringify(body), contentType: 'application/json' });
  });

  test('replaying an authenticated browser request reuses the record', async ({ page }) => {
    await page.goto('/vertical/cycle-records');
    const token = await page.locator('input[name="_token"]').inputValue();
    const key = `synthetic-browser-${randomUUID()}`;
    const results = await page.evaluate(async ({ token, key }) => {
      const send = async () => {
        const response = await fetch('/vertical/cycle-records', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'Idempotency-Key': key },
          body: JSON.stringify({ record_type: 'synthetic-browser-replay', payload: { state: 'created' } }),
        });
        return { status: response.status, body: await response.json() };
      };
      return [await send(), await send()];
    }, { token, key });
    expect(results.map(r => r.status)).toEqual([201, 201]);
    expect(results[0].body.data.record_id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
    expect(results[1].body.data.record_id).toBe(results[0].body.data.record_id);
  });
});

test('anonymous page access is denied', async ({ page }) => {
  expect((await page.goto('/vertical/cycle-records'))?.status()).toBe(401);
});

test('session without write permission cannot save', async ({ browser }) => {
  expect(denied, 'Generate denied Laravel session storage state first').toBeTruthy();
  const context = await browser.newContext({ storageState: denied });
  try {
    const page = await context.newPage();
    await page.goto(`${process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765'}/vertical/cycle-records`);
    await page.getByLabel('نوع السجل').fill('synthetic-browser-denied');
    await page.getByLabel('الحالة', { exact: true }).fill('created');
    const result = page.waitForResponse(r => r.request().method() === 'POST');
    await page.getByRole('button', { name: 'حفظ', exact: true }).click();
    expect((await result).status()).toBe(401);
  } finally {
    await context.close();
  }
});

test('a second entity saves only under its own session scope', async ({ browser }) => {
  expect(otherEntity, 'Generate second-entity Laravel session storage state first').toBeTruthy();
  expect(authorized, 'Generate authorized Laravel session storage state first').toBeTruthy();
  const key = `synthetic-browser-isolation-${randomUUID()}`;
  const originalContext = await browser.newContext({ storageState: authorized });
  const context = await browser.newContext({ storageState: otherEntity });
  try {
    const originalPage = await originalContext.newPage();
    const baseUrl = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
    await originalPage.goto(`${baseUrl}/vertical/cycle-records`);
    const originalToken = await originalPage.locator('input[name="_token"]').inputValue();
    const original = await originalContext.request.post(`${baseUrl}/vertical/cycle-records`, {
      headers: { 'X-CSRF-TOKEN': originalToken, 'Idempotency-Key': key, Accept: 'application/json' },
      data: { record_type: 'synthetic-browser-isolation', payload: { state: 'created' } },
    });
    expect(original.status()).toBe(201);
    const originalBody = await original.json();
    const page = await context.newPage();
    await page.goto(`${baseUrl}/vertical/cycle-records`);
    const token = await page.locator('input[name="_token"]').inputValue();
    const result = await page.evaluate(async ({ token, key }) => {
      const response = await fetch('/vertical/cycle-records', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'Idempotency-Key': key },
        body: JSON.stringify({ record_type: 'synthetic-browser-isolation', payload: { state: 'created' }, entity_id: 'synthetic-browser-entity' }),
      });
      return { status: response.status, body: await response.json() };
    }, { token, key });
    expect(result.status).toBe(201);
    expect(result.body.data.entity_id).toBe('synthetic-browser-other-entity');
    expect(result.body.data.branch_id).toBe('synthetic-browser-branch');
    expect(result.body.data.record_id).not.toBe(originalBody.data.record_id);
  } finally {
    await context.close();
    await originalContext.close();
  }
});
