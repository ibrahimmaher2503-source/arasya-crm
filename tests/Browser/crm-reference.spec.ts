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

test('sales s1 keyed opportunity retains history across reload', async ({ page }, testInfo) => {
  test.skip(!salesEnabled, 'Requires ARASYA_SYNTHETIC_SALES=true on both the test runner and isolated server.');
  test.setTimeout(90_000);
  const fixture = readFixture();
  const title = `Synthetic keyed opportunity ${randomUUID()}`;
  const updatedTitle = `Updated ${title}`;
  await login(page, fixture.manager);
  await page.setViewportSize({ width: 1672, height: 941 });
  expect((await page.goto('/crm/pipeline?locale=en'))?.status()).toBe(200);

  await page.getByRole('button', { name: 'Add opportunity', exact: true }).click();
  const createDialog = page.locator('#opportunity-create');
  const form = createDialog.locator('form[method="post"]');
  const submissionKey = await form.locator('[name=submission_key]').inputValue();
  const csrfToken = await form.locator('[name=_token]').inputValue();
  expect(submissionKey).toMatch(/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i);

  const businessPayload = {
    lead_id: fixture.assigned_lead_id,
    title,
    amount: '10.25',
    currency: 'SAR',
    stage: 'lead',
    probability: 40,
    expected_close: '2026-10-31',
  };
  const payload = { ...businessPayload, submission_key: submissionKey };
  const leakedHash = /\b[a-f0-9]{64}\b/i;
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken };
  const endpoint = new URL('/crm/pipeline', page.url()).toString();
  const created = await page.request.post(endpoint, { headers, data: payload });
  expect(created.status()).toBe(201);
  const createdBody = await created.json();
  const opportunityId = createdBody.data.id as string;
  expect(opportunityId).toBeTruthy();
  expect(createdBody.data.version).toBe(0);
  expect(createdBody.data).not.toHaveProperty('submission_key');
  expect(createdBody.data).not.toHaveProperty('submission_hash');
  expect(JSON.stringify(createdBody)).not.toContain(submissionKey);
  expect(JSON.stringify(createdBody)).not.toMatch(leakedHash);

  const replayed = await page.request.post(endpoint, { headers, data: payload });
  expect(replayed.status()).toBe(201);
  const replayedBody = await replayed.json();
  expect(replayedBody.data.id).toBe(opportunityId);
  expect(replayedBody.data).not.toHaveProperty('submission_key');
  expect(replayedBody.data).not.toHaveProperty('submission_hash');
  expect(JSON.stringify(replayedBody)).not.toContain(submissionKey);
  expect(JSON.stringify(replayedBody)).not.toMatch(leakedHash);

  const editPayload = { ...businessPayload, title: updatedTitle, version: 0 };
  const updated = await page.request.patch(`${endpoint}/${opportunityId}`, { headers, data: editPayload });
  expect(updated.status()).toBe(200);
  const updatedBody = await updated.json();
  expect(updatedBody.data).toMatchObject({ id: opportunityId, title: updatedTitle, version: 1 });
  expect(updatedBody.data).not.toHaveProperty('submission_key');
  expect(updatedBody.data).not.toHaveProperty('submission_hash');
  expect(JSON.stringify(updatedBody)).not.toContain(submissionKey);
  expect(JSON.stringify(updatedBody)).not.toMatch(leakedHash);

  for (const locale of ['ar', 'en'] as const) {
    for (const { width, height } of [
      { width: 1672, height: 941 }, { width: 768, height: 1024 }, { width: 390, height: 844 },
    ]) {
      await page.setViewportSize({ width, height });
      const response = await page.goto(`/crm/pipeline?locale=${locale}&selected=${opportunityId}`);
      expect(response?.status()).toBe(200);
      expect((await page.reload())?.status()).toBe(200);
      await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
      const history = page.locator('[data-opportunity-history]');
      await expect(history).toBeVisible();
      await expect(page.getByRole('heading', {
        name: locale === 'en' ? 'Opportunity history' : 'سجل تغييرات الفرصة', exact: true,
      })).toBeVisible();
      await expect(history).toContainText(title);
      await expect(history).toContainText(updatedTitle);
      const rows = history.locator('tbody tr');
      await expect(rows).toHaveCount(2);
      await expect(rows.nth(0)).toContainText(updatedTitle);
      await expect(rows.nth(1)).toContainText(title);
      await expect(page.locator('#opportunity-edit [name=version]')).toHaveValue('1');

      const themeToggle = page.locator('[data-theme-toggle]');
      await expect(themeToggle).toHaveCount(1);
      for (const theme of ['light', 'dark'] as const) {
        const currentTheme = await page.locator('html').getAttribute('data-theme');
        if ((theme === 'dark' && currentTheme !== 'dark') || (theme === 'light' && currentTheme === 'dark')) {
          await themeToggle.click();
        }
        if (theme === 'dark') await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        else expect([null, 'light']).toContain(await page.locator('html').getAttribute('data-theme'));
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
        await history.scrollIntoViewIfNeeded();
        await page.screenshot({ path: testInfo.outputPath(`sales-s1-history-${locale}-${width}-${theme}.png`) });
      }

      await page.getByRole('button', { name: locale === 'en' ? 'Add opportunity' : 'إضافة صفقة', exact: true }).click();
      await expect(createDialog).toBeVisible();
      const newFormKey = await createDialog.locator('[name=submission_key]').inputValue();
      expect(newFormKey).toMatch(/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i);
      expect(newFormKey).not.toBe(submissionKey);
      const markupWithoutNewKey = await page.locator('body').evaluate((body) => {
        const copy = body.cloneNode(true) as HTMLElement;
        copy.querySelectorAll('[name="submission_key"]').forEach((input) => input.remove());
        return copy.innerHTML;
      });
      expect(markupWithoutNewKey).not.toContain(submissionKey);
      expect(markupWithoutNewKey).not.toMatch(leakedHash);
      await page.keyboard.press('Escape');
      await expect(createDialog).not.toBeVisible();
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    }
  }
});

test('sales s1 customer form persists private work context and directory', async ({ page }, testInfo) => {
  test.skip(!salesEnabled, 'Requires isolated synthetic Sales runtime.');
  const fixture = readFixture();
  await login(page, fixture.manager);
  await page.goto('/crm/pipeline?locale=en');
  const token = await page.locator('#opportunity-create [name=_token]').inputValue();
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': token };
  const lead = await page.request.get(`/crm/leads/${fixture.assigned_lead_id}`, { headers });
  expect(lead.status()).toBe(200);
  const converted = await page.request.post(`/crm/leads/${fixture.assigned_lead_id}/convert`, {
    headers, data: { version: (await lead.json()).data.version },
  });
  expect(converted.status()).toBe(200);
  const customerId = (await converted.json()).data.customer_id;
  await page.getByRole('button', { name: 'Add opportunity', exact: true }).click();
  await page.getByRole('link', { name: 'Customer source', exact: true }).click();
  const dialog = page.locator('#opportunity-create');
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('link', { name: 'Customer source', exact: true })).toHaveAttribute('aria-current', 'page');
  const form = dialog.locator('form[method=post]');
  await form.locator('[name=customer_id]').selectOption(customerId);
  const title = `Synthetic customer opportunity ${randomUUID()}`;
  await form.locator('[name=title]').fill(title);
  await form.locator('[name=amount]').fill('12.50');
  await form.locator('[name=work_type]').selectOption('site_inspection');
  await form.locator('[name=requirements]').fill('Private site requirements');
  await form.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page).toHaveURL(/selected=/);
  const id = new URL(page.url()).searchParams.get('selected');
  const editor = page.locator('#opportunity-edit');
  await expect(editor.locator('[name=requirements]')).toHaveValue('Private site requirements');
  await editor.locator('[name=work_type]').selectOption('maintenance');
  await editor.locator('[name=requirements]').fill('Updated private requirements');
  await editor.getByRole('button', { name: 'Save', exact: true }).click();
  await page.reload();
  await expect(editor.locator('[name=work_type]')).toHaveValue('maintenance');
  await expect(editor.locator('[name=requirements]')).toHaveValue('Updated private requirements');
  await expect(page.locator('[data-opportunity-history]')).not.toContainText('private requirements');
  await expect(page.locator('[data-opportunity-history]')).not.toContainText('Private site requirements');
  for (const locale of ['ar', 'en']) {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/crm/pipeline?locale=${locale}&view=directory&q=${encodeURIComponent(title)}`);
    await expect(page.locator('table tbody tr')).toHaveCount(1);
    await expect(page.locator('table')).toContainText(title);
    await expect(page.locator('body')).not.toContainText('Updated private requirements');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await page.locator('table').scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath(`sales-s1-directory-${locale}-390.png`) });
    await page.locator(`a[href*="selected=${id}"]`).click();
    await expect(page.locator('[data-opportunity-history]')).toBeVisible();
  }
  for (let index = 0; index < 25; index++) {
    const response = await page.request.post('/crm/pipeline', { headers, data: {
      customer_id: customerId, title: `${title} ${index}`, amount: '1', currency: 'SAR',
      stage: 'lead', probability: 0, submission_key: randomUUID(),
    } });
    expect(response.status()).toBe(201);
  }
  await page.goto(`/crm/pipeline?locale=en&q=${encodeURIComponent(title)}`);
  await page.getByRole('link', { name: 'Opportunity directory', exact: true }).click();
  await expect(page.getByRole('link', { name: 'Opportunity directory', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('table tbody tr')).toHaveCount(25);
  await page.locator('a[href*="opportunity_page=2"]').first().click();
  await expect(page.locator('table tbody tr')).toHaveCount(1);
  await expect(page.locator('table')).toContainText(title);
  expect(new URL(page.url()).searchParams.get('q')).toBe(title);
  expect(new URL(page.url()).searchParams.get('view')).toBe('directory');
});

test('sales s1 proposed workflow denies commercial closure', async ({ page }) => {
  test.skip(!salesEnabled, 'Requires ARASYA_SYNTHETIC_SALES=true on both the test runner and isolated server.');
  test.setTimeout(75_000);
  const fixture = readFixture();
  const title = `Synthetic workflow opportunity ${randomUUID()}`;
  await login(page, fixture.manager);
  expect((await page.goto('/crm/pipeline?locale=en'))?.status()).toBe(200);
  await page.getByRole('button', { name: 'Add opportunity', exact: true }).click();
  const createForm = page.locator('#opportunity-create form[method="post"]');
  const submissionKey = await createForm.locator('[name=submission_key]').inputValue();
  expect(submissionKey).toMatch(/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i);
  const business = { lead_id: fixture.assigned_lead_id, title, amount: '10.25', currency: 'SAR', stage: 'lead', probability: 40, expected_close: '2026-10-31' };
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': await createForm.locator('[name=_token]').inputValue() };
  const created = await page.request.post(new URL('/crm/pipeline', page.url()).toString(), {
    headers, data: { ...business, submission_key: submissionKey },
  });
  expect(created.status()).toBe(201);
  const id = (await created.json()).data.id as string;
  expect(id).toBeTruthy();

  await page.goto(`/crm/pipeline?locale=en&selected=${id}`);
  const transition = page.locator('[data-opportunity-transition]');
  const stage = transition.locator('[name=target_stage]');
  const values = async () => stage.locator('option').evaluateAll(options => options.map(option => (option as HTMLOptionElement).value).filter(Boolean));
  expect(await values()).toEqual(['contact']);
  const action = new URL((await transition.getAttribute('action'))!, page.url()).toString();
  const actionPath = new URL(action).pathname;
  const forward = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === actionPath);
  await stage.selectOption('contact');
  await transition.getByRole('button', { name: 'Move opportunity', exact: true }).click();
  expect([302, 303]).toContain((await forward).status());
  expect((await page.reload())?.status()).toBe(200);
  const history = page.locator('[data-opportunity-history] tbody tr');
  await expect(history).toHaveCount(2);
  await expect(page.locator('[data-opportunity-transition] [name=version]')).toHaveValue('1');

  const csrf = await page.locator('[data-opportunity-transition] [name=_token]').inputValue();
  const key = await page.locator('[data-opportunity-transition] [name=submission_key]').inputValue();
  expect(key).toMatch(/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i);
  const apiHeaders = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
  for (const [outcome, submissionKey] of [['won', key], ['lost', randomUUID()]]) {
    expect((await page.request.post(action, {
      headers: apiHeaders, data: { target_stage: 'closed', outcome, version: 1, submission_key: submissionKey },
    })).status()).toBe(403);
  }
  expect((await page.request.post(new URL(`/crm/pipeline/${id}/reopen`, page.url()).toString(), {
    headers: apiHeaders, data: { reason: 'Reopen request', version: 1, submission_key: randomUUID() },
  })).status()).toBe(403);
  expect((await page.request.patch(new URL(`/crm/pipeline/${id}`, page.url()).toString(), {
    headers: apiHeaders, data: { ...business, stage: 'closed', version: 1 },
  })).status()).toBe(422);
  expect((await page.reload())?.status()).toBe(200);
  await expect(history).toHaveCount(2);
  await expect(page.locator('[data-opportunity-transition] [name=version]')).toHaveValue('1');

  const backForm = page.locator('[data-opportunity-transition]');
  const backStage = backForm.locator('[name=target_stage]');
  expect((await backStage.locator('option').evaluateAll(options => options.map(option => (option as HTMLOptionElement).value).filter(Boolean))).sort()).toEqual(['lead', 'proposal']);
  await backStage.selectOption('lead');
  await backForm.locator('[name=reason]').fill('Correcting the previous stage');
  const backward = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === actionPath);
  await backForm.getByRole('button', { name: 'Move opportunity', exact: true }).click();
  expect([302, 303]).toContain((await backward).status());
  expect((await page.reload())?.status()).toBe(200);
  await expect(history).toHaveCount(3);
  await expect(page.locator('[data-opportunity-transition] [name=version]')).toHaveValue('2');

  expect((await page.request.post(action, {
    headers: apiHeaders, data: { target_stage: 'proposal', reason: '', version: 2, submission_key: randomUUID() },
  })).status()).toBe(422);
  expect((await page.reload())?.status()).toBe(200);
  await expect(history).toHaveCount(3);
  await expect(page.locator('[data-opportunity-transition] [name=version]')).toHaveValue('2');
});
