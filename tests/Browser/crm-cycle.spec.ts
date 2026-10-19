import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';

type UserFixture = { id: string; email: string; password: string };
type CrmFixture = {
  owner_suffix: string;
  lead_count: number;
  stage_id: string;
  assigned_lead_id: string;
  unrelated_lead_id: string;
  manager: UserFixture;
  sales: UserFixture;
  unrelated_sales: UserFixture;
};

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
const fixturePath = resolve('storage/framework/testing/crm-fixture.json');
test.use({ trace: 'off' });
const contactFor = (suffix: string, number: number) => ({
  email: `synthetic-crm-${suffix}-lead-${String(number).padStart(3, '0')}@example.test`,
  phone: `+2012000${String(number).padStart(5, '0')}`,
  whatsapp: `+2013000${String(number).padStart(5, '0')}`,
});

function readFixture(): CrmFixture {
  return JSON.parse(readFileSync(fixturePath, 'utf8')) as CrmFixture;
}

async function login(page: Page, user: UserFixture): Promise<void> {
  const response = await page.goto('/login');
  expect(response?.status()).toBe(200);
  await page.getByLabel('البريد الإلكتروني', { exact: true }).fill(user.email);
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password);
  const post = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await page.getByRole('button', { name: 'تسجيل الدخول', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(/\/dashboard\/?$/);
}

async function expectCrmShell(page: Page, locale: 'ar' | 'en'): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('.dashboard-sidebar')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
}

async function fillField(form: Locator, name: string, value: string): Promise<Locator> {
  const field = form.locator(`[name="${name}"]`);
  await expect(field).toHaveCount(1);
  await expect(field).toHaveAccessibleName(/\S+/);
  await field.fill(value);
  return field;
}

async function expectRedirect(response: import('@playwright/test').Response): Promise<void> {
  expect([302, 303]).toContain(response.status());
}

test('manager overview, directory, create, edit, and reasoned contact reveal', async ({ page }, testInfo) => {
  const fixture = readFixture();
  const createdNumber = testInfo.project.name.startsWith('mobile') ? 302 : 301;
  const createdName = `Synthetic CRM Prospect ${String(createdNumber).padStart(3, '0')} ${fixture.owner_suffix}`;
  const createdEmail = `crm-${randomUUID()}@example.test`;
  const createdPhone = `+201555${randomUUID().replaceAll('-', '').slice(0, 10)}`;
  const createdWhatsapp = `+201556${randomUUID().replaceAll('-', '').slice(0, 10)}`;

  await login(page, fixture.manager);
  const overviewResponse = await page.goto('/crm');
  expect(overviewResponse?.status()).toBe(200);
  const overviewCount = await page.request.get(`${baseURL}/crm/leads?per_page=25`, {
    headers: { Accept: 'application/json' },
  });
  expect(overviewCount.status()).toBe(200);
  const scopedTotal = Number((await overviewCount.json()).meta.total);
  expect(scopedTotal).toBeGreaterThanOrEqual(fixture.lead_count);
  await expectCrmShell(page, 'ar');
  await page.goto('/crm?locale=en');
  await expectCrmShell(page, 'en');
  await expect(page.locator('.ui-stat-card').filter({ hasText: 'Active leads' })).toContainText(String(scopedTotal));
  await page.goto('/crm');
  await expectCrmShell(page, 'ar');
  await page.screenshot({ path: testInfo.outputPath('crm-overview-rtl.png'), fullPage: true });
  await page.keyboard.press('Tab');
  expect(await page.evaluate(() => document.activeElement?.tagName !== 'BODY')).toBe(true);
  const lightBackground = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-background').trim());
  const themeToggle = page.getByRole('button', { name: 'تبديل المظهر', exact: true });
  await expect(themeToggle).toHaveAccessibleName('تبديل المظهر');
  await themeToggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  const darkBackground = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-background').trim());
  expect(darkBackground).not.toBe(lightBackground);
  await themeToggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

  const directoryResponse = await page.goto('/crm/leads?locale=en');
  expect(directoryResponse?.status()).toBe(200);
  await expectCrmShell(page, 'en');
  await expect(page.locator('table tbody tr')).toHaveCount(25);

  const createResponse = await page.goto('/crm/leads/create?locale=en');
  expect(createResponse?.status()).toBe(200);
  await expectCrmShell(page, 'en');
  await expect(page.getByRole('heading', { level: 1, name: 'Create lead', exact: true })).toHaveCount(1);
  const createForm = page.locator('form').filter({ has: page.locator('[name="contacts[email]"]') });
  await expect(createForm).toHaveCount(1);
  await fillField(createForm, 'name', createdName);
  await fillField(createForm, 'organization', `Synthetic CRM Organization ${createdNumber} ${fixture.owner_suffix}`);
  await fillField(createForm, 'source', 'website');
  await fillField(createForm, 'notes', 'Synthetic browser create form record.');
  await fillField(createForm, 'contacts[email]', createdEmail);
  await fillField(createForm, 'contacts[phone]', createdPhone);
  await fillField(createForm, 'contacts[whatsapp]', createdWhatsapp);
  const stage = createForm.locator('[name="stage_id"]');
  await expect(stage).toHaveCount(1);
  await stage.selectOption(fixture.stage_id);
  const consent = createForm.locator('input[type="checkbox"][name="consent"]');
  await expect(consent).toHaveCount(1);
  await consent.check();

  const createPost = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/crm/leads');
  await createForm.getByRole('button', { name: 'Save lead', exact: true }).click();
  await expectRedirect(await createPost);

  const createdRowsResponse = await page.request.get(`${baseURL}/crm/leads?${new URLSearchParams({ q: createdName, per_page: '100' })}`, {
    headers: { Accept: 'application/json' },
  });
  expect(createdRowsResponse.status()).toBe(200);
  const createdRows = (await createdRowsResponse.json()).data as Array<{ id: string; name: string }>;
  const createdLead = createdRows.find((lead) => lead.name === createdName);
  expect(createdLead?.id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
  const detailPath = `/crm/leads/${createdLead!.id}`;
  const detailResponse = await page.goto(`${detailPath}?locale=en`);
  expect(detailResponse?.status()).toBe(200);
  await expectCrmShell(page, 'en');
  await expect(page.getByText(createdName, { exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Edit lead', exact: true })).toBeVisible();
  const maskedDetail = await page.content();
  for (const value of [createdEmail, createdPhone, createdWhatsapp]) {
    expect(maskedDetail).not.toContain(value);
  }

  await page.getByRole('link', { name: 'Edit lead', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`${detailPath}/edit\\?locale=en$`));
  await expectCrmShell(page, 'en');
  await expect(page.getByRole('heading', { level: 1, name: 'Edit lead', exact: true })).toHaveCount(1);
  const editForm = page.locator('form').filter({ has: page.locator('[name="version"]') });
  await expect(editForm).toHaveCount(1);
  await expect(editForm.locator('[name="name"]')).toHaveValue(createdName);
  const maskedEdit = await page.content();
  for (const value of [createdEmail, createdPhone, createdWhatsapp]) {
    expect(maskedEdit).not.toContain(value);
  }
  await fillField(editForm, 'organization', `Synthetic CRM Organization updated ${fixture.owner_suffix}`);
  const editPost = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === detailPath);
  await editForm.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expectRedirect(await editPost);
  const updatedDetail = await page.goto(`${detailPath}?locale=en`);
  expect(updatedDetail?.status()).toBe(200);
  await expect(page.getByText(`Synthetic CRM Organization updated ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();

  const revealForm = page.locator(`form[action*="${detailPath}/reveal"]`);
  await expect(revealForm).toHaveCount(1);
  const revealDialog = revealForm.locator('xpath=ancestor::dialog[1]');
  if (await revealDialog.count()) {
    const open = await revealDialog.evaluate((element: HTMLDialogElement) => element.open);
    if (!open) {
      const dialogId = await revealDialog.getAttribute('id');
      expect(dialogId).toBeTruthy();
      await page.locator(`[data-open-dialog="${dialogId}"]`).click();
    }
  }
  await fillField(revealForm, 'reason', 'Synthetic browser authorized contact review.');
  const revealPost = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === `${detailPath}/reveal`);
  await revealForm.getByRole('button', { name: /reveal|show contacts/i }).click();
  expect([200, 302, 303]).toContain((await revealPost).status());
  await expect(page.getByText(createdEmail, { exact: true })).toBeVisible();
});

test('assigned and unrelated salespeople see only their own leads and no contact data', async ({ page }) => {
  const fixture = readFixture();
  const actors = [
    { user: fixture.sales, ownNumber: 1, ownLeadId: fixture.assigned_lead_id, hiddenNumber: 2, hiddenLeadId: fixture.unrelated_lead_id },
    { user: fixture.unrelated_sales, ownNumber: 2, ownLeadId: fixture.unrelated_lead_id, hiddenNumber: 1, hiddenLeadId: fixture.assigned_lead_id },
  ];

  for (const actor of actors) {
    await page.context().clearCookies();
    await login(page, actor.user);
    const listing = await page.goto('/crm/leads?locale=en');
    expect(listing?.status()).toBe(200);
    await expectCrmShell(page, 'en');
    await expect(page.locator('table tbody tr')).toHaveCount(1);
    await expect(page.getByText(`Synthetic CRM Prospect ${String(actor.ownNumber).padStart(3, '0')} ${fixture.owner_suffix}`, { exact: true })).toBeVisible();
    await expect(page.getByText(`Synthetic CRM Prospect ${String(actor.hiddenNumber).padStart(3, '0')} ${fixture.owner_suffix}`, { exact: true })).toHaveCount(0);
    const scopedRows = await page.request.get(`${baseURL}/crm/leads?per_page=25`, { headers: { Accept: 'application/json' } });
    expect(scopedRows.status()).toBe(200);
    expect(Number((await scopedRows.json()).meta.total)).toBe(1);

    const detailPath = `/crm/leads/${actor.ownLeadId}`;
    const detail = await page.goto(`${detailPath}?locale=en`);
    expect(detail?.status()).toBe(200);
    await expectCrmShell(page, 'en');
    await expect(page.locator(`form[action*="${detailPath}/reveal"]`)).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Edit lead', exact: true })).toHaveCount(0);
    const html = await page.content();
    for (const value of Object.values(contactFor(fixture.owner_suffix, actor.ownNumber))) {
      expect(html).not.toContain(value);
    }

    const hiddenDetail = await page.goto(`/crm/leads/${actor.hiddenLeadId}?locale=en`);
    expect(hiddenDetail?.status()).toBe(404);
    const hiddenHtml = await page.content();
    for (const value of Object.values(contactFor(fixture.owner_suffix, actor.hiddenNumber))) {
      expect(hiddenHtml).not.toContain(value);
    }
  }
});
