import { Buffer } from 'node:buffer';
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, type Page } from '@playwright/test';

type UserFixture = { email: string; password: string };
type CrmFixture = {
  owner_suffix: string;
  assigned_lead_id: string;
  manager: UserFixture;
};

const fixturePath = resolve(process.env.ARASYA_REFERENCE_FIXTURE || 'storage/framework/testing/crm-fixture.json');
const salesEnabled = /^(1|true|yes|on)$/i.test(process.env.ARASYA_SYNTHETIC_SALES || '');

test.describe('consistency regressions', () => {
  test.use({ storageState: process.env.ARASYA_BROWSER_AUTH_STATE });
  test('lead choices retain the selected lead and pipeline context across search and pages', async ({ page }) => {
    const query = { locale: 'en', currency: 'SAR', stage: 'proposal', probability_min: '70', from: '2026-10-01', to: '2026-10-31', selected: '01M00000000000000000000999', closing_page: '1', contact_page: '2', proposal_page: '2' };
    await page.goto(`/crm/pipeline?${new URLSearchParams(query)}`);
    await page.getByRole('button', { name: 'Add opportunity', exact: true }).click();
    const dialog = page.locator('#opportunity-create');
    const select = dialog.locator('[name=lead_id]');
    const option = select.locator('option[value]:not([value=""])').first();
    const id = await option.getAttribute('value');
    await select.selectOption(id!);
    await dialog.getByLabel('Opportunity title', { exact: true }).fill('Transient opportunity title');
    await dialog.getByRole('searchbox', { name: 'Search leads', exact: true }).fill('');
    const searchRequest = page.waitForRequest(request => request.resourceType() === 'fetch' && new URL(request.url()).pathname === '/crm/pipeline');
    await dialog.getByRole('button', { name: 'Search', exact: true }).click();
    for (const [key, value] of Object.entries(query)) expect(new URL((await searchRequest).url()).searchParams.get(key)).toBe(value);
    await expect(dialog.getByLabel('Opportunity title', { exact: true })).toHaveValue('Transient opportunity title');
    await expect(dialog.locator('[data-crm-outcome-picker]')).not.toHaveAttribute('aria-busy', 'true');
    const nextPage = dialog.locator('[data-crm-outcome-pages] a[href*="lead_page=2"]').first();
    for (const [key, value] of Object.entries(query)) expect(new URL((await nextPage.getAttribute('href'))!).searchParams.get(key)).toBe(value);
    await nextPage.click();
    await expect(dialog.locator('[data-crm-outcome-pages]')).toContainText('26');
    await expect(dialog).toBeVisible();
    await expect(select).toHaveValue(id!);
    await expect(dialog.getByLabel('Opportunity title', { exact: true })).toHaveValue('Transient opportunity title');
    for (const [key, value] of Object.entries(query)) expect(new URL(page.url()).searchParams.get(key)).toBe(value);
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
  });
  test('activity outcome search and option pages retain modal fields and selection', async ({ page }) => {
    const lead = process.env.ARASYA_CONSISTENCY_LEAD!;
    await page.goto(`/crm/leads/${lead}/workspace/activities?locale=en`);
    await page.getByRole('button', { name: 'Add activity', exact: true }).click();
    const dialog = page.locator('#crm-workspace-create');
    await dialog.locator('[name=next_step]').fill('Factory verified activity');
    await dialog.getByLabel('Note', { exact: true }).fill('PRIVATE_TRANSIENT_ACTIVITY_NOTE');
    await dialog.getByRole('searchbox', { name: 'Search outcomes', exact: true }).fill('Factory selector');
    await dialog.getByRole('button', { name: 'Find outcome', exact: true }).click();
    await expect(dialog.locator('[data-crm-outcome-picker]')).not.toHaveAttribute('aria-busy', 'true');
    await expect(dialog.locator('[data-crm-outcome-error]')).toBeHidden();
    await expect(dialog.locator('[data-crm-outcome-pages]')).toContainText('of 30 results');
    const outcome = dialog.getByLabel('Outcome', { exact: true });
    await expect(outcome.locator('option')).toContainText(['Factory selector']);
    const selected = await outcome.locator('option[value]:not([value=""])').first().getAttribute('value');
    await outcome.selectOption(selected!);
    await dialog.locator('[data-crm-outcome-pages] a[href*="outcome_page=2"]').first().click();
    await expect(dialog.locator('[data-crm-outcome-pages]')).toContainText('26');
    await expect(dialog).toBeVisible();
    await expect(outcome).toHaveValue(selected!);
    await expect(dialog.getByLabel('Note', { exact: true })).toHaveValue('PRIVATE_TRANSIENT_ACTIVITY_NOTE');
    expect(page.url()).not.toContain('PRIVATE_TRANSIENT');
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await page.getByRole('button', { name: 'Add activity', exact: true }).click();
    await expect(dialog.locator('[name=next_step]')).toHaveValue('Factory verified activity');
    await dialog.getByRole('button', { name: 'Record activity', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/crm/leads/${lead}\\?locale=en$`));
    await expect(page.locator('.crm-overview-events')).toContainText('Factory verified activity');
    await page.goto(`/crm/leads/${lead}/workspace/activities?locale=en`);
    await page.getByRole('button', { name: 'Add activity', exact: true }).click();
    await dialog.locator('[name=next_step]').fill('Invalid activity');
    await dialog.getByLabel('Note', { exact: true }).fill('DO_NOT_FLASH_PRIVATE_NOTE');
    await dialog.getByLabel('Activity time with timezone', { exact: true }).fill('invalid');
    await dialog.getByLabel('Outcome', { exact: true }).selectOption(await dialog.locator('[name=outcome_id] option[value]:not([value=""])').first().getAttribute('value') as string);
    const response = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/activities'));
    await dialog.getByRole('button', { name: 'Record activity', exact: true }).click();
    expect((await response).status()).toBe(422);
    await expect(page.locator('body')).not.toContainText('DO_NOT_FLASH_PRIVATE_NOTE');
  });
  test('shared shell dimensions match across CRM screens', async ({ page }, testInfo) => {
    const lead = process.env.ARASYA_CONSISTENCY_LEAD!;
    const routes = ['/crm', '/crm/leads', `/crm/leads/${lead}`, `/crm/leads/${lead}/workspace/activities`, `/crm/leads/${lead}/workspace/notes`, `/crm/leads/${lead}/workspace/tasks`, `/crm/leads/${lead}/files`, '/crm/pipeline'];
    for (const locale of ['ar', 'en']) {
      let baseline: unknown;
      for (const [index, route] of routes.entries()) {
        expect((await page.goto(`${route}?locale=${locale}`))?.status()).toBe(200);
        const geometry = await page.evaluate(() => {
          const css = (selector: string, properties: string[]) => {
            const style = getComputedStyle(document.querySelector(selector)!);
            return properties.map(property => style.getPropertyValue(property));
          };
          return [css('.dashboard-sidebar', ['width','padding-inline-start','padding-inline-end']), css('.dashboard-topbar', ['height','padding-block-start','padding-block-end']), css('.dashboard-brand > svg', ['width','height']), css('.nav-link', ['padding-inline-start','padding-inline-end'])];
        });
        if (index === 0) baseline = geometry;
        else expect(geometry, route).toEqual(baseline);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        if (index === 2 && testInfo.project.name === 'mobile-chromium') {
          for (const metric of await page.locator('.crm-reference-header--overview .crm-reference-metric').all()) {
            const icon = await metric.locator('.ui-icon').boundingBox();
            const label = await metric.locator('span').boundingBox();
            expect(icon!.y + icon!.height).toBeLessThanOrEqual(label!.y);
          }
        }
        for (const theme of ['light', 'dark']) {
          await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
          await page.screenshot({ path: testInfo.outputPath(`shell-${locale}-${index}-${theme}.png`) });
        }
      }
    }
  });
});
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

async function expectLeadIdentity(page: Page, fixture: CrmFixture): Promise<void> {
  const identity = page.locator('.crm-reference-header');
  await expect(identity).toHaveCount(1);
  await expect(identity.locator('h2')).toHaveText(`Synthetic CRM Organization 001 ${fixture.owner_suffix}`);
  await expect(identity).toContainText(`Synthetic CRM Prospect 001 ${fixture.owner_suffix}`);
  await expect(identity).toContainText(fixture.assigned_lead_id);
}

async function expectWorkspaceShell(page: Page, fixture: CrmFixture, locale: 'ar' | 'en'): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('.dashboard-sidebar')).toHaveCount(1);
  await expect(page.locator('.dashboard-topbar')).toHaveCount(1);
  await expect(page.getByRole('main')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  await expectLeadIdentity(page, fixture);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
}

test('lead overview stays compact across directions themes and mobile with keyboard accessible dialogs', async ({ page }) => {
  const fixture = readFixture();
  await login(page, fixture.manager);
  for (const locale of ['ar', 'en'] as const) {
    for (const width of [1672, 390]) {
      await page.setViewportSize({ width, height: 941 });
      const response = await page.goto(`/crm/leads/${fixture.assigned_lead_id}?locale=${locale}`);
      expect(response?.status()).toBe(200);
      await expectWorkspaceShell(page, fixture, locale);
      await expect(page.locator('[data-lead-overview]')).toHaveCount(1);
      const events = page.locator('.crm-overview-event');
      expect(await events.count()).toBeLessThanOrEqual(6);
      if (width === 1672) {
        const information = await page.locator('.crm-overview-information').boundingBox();
        const timeline = await page.locator('.crm-overview-timeline').boundingBox();
        expect(Math.abs(information!.y - timeline!.y)).toBeLessThan(2);
        expect(information!.width).toBeGreaterThan(timeline!.width);
      }
      for (const theme of ['dark', 'light']) {
        await page.locator('[data-theme-toggle]').click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
      }
      for (const id of ['crm-lead-actions', 'crm-lead-history', 'crm-lead-activity-detail', 'crm-lead-convert']) {
        const opener = page.locator(`[data-open-dialog="${id}"]`);
        if (await opener.count() === 0) continue;
        await opener.focus();
        await page.keyboard.press('Enter');
        const dialog = page.locator(`#${id}`);
        await expect(dialog).toBeVisible();
        expect(await dialog.evaluate((element: HTMLDialogElement) => element.matches(':modal'))).toBe(true);
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await expect(opener).toBeFocused();
      }
      const activitiesLink = page.locator('.crm-overview-panel-action').first();
      await expect(activitiesLink).toHaveAttribute('href', `/crm/leads/${fixture.assigned_lead_id}/workspace/activities?locale=${locale}`);
    }
  }
});

test('reference workspace navigation and file fields preserve the lead header in RTL and LTR without viewport overflow', async ({ page }) => {
  const fixture = readFixture();
  await login(page, fixture.manager);

  const themeToggle = page.locator('[data-theme-toggle]');
  await themeToggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await themeToggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

  for (const locale of ['ar', 'en'] as const) {
    const activityUrl = `/crm/leads/${fixture.assigned_lead_id}/workspace/activities?locale=${locale}`;
    const response = await page.goto(activityUrl);
    expect(response?.status()).toBe(200);
    await expectWorkspaceShell(page, fixture, locale);

    const sectionNavigation = page.locator('nav.crm-workspace-nav');
    await expect(sectionNavigation).toHaveCount(1);
    for (const section of ['activities', 'notes', 'tasks'] as const) {
      const link = sectionNavigation.locator(`a[href="/crm/leads/${fixture.assigned_lead_id}/workspace/${section}?locale=${locale}"]`);
      await expect(link).toHaveCount(1);
      await link.click();
      const currentUrl = new URL(page.url());
      expect(currentUrl.pathname).toBe(`/crm/leads/${fixture.assigned_lead_id}/workspace/${section}`);
      expect(currentUrl.searchParams.get('locale')).toBe(locale);
      await expect(sectionNavigation.locator('a[aria-current="page"]')).toHaveAttribute(
        'href', `/crm/leads/${fixture.assigned_lead_id}/workspace/${section}?locale=${locale}`,
      );
      await expectWorkspaceShell(page, fixture, locale);
    }

    const filesLink = sectionNavigation.getByRole('link', { name: locale === 'en' ? 'Files' : 'الملفات', exact: true });
    await expect(filesLink).toHaveAttribute('href', `/crm/leads/${fixture.assigned_lead_id}/files?locale=${locale}`);
    await filesLink.click();
    await expect(page).toHaveURL(new RegExp(`/crm/leads/${fixture.assigned_lead_id}/files\\?locale=${locale}$`));
    await expect(sectionNavigation.locator('a[aria-current="page"]')).toHaveAttribute(
      'href', `/crm/leads/${fixture.assigned_lead_id}/files?locale=${locale}`,
    );
    await expectWorkspaceShell(page, fixture, locale);

    const uploadForm = page.locator(`form[action^="/crm/leads/${fixture.assigned_lead_id}/files"]`);
    await expect(uploadForm).toHaveCount(1);
    await expect(uploadForm.locator('#crm-upload')).toHaveAttribute('type', 'file');
    await expect(uploadForm.locator('label[for="crm-upload"]')).toHaveCount(1);
    await expect(uploadForm.locator('#crm-file-description')).toBeVisible();
    await expect(uploadForm.locator('#crm-file-category')).toBeVisible();

    if (salesEnabled) {
      const quotationsLink = sectionNavigation.getByRole('link', { name: locale === 'en' ? 'Quotations' : 'العروض', exact: true });
      await expect(quotationsLink).toHaveAttribute('href', `/crm/leads/${fixture.assigned_lead_id}/quotations?locale=${locale}`);
      await quotationsLink.click();
      const quotationsUrl = new URL(page.url());
      expect(quotationsUrl.pathname).toBe(`/crm/leads/${fixture.assigned_lead_id}/quotations`);
      expect(quotationsUrl.searchParams.get('locale')).toBe(locale);
      await expect(sectionNavigation.locator('a[aria-current="page"]')).toHaveAttribute(
        'href', `/crm/leads/${fixture.assigned_lead_id}/quotations?locale=${locale}`,
      );
      await expectWorkspaceShell(page, fixture, locale);
      await expect(page.getByText(locale === 'en'
        ? 'Non-binding synthetic drafts — approval and sending are disabled'
        : 'مسودات تجريبية غير ملزمة — الاعتماد والإرسال معطلان', { exact: true })).toBeVisible();
    }
  }
});

test('pipeline lead search retains its query, opens the create dialog, and shows the organization label', async ({ page }) => {
  test.skip(!salesEnabled, 'Requires ARASYA_SYNTHETIC_SALES=true on both the test runner and isolated server.');
  const fixture = readFixture();
  await login(page, fixture.manager);

  const response = await page.goto('/crm/pipeline?locale=en');
  expect(response?.status()).toBe(200);
  const opener = page.locator('[data-open-dialog="opportunity-create"]');
  await opener.click();
  const dialog = page.locator('#opportunity-create');
  await expect(dialog).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);

  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(opener).toBeFocused();
  await opener.click();
  await expect(dialog).toBeVisible();

  const leadQuery = `Synthetic CRM Prospect 001 ${fixture.owner_suffix}`;
  const search = dialog.locator('input[name="lead_q"]');
  await search.fill(leadQuery);
  await dialog.getByRole('button', { name: 'Search', exact: true }).click();
  await expect(page).toHaveURL(/\/crm\/pipeline\?/);

  const currentUrl = new URL(page.url());
  expect(currentUrl.searchParams.get('lead_q')).toBe(leadQuery);
  await expect(dialog).toBeVisible();
  await expect(search).toHaveValue(leadQuery);
  await expect(dialog.locator('#create-lead option[value=""]')).toHaveText('Choose a lead');
  await expect(dialog.locator(`#create-lead option[value="${fixture.assigned_lead_id}"]`))
    .toHaveText(`Synthetic CRM Organization 001 ${fixture.owner_suffix}`);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
});

test('synthetic quotation draft can be created and opened as a non-binding print preview', async ({ page }) => {
  test.skip(!salesEnabled, 'Requires ARASYA_SYNTHETIC_SALES=true on both the test runner and isolated server.');
  const fixture = readFixture();
  await login(page, fixture.manager);

  const leadId = fixture.assigned_lead_id;
  const title = `Synthetic browser quotation ${randomUUID()}`;
  const formResponse = await page.goto(`/crm/leads/${leadId}/quotations/create?locale=en`);
  expect(formResponse?.status()).toBe(200);
  await expectLeadIdentity(page, fixture);

  const form = page.locator('[data-quotation-form]');
  await expect(form).toHaveCount(1);
  await form.locator('[name="title"]').fill(title);
  await form.locator('[name="currency"]').fill('USD');

  const lines = form.locator('[data-quote-line]');
  await expect(lines).toHaveCount(1);
  const first = lines.nth(0);
  await first.locator('[data-line-field="description"]').fill('Synthetic browser service');
  await first.locator('[data-line-field="quantity"]').fill('2');
  await first.locator('[data-line-field="unit_price"]').fill('10.25');
  await first.locator('[data-line-field="discount"]').fill('0.50');
  await first.locator('[data-line-field="tax_rate"]').fill('15');

  await form.locator('[data-add-line]').click();
  await expect(lines).toHaveCount(2);
  const second = lines.nth(1);
  await second.locator('[data-line-field="description"]').fill('Synthetic browser accessory');
  await second.locator('[data-line-field="quantity"]').fill('1');
  await second.locator('[data-line-field="unit_price"]').fill('4.00');
  await second.locator('[data-line-field="discount"]').fill('0');
  await second.locator('[data-line-field="tax_rate"]').fill('0');

  const post = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === `/crm/leads/${leadId}/quotations`);
  await form.getByRole('button', { name: 'Save draft', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(new RegExp(`/crm/leads/${leadId}/quotations\\?`));

  const selectedId = new URL(page.url()).searchParams.get('selected');
  expect(selectedId).toBeTruthy();
  const row = page.locator('table tbody tr').filter({ hasText: title });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('Draft');
  await expect(row).toContainText('USD');
  await expect(page.getByRole('link', { name: 'Print / PDF preview', exact: true })).toBeVisible();

  await page.getByRole('link', { name: 'Print / PDF preview', exact: true }).click();
  const previewUrl = new URL(page.url());
  expect(previewUrl.pathname).toBe(`/crm/leads/${leadId}/quotations/${selectedId}/preview`);
  expect(previewUrl.searchParams.get('locale')).toBe('en');
  const preview = page.locator('.quotation-print-preview');
  await expect(preview).toBeVisible();
  await expect(preview).toContainText(title);
  await expect(preview).toContainText('Synthetic browser service');
  await expect(preview).toContainText('Synthetic browser accessory');
  await expect(preview).toContainText('Non-binding synthetic draft — not approved and must not be sent to a customer');
  await expect(preview.getByRole('button', { name: /approve|send/i })).toHaveCount(0);
  await expect(preview.getByRole('link', { name: /approve|send/i })).toHaveCount(0);
  const totals = preview.locator('.crm-contact-list');
  await expect(totals).toContainText('24.50 USD');
  await expect(totals).toContainText('0.50 USD');
  await expect(totals).toContainText(/3(?:\.0+)?\s*USD/);
  await expect(totals).toContainText(/27(?:\.0+)?\s*USD/);
});

test('synthetic lead file upload can be downloaded with its original content', async ({ page }) => {
  const fixture = readFixture();
  await login(page, fixture.manager);

  const leadId = fixture.assigned_lead_id;
  const fileName = `synthetic-browser-${randomUUID()}.txt`;
  const fileContents = `Synthetic CRM browser upload ${randomUUID()}`;
  const response = await page.goto(`/crm/leads/${leadId}/files?locale=en`);
  expect(response?.status()).toBe(200);

  const form = page.locator(`form[action^="/crm/leads/${leadId}/files"]`);
  await expect(form).toHaveCount(1);
  await form.locator('#crm-upload').setInputFiles({
    name: fileName,
    mimeType: 'text/plain',
    buffer: Buffer.from(fileContents, 'utf8'),
  });
  await form.locator('#crm-file-description').fill('Synthetic browser upload regression');
  await form.locator('#crm-file-category').fill('Synthetic QA');

  const uploadResponse = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === `/crm/leads/${leadId}/files`);
  await form.getByRole('button', { name: 'Upload file', exact: true }).click();
  expect([302, 303]).toContain((await uploadResponse).status());
  await expect(page).toHaveURL(new RegExp(`/crm/leads/${leadId}/files\\?locale=en$`));

  const row = page.locator('table tbody tr').filter({ hasText: fileName });
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('Synthetic browser upload regression');
  await expect(row).toContainText('Synthetic QA');

  const downloadLink = row.getByRole('link', { name: 'Download', exact: true });
  const downloadPath = new URL(await downloadLink.getAttribute('href') || '', page.url()).pathname;
  const downloadResponsePromise = page.waitForResponse((candidate) => candidate.request().method() === 'GET'
    && new URL(candidate.url()).pathname === downloadPath);
  const downloadPromise = page.waitForEvent('download');
  await downloadLink.click();
  const [downloadResponse, download] = await Promise.all([downloadResponsePromise, downloadPromise]);
  expect(downloadResponse.status()).toBe(200);
  expect(download.suggestedFilename()).toBe(fileName);

  const stream = await download.createReadStream();
  expect(stream).not.toBeNull();
  const chunks: Buffer[] = [];
  for await (const chunk of stream!) {
    chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
  }
  expect(Buffer.concat(chunks).toString('utf8')).toBe(fileContents);
});
