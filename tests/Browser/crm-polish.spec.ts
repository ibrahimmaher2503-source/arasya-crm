import { randomUUID } from 'node:crypto';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';

type UserFixture = { id: string; email: string; password: string };
type CrmFixture = {
  owner_suffix: string;
  stage_id: string;
  outcome_id: string;
  assigned_lead_id: string;
  unrelated_lead_id: string;
  manager: UserFixture;
  sales: UserFixture;
  unrelated_sales: UserFixture;
};
type LeadFixture = { id: string; name: string; email: string };

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8773';
const fixturePath = resolve(process.env.ARASYA_REFERENCE_FIXTURE || 'storage/framework/testing/crm-fixture.json');
test.use({ trace: 'off' });

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

async function expectShell(page: Page, locale: 'ar' | 'en'): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('.dashboard-sidebar')).toHaveCount(1);
  await expect(page.locator('.dashboard-topbar')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
}

async function setTheme(page: Page, theme: 'light' | 'dark'): Promise<void> {
  const root = page.locator('html');
  if (await root.getAttribute('data-theme') !== theme) {
    await page.locator('[data-theme-toggle]').click();
    if (await root.getAttribute('data-theme') !== theme) {
      await page.locator('[data-theme-toggle]').click();
    }
  }
  await expect(root).toHaveAttribute('data-theme', theme);
}

async function capturePage(
  page: Page,
  testInfo: TestInfo,
  label: string,
  url: string,
  locale: 'ar' | 'en',
  theme: 'light' | 'dark',
): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status()).toBe(200);
  await expectShell(page, locale);
  await setTheme(page, theme);
  if (label === 'directory') {
    const tableRegion = page.locator('.ui-table-region');
    await expect(tableRegion).toHaveCount(1);
    expect(await tableRegion.evaluate((element) => getComputedStyle(element).overflowX)).toBe('auto');
  }
  await page.screenshot({ path: testInfo.outputPath(`${label}-${locale}-${theme}.png`), fullPage: true });
}

async function createLead(page: Page, fixture: CrmFixture, label: string): Promise<LeadFixture> {
  const token = randomUUID().replaceAll('-', '');
  const name = `Synthetic CRM Polish ${fixture.owner_suffix} ${label} ${token}`;
  const email = `crm-polish-${token}@example.test`;
  const phone = `+201555${token.slice(0, 10)}`;
  const formResponse = await page.goto('/crm/leads/create?locale=en');
  expect(formResponse?.status()).toBe(200);

  const form = page.locator('form').filter({ has: page.locator('[name="contacts[email]"]') });
  await expect(form).toHaveCount(1);
  await form.locator('[name="name"]').fill(name);
  await form.locator('[name="organization"]').fill(`Synthetic CRM Polish initial ${fixture.owner_suffix}`);
  await form.locator('[name="source"]').fill('website');
  await form.locator('[name="contacts[email]"]').fill(email);
  await form.locator('[name="contacts[phone]"]').fill(phone);
  await form.locator('[name="contacts[whatsapp]"]').fill(`+201556${token.slice(0, 10)}`);
  await form.locator('[name="stage_id"]').selectOption(fixture.stage_id);
  await form.locator('input[type="checkbox"][name="consent"]').check();

  const post = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/crm/leads');
  await form.getByRole('button', { name: 'Save lead', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(/\/crm\/leads\/[0-9A-HJKMNP-TV-Z]{26}\?locale=en$/);
  return { id: new URL(page.url()).pathname.split('/').at(-1)!, name, email };
}

async function expectMasked(page: Page, email: string): Promise<void> {
  expect(await page.content()).not.toContain(email);
}

function expectPrivateNoStore(cacheControl: string | undefined): void {
  const directives = new Set((cacheControl ?? '').split(',').map((directive) =>
    directive.trim().toLowerCase().split('=', 1)[0],
  ));
  expect([...directives]).toEqual(expect.arrayContaining(['private', 'no-store']));
}

async function openForm(form: Locator): Promise<void> {
  const disclosures: Locator[] = [];
  let ancestor = form.locator('xpath=..');

  while (await ancestor.count() > 0) {
    const tagName = await ancestor.evaluate((element) => element.tagName);
    if (tagName === 'DETAILS') disclosures.push(ancestor);
    if (tagName === 'HTML') break;
    ancestor = ancestor.locator('xpath=..');
  }

  for (const disclosure of disclosures.reverse()) {
    const isOpen = await disclosure.evaluate((element) => (element as HTMLDetailsElement).open);
    if (!isOpen) await disclosure.locator('summary').click();
  }
}

async function submitForm(page: Page, form: Locator, pathname: string, button: string): Promise<void> {
  await openForm(form);
  const response = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === pathname);
  await form.getByRole('button', { name: button, exact: true }).click();
  expect([302, 303]).toContain((await response).status());
}

test('CRM screen states render in both locales and themes at the configured viewport', async ({ page }, testInfo) => {
  const fixture = readFixture();
  await login(page, fixture.manager);
  const lead = await createLead(page, fixture, 'visual');

  await page.keyboard.press('Tab');
  await expect(page.locator('.skip-link')).toBeFocused();

  const routes = [
    ['overview', '/crm'],
    ['directory', '/crm/leads'],
    ['create', '/crm/leads/create'],
    ['edit', `/crm/leads/${lead.id}/edit`],
    ['detail', `/crm/leads/${lead.id}`],
  ] as const;
  for (const [label, path] of routes) {
    for (const locale of ['ar', 'en'] as const) {
      for (const theme of ['light', 'dark'] as const) {
        await capturePage(page, testInfo, label, `${path}?locale=${locale}`, locale, theme);
        await expectMasked(page, lead.email);
      }
    }
  }

  const navigationButton = page.locator('.mobile-menu');
  if (testInfo.project.name === 'desktop') {
    await expect(navigationButton).toBeHidden();
  } else {
    await expect(navigationButton).toBeVisible();
    await expect(navigationButton).toHaveAttribute('aria-expanded', 'false');
    await navigationButton.click();
    await expect(page.locator('.dashboard-shell')).toHaveClass(/is-nav-open/);
    await expect(navigationButton).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.sidebar-close')).toBeVisible();
    await page.locator('.sidebar-close').click();
    await expect(page.locator('.dashboard-shell')).not.toHaveClass(/is-nav-open/);
    await expect(navigationButton).toHaveAttribute('aria-expanded', 'false');
  }
});

test('directory filtering, pagination, empty state, validation, cancel, save, reload, and stale edits work', async ({ page }, testInfo) => {
  const fixture = readFixture();
  await login(page, fixture.manager);
  const cancelLead = await createLead(page, fixture, 'cancel');
  const editLead = await createLead(page, fixture, 'edit');

  const listing = await page.goto('/crm/leads?locale=en');
  expect(listing?.status()).toBe(200);
  await expect(page.locator('table tbody tr')).toHaveCount(25);
  const pageNav = page.getByRole('navigation', { name: 'Result pages' });
  await pageNav.getByRole('link', { name: 'Next', exact: true }).click();
  await expect(page).toHaveURL(/page=2/);
  await expect(page.locator('table tbody tr')).toHaveCount(25);

  const filter = page.getByRole('search', { name: 'Search and filters' });
  await filter.locator('[name="q"]').fill(editLead.name);
  await filter.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.locator('table tbody tr')).toHaveCount(1);
  await expect(page.getByText(editLead.name, { exact: true })).toBeVisible();
  await expect(page).toHaveURL(/q=Synthetic\+CRM\+Polish/);

  await filter.locator('[name="q"]').fill(`missing-${randomUUID()}`);
  await filter.getByRole('button', { name: 'Apply filters', exact: true }).click();
  await expect(page.getByText('No leads found', { exact: true })).toBeVisible();
  await expect(page.locator('table')).toHaveCount(0);

  const createPage = await page.goto('/crm/leads/create?locale=en');
  expect(createPage?.status()).toBe(200);
  const emptyForm = page.locator('form').filter({ has: page.locator('[name="contacts[email]"]') });
  await expect(emptyForm.locator('[name="name"]')).toHaveJSProperty('required', true);
  await emptyForm.getByRole('button', { name: 'Save lead', exact: true }).click();
  await expect(emptyForm.locator('[name="name"]')).toBeFocused();
  expect(await emptyForm.locator('[name="name"]').evaluate((input: HTMLInputElement) => !input.validity.valid)).toBe(true);

  await page.goto(`/crm/leads/${cancelLead.id}/edit?locale=en`);
  const editForm = page.locator('form').filter({ has: page.locator('[name="version"]') });
  await editForm.locator('[name="organization"]').fill('Synthetic CRM Polish cancelled draft');
  await editForm.getByRole('link', { name: 'Cancel', exact: true }).click();
  await expect(page.getByText(`Synthetic CRM Polish initial ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();
  await page.reload();
  await expect(page.getByText(`Synthetic CRM Polish initial ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();
  await expectMasked(page, cancelLead.email);

  await page.goto(`/crm/leads/${editLead.id}/edit?locale=en`);
  const saveForm = page.locator('form').filter({ has: page.locator('[name="version"]') });
  await saveForm.locator('[name="organization"]').fill(`Synthetic CRM Polish edited ${fixture.owner_suffix}`);
  await submitForm(page, saveForm, `/crm/leads/${editLead.id}`, 'Save changes');
  await expect(page.getByText(`Synthetic CRM Polish edited ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();
  await expectMasked(page, editLead.email);
  await page.reload();
  await expect(page.getByText(`Synthetic CRM Polish edited ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();
  for (const locale of ['ar', 'en'] as const) {
    for (const theme of ['light', 'dark'] as const) {
      await capturePage(page, testInfo, 'detail-after-save', `/crm/leads/${editLead.id}?locale=${locale}`, locale, theme);
      await expectMasked(page, editLead.email);
    }
  }

  const staleLead = await createLead(page, fixture, 'stale');
  const stalePage = await page.context().newPage();
  await page.goto(`/crm/leads/${staleLead.id}/edit?locale=en`);
  await stalePage.goto(`/crm/leads/${staleLead.id}/edit?locale=en`);
  const winnerForm = page.locator('form').filter({ has: page.locator('[name="version"]') });
  const staleForm = stalePage.locator('form').filter({ has: stalePage.locator('[name="version"]') });
  await winnerForm.locator('[name="organization"]').fill(`Synthetic CRM Polish winner ${fixture.owner_suffix}`);
  await submitForm(page, winnerForm, `/crm/leads/${staleLead.id}`, 'Save changes');
  await staleForm.locator('[name="organization"]').fill(`Synthetic CRM Polish stale overwrite ${fixture.owner_suffix}`);
  const staleResponse = stalePage.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === `/crm/leads/${staleLead.id}`);
  await staleForm.getByRole('button', { name: 'Save changes', exact: true }).click();
  expect((await staleResponse).status()).toBe(409);
  await page.goto(`/crm/leads/${staleLead.id}?locale=en`);
  await expect(page.getByText(`Synthetic CRM Polish winner ${fixture.owner_suffix}`, { exact: true }).first()).toBeVisible();
  await expect(page.getByText(`Synthetic CRM Polish stale overwrite ${fixture.owner_suffix}`, { exact: true })).toHaveCount(0);
  await stalePage.close();
});

test('assignment, activity, transfer decisions, conversion, and archive are available through the CRM UI', async ({ page }) => {
  const fixture = readFixture();
  await login(page, fixture.manager);
  const activityLead = await createLead(page, fixture, 'activity');
  const acceptLead = await createLead(page, fixture, 'transfer-accept');
  const rejectLead = await createLead(page, fixture, 'transfer-reject');
  const convertedLead = await createLead(page, fixture, 'converted');
  const archivedLead = await createLead(page, fixture, 'archived');

  for (const lead of [activityLead, acceptLead, rejectLead]) {
    await page.goto(`/crm/leads/${lead.id}?locale=en`);
    const assignmentForm = page.locator(`form[action^="/crm/leads/${lead.id}/assignment"]`);
    await openForm(assignmentForm);
    await assignmentForm.locator('[name="assignee_id"]').selectOption(fixture.sales.id);
    await submitForm(page, assignmentForm, `/crm/leads/${lead.id}/assignment`, 'Assign lead');
  }

  await page.context().clearCookies();
  await login(page, fixture.sales);
  await page.goto(`/crm/leads/${activityLead.id}?locale=en`);
  const activityForm = page.locator(`form[action^="/crm/leads/${activityLead.id}/activities"]`);
  await openForm(activityForm);
  await activityForm.locator('[name="kind"]').selectOption('call');
  await activityForm.locator('[name="outcome_id"]').selectOption(fixture.outcome_id);
  await activityForm.locator('[name="occurred_at"]').fill('2026-10-18T10:30:00+03:00');
  await activityForm.locator('[name="next_step"]').fill('Synthetic CRM browser follow-up');
  await activityForm.locator('[name="note"]').fill('Synthetic browser activity record.');
  await submitForm(page, activityForm, `/crm/leads/${activityLead.id}/activities`, 'Record activity');
  await expect(page.getByText('Synthetic CRM browser follow-up', { exact: true })).toBeVisible();

  await page.context().clearCookies();
  await login(page, fixture.manager);
  for (const [lead, context] of [
    [acceptLead, 'Synthetic CRM browser transfer for acceptance.'],
    [rejectLead, 'Synthetic CRM browser transfer for rejection.'],
  ] as const) {
    await page.goto(`/crm/leads/${lead.id}?locale=en`);
    const transferForm = page.locator(`form[action^="/crm/leads/${lead.id}/transfers"]`);
    await openForm(transferForm);
    await transferForm.locator('[name="recipient_id"]').selectOption(fixture.unrelated_sales.id);
    await transferForm.locator('[name="context"]').fill(context);
    await submitForm(page, transferForm, `/crm/leads/${lead.id}/transfers`, 'Send transfer request');
    await expect(page.getByText(context, { exact: true })).toBeVisible();
  }

  await page.context().clearCookies();
  await login(page, fixture.unrelated_sales);
  await page.goto('/crm?locale=en');
  const incoming = page.locator('.crm-inbox-list li');
  const acceptRow = incoming.filter({ hasText: acceptLead.name });
  await expect(acceptRow).toHaveCount(1);
  await acceptRow.getByRole('button', { name: 'Accept', exact: true }).click();
  await expect(page).toHaveURL(/\/crm\?locale=en$/);
  await expect(page.locator('.crm-inbox-list li').filter({ hasText: acceptLead.name })).toHaveCount(0);

  const rejectRow = page.locator('.crm-inbox-list li').filter({ hasText: rejectLead.name });
  await expect(rejectRow).toHaveCount(1);
  await rejectRow.getByRole('button', { name: 'Reject', exact: true }).click();
  await expect(page).toHaveURL(/\/crm\?locale=en$/);
  await expect(page.locator('.crm-inbox-list li').filter({ hasText: rejectLead.name })).toHaveCount(0);
  await page.goto(`/crm/leads/${acceptLead.id}?locale=en`);
  await expect(page.getByText(acceptLead.name, { exact: true })).toBeVisible();

  await page.context().clearCookies();
  await login(page, fixture.manager);
  await page.goto(`/crm/leads/${convertedLead.id}?locale=en`);
  const conversionForm = page.locator(`form[action^="/crm/leads/${convertedLead.id}/convert"]`);
  await submitForm(page, conversionForm, `/crm/leads/${convertedLead.id}/convert`, 'Convert to customer');
  await expect(page.getByText('Converted', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByText('Converted', { exact: true })).toBeVisible();

  await page.goto(`/crm/leads/${archivedLead.id}?locale=en`);
  const archiveDisclosure = page.locator('.crm-archive-confirm');
  await archiveDisclosure.locator('summary').click();
  const archiveForm = archiveDisclosure.locator(`form[action^="/crm/leads/${archivedLead.id}/archive"]`);
  await submitForm(page, archiveForm, `/crm/leads/${archivedLead.id}/archive`, 'Confirm archive');
  await expect(page.getByText('Archived', { exact: true })).toBeVisible();
  await page.reload();
  await expect(page.getByText('Archived', { exact: true })).toBeVisible();
});

test('CRM HTML and JSON are private, masked, sanitized, and fail closed for guests and limited roles', async ({ page }) => {
  const fixture = readFixture();
  const guestHtml = await page.goto('/crm?locale=en');
  expect(guestHtml?.status()).toBe(401);
  expectPrivateNoStore(guestHtml?.headers()['cache-control']);
  expect(await page.locator('body').innerText()).toContain('Sign in to continue.');

  const guestJson = await page.request.get(`${baseURL}/crm/leads`, { headers: { Accept: 'application/json' } });
  expect(guestJson.status()).toBe(401);
  expectPrivateNoStore(guestJson.headers()['cache-control']);
  expect(await guestJson.json()).toMatchObject({ code: 'unauthenticated', message: 'Authentication is required.' });

  await login(page, fixture.manager);
  const lead = await createLead(page, fixture, 'privacy');
  const html = await page.goto(`/crm/leads/${lead.id}?locale=en`);
  expect(html?.status()).toBe(200);
  expectPrivateNoStore(html?.headers()['cache-control']);
  await expectMasked(page, lead.email);

  const json = await page.request.get(`${baseURL}/crm/leads?per_page=25`, { headers: { Accept: 'application/json' } });
  expect(json.status()).toBe(200);
  expectPrivateNoStore(json.headers()['cache-control']);
  const body = await json.json() as { data: unknown[]; meta: { total: number } };
  expect(body.data.length).toBe(25);
  expect(body.meta.total).toBeGreaterThanOrEqual(300);
  expect(JSON.stringify(body)).not.toContain(lead.email);
  const managerCsrf = await page.locator('form input[name="_token"]').first().inputValue();
  const invalidCreate = await page.request.post(`${baseURL}/crm/leads`, {
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': managerCsrf, Referer: `${baseURL}/crm/leads/create?locale=en` },
    form: { name: '', organization: '', source: '', stage_id: '' },
  });
  expect(invalidCreate.status()).toBe(422);
  expectPrivateNoStore(invalidCreate.headers()['cache-control']);
  expect(await invalidCreate.json()).toMatchObject({ code: 'validation_failed', message: 'The submitted data is invalid.' });

  await page.context().clearCookies();
  await login(page, fixture.sales);
  const restrictedCreate = await page.goto('/crm/leads/create?locale=en');
  expect(restrictedCreate?.status()).toBe(403);
  expect(await page.locator('body').innerText()).toContain('You do not have permission to complete this action.');
  await page.goto(`/crm/leads/${fixture.assigned_lead_id}?locale=en`);
  await expect(page.getByRole('link', { name: 'Edit lead', exact: true })).toHaveCount(0);
  await expect(page.locator(`form[action^="/crm/leads/${fixture.assigned_lead_id}/reveal"]`)).toHaveCount(0);
  await expect(page.locator(`form[action^="/crm/leads/${fixture.assigned_lead_id}/archive"]`)).toHaveCount(0);
  const csrf = await page.locator('form input[name="_token"]').first().inputValue();
  const hiddenLead = await page.goto(`/crm/leads/${fixture.unrelated_lead_id}?locale=en`);
  expect(hiddenLead?.status()).toBe(404);
  expectPrivateNoStore(hiddenLead?.headers()['cache-control']);
  const hiddenJson = await page.request.get(`${baseURL}/crm/leads/${fixture.unrelated_lead_id}`, { headers: { Accept: 'application/json' } });
  expect(hiddenJson.status()).toBe(404);
  expect(await hiddenJson.json()).toMatchObject({ code: 'not_found', message: 'Resource not found.' });
  const deniedArchive = await page.request.post(`${baseURL}/crm/leads/${fixture.assigned_lead_id}/archive`, {
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, Referer: `${baseURL}/crm/leads/${fixture.assigned_lead_id}?locale=en` },
    form: { version: '0' },
  });
  expect(deniedArchive.status()).toBe(403);
  expectPrivateNoStore(deniedArchive.headers()['cache-control']);
  expect(await deniedArchive.json()).toMatchObject({ code: 'forbidden', message: 'The action is not permitted.' });

  await page.context().clearCookies();
  await login(page, fixture.manager);
  await page.goto(`/crm/leads/${fixture.assigned_lead_id}?locale=en`);
  await expect(page.getByRole('link', { name: 'Edit lead', exact: true })).toBeVisible();
  await expect(page.getByText('Archived', { exact: true })).toHaveCount(0);
});
