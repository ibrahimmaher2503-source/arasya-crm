import { test, expect, type Page } from '@playwright/test';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import { join } from 'node:path';

const fixture = JSON.parse(readFileSync(process.env.ARASYA_CLOSURE_PRIVATE!, 'utf8'));
const origin = process.env.ARASYA_CLOSURE_ORIGIN!;

async function login(page: Page, user: string) {
  await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
  await page.goto('/login');
  await page.locator('[name=email]').fill(fixture[user].email);
  await page.locator('[name=password]').fill(fixture[user].password);
  await Promise.all([page.waitForURL('**/dashboard'), page.locator('button[type=submit]').click()]);
}

async function api(page: Page, method: string, path: string, data?: unknown) {
  return page.request.fetch(path, { method, data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': await page.locator('[name=_token]').first().inputValue() }, maxRedirects: 0 });
}

async function get(page: Page, path: string) {
  const response = await page.request.get(path, { headers: { Accept: 'application/json' } });
  expect(response.status(), path).toBe(200);
  return (await response.json()).data;
}

async function submit(page: Page, form: any, statuses = [302, 303]) {
  const path = new URL(await form.getAttribute('action'), page.url()).pathname;
  const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === path);
  await form.locator('button[type=submit]').last().click();
  expect(statuses).toContain((await response).status());
  await page.waitForLoadState('domcontentloaded');
}

test('role grant enables contact reveal and task delegation; revoke removes both in the same session', async ({ page, browser }, info) => {
  const locale = info.project.name === 'tablet' ? 'en' : 'ar';
  await login(page, 'admin');
  await page.goto(`/access/roles?locale=${locale}`);
  const form = page.locator('[data-testid=role-form]');
  await form.locator('[name=name]').fill(`Delegation ${info.project.name} ${randomUUID()}`);
  for (const permission of ['contact.reveal', 'tasks.assign']) {
    const checkbox = form.locator(`input[value="${permission}"]`);
    await checkbox.evaluate(el => { let parent = el.parentElement; while (parent) { if (parent.tagName === 'DETAILS') (parent as HTMLDetailsElement).open = true; parent = parent.parentElement; } });
    await checkbox.check();
  }
  await page.screenshot({ path: info.outputPath('role-permissions.png'), fullPage: true });
  await submit(page, form);
  await page.waitForURL(/\/access\/roles\/[^/?]+/);
  const roleId = new URL(page.url()).pathname.split('/').at(-1)!;
  const user = await get(page, `/access/users/${fixture.sales.id}`);
  const originalRoles = user.role_ids;
  expect((await api(page, 'POST', `/access/users/${fixture.sales.id}/access`, { version: user.version, role_ids: [...originalRoles, roleId], permissions: {} })).status()).toBe(200);

  const context = await browser.newContext({ baseURL: origin, viewport: info.project.use.viewport, isMobile: info.project.use.isMobile, hasTouch: info.project.use.hasTouch });
  const sales = await context.newPage();
  await login(sales, 'sales');
  await sales.goto(`/tasks/create?locale=${locale}`);
  await expect(sales.locator('select[name=assignee_id]')).toBeVisible();
  await sales.locator('[name=title]').fill(`Delegated ${info.project.name}`);
  await sales.locator('[name=due_at]').fill('2026-11-01T10:00');
  await sales.locator('select[name=assignee_id]').selectOption(fixture.recipient.id);
  await submit(sales, sales.locator('form[method=post]').filter({ has: sales.locator('[name=title]') }));
  await sales.waitForURL(/\/tasks\/(?!create)[^/?]+/);
  const taskId = new URL(sales.url()).pathname.split('/').at(-1)!;
  await sales.reload();
  const created = await get(sales, `/tasks/${taskId}`);
  expect(created.owner_id).toBe(fixture.sales.id);
  expect(created.assignee_id).toBe(fixture.recipient.id);
  let assign = sales.locator('form[action$="/assign"]');
  await assign.locator('select[name=assignee_id]').selectOption(fixture.sales.id);
  await submit(sales, assign);
  await sales.reload();
  expect((await get(sales, `/tasks/${taskId}`)).assignee_id).toBe(fixture.sales.id);
  assign = sales.locator('form[action$="/assign"]');
  const key = await assign.locator('[name=idempotency_key]').inputValue();
  const version = Number(await assign.locator('[name=expected_version]').inputValue());
  await assign.locator('select[name=assignee_id]').selectOption(fixture.recipient.id);
  await submit(sales, assign);
  await sales.reload();
  expect((await api(sales, 'POST', `/tasks/${taskId}/assign`, { assignee_id: fixture.recipient.id, expected_version: version, idempotency_key: key })).status()).toBe(200);
  await sales.goto(`/tasks/${taskId}?locale=${locale}`);
  await expect(sales.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  const picker = sales.locator('form[action$="/assign"] select[name=assignee_id]');
  await picker.focus();
  await expect(picker).toBeFocused();
  await picker.press('Tab');
  await expect(sales.locator('form[action$="/assign"] button[type=submit]')).toBeFocused();
  await sales.screenshot({ path: info.outputPath('delegated-task.png'), fullPage: true });
  const theme = sales.locator('[data-testid="theme-toggle"], [data-theme-toggle]').first();
  await theme.click();
  await expect(sales.locator('html')).toHaveAttribute('data-theme', 'dark');
  await sales.screenshot({ path: info.outputPath('delegated-task-dark.png'), fullPage: true });
  await theme.click();
  expect(await sales.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
  const recipientContext = await browser.newContext({ baseURL: origin });
  const recipient = await recipientContext.newPage();
  await login(recipient, 'recipient');
  await recipient.goto(`/tasks/${taskId}`);
  const start = recipient.locator('form[action$="/start"]');
  await submit(recipient, start);
  await recipient.reload();
  expect((await get(recipient, `/tasks/${taskId}`)).status).toBe('in_progress');

  const masked = await get(sales, `/crm/leads/${fixture.assigned_lead_id}`);
  expect(JSON.stringify(masked)).not.toContain(fixture.privateContact.phone);
  await sales.goto(`/crm/leads/${fixture.assigned_lead_id}?locale=${locale}`);
  await sales.locator('.crm-contact-reveal > summary').click();
  const reveal = sales.locator('form[action*="/reveal"]');
  await reveal.locator('input[name=reason],textarea[name=reason]').fill('Authorized role contact disclosure');
  await submit(sales, reveal, [200]);
  expect(await sales.content()).toContain(fixture.privateContact.phone);
  await sales.screenshot({ path: info.outputPath('authorized-contact.png'), fullPage: true });

  const currentUser = await get(page, `/access/users/${fixture.sales.id}`);
  expect((await api(page, 'POST', `/access/users/${fixture.sales.id}/access`, { version: currentUser.version, role_ids: originalRoles, permissions: {} })).status()).toBe(200);
  await sales.goto(`/tasks/${taskId}`);
  await expect(sales.locator('form[action$="/assign"]')).toHaveCount(0);
  expect((await api(sales, 'POST', `/tasks/${taskId}/assign`, { assignee_id: fixture.sales.id, expected_version: 3, idempotency_key: randomUUID() })).status()).toBe(403);
  expect((await api(sales, 'POST', `/crm/leads/${fixture.assigned_lead_id}/reveal`, { reason: 'Grant was revoked' })).status()).toBe(403);
  const file = join(process.env.ARASYA_CLOSURE_EVIDENCE!, 'workflow-results.json');
  const results = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : [];
  results.push({ project: info.project.name, roleId, taskId, owner: fixture.sales.id, assignee: fixture.recipient.id, contactRole: true, taskRole: true, revoked: true });
  writeFileSync(file, JSON.stringify(results, null, 2));
  await recipientContext.close();
  await context.close();
});
