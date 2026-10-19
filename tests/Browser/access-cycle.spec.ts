import { randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
const fixturePath = resolve('storage/framework/testing/access-fixture.json');
const restrictedPassword = 'SyntheticAccess!2026';
const parsedBaseURL = new URL(baseURL);
if (parsedBaseURL.protocol !== 'http:'
  || !['127.0.0.1', 'localhost'].includes(parsedBaseURL.hostname)
  || parsedBaseURL.username !== ''
  || parsedBaseURL.password !== '') {
  throw new Error('Access browser checks may use only unauthenticated loopback HTTP.');
}

type AccessFixture = {
  company: { id: string; name: string };
  root_branch: { id: string; name: string };
  root_department: { id: string; name: string };
  admin: { id: string; name: string; email: string; password: string };
};

async function readFixture(): Promise<AccessFixture> {
  const fixture = JSON.parse(await readFile(fixturePath, 'utf8')) as AccessFixture;
  expect(fixture.company.id).toBeTruthy();
  expect(fixture.admin.email).toBeTruthy();
  expect(fixture.admin.password).toBeTruthy();
  return fixture;
}

async function expectArabicAndNoOverflow(page: Page): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function expectRowWithName(page: Page, name: string): Promise<void> {
  const row = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name, exact: true }) });
  await expect(row).toHaveCount(1);
  await expect(row).toBeVisible();
}

async function login(page: Page, email: string, password: string): Promise<void> {
  const response = await page.goto('/login');
  expect(response?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);

  const emailField = page.getByLabel('البريد الإلكتروني', { exact: true });
  const passwordField = page.getByLabel('كلمة المرور', { exact: true });
  const submit = page.getByRole('button', { name: 'تسجيل الدخول', exact: true });
  await expect(emailField).toBeVisible();
  await expect(passwordField).toBeVisible();
  await emailField.focus();
  const passwordToggle = page.locator('[data-password-toggle="login-password"]');
  await page.keyboard.press('Tab');
  await expect(passwordField).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(passwordToggle).toHaveAccessibleName(/\S+/);
  await expect(passwordToggle).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(submit).toBeFocused();
  await emailField.fill(email);
  await passwordField.fill(password);

  const post = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await submit.click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(/\/dashboard\/?$/);
  await page.goto('/access');
  await expectArabicAndNoOverflow(page);
}

async function fillField(form: Locator, name: string, value: string): Promise<Locator> {
  const field = form.locator(`[name="${name}"]`);
  await expect(field).toHaveCount(1);
  await expect(field).toHaveAccessibleName(/\S+/);
  await field.fill(value);
  return field;
}

async function selectOptionByVisibleText(form: Locator, name: string, label: string): Promise<string> {
  const select = form.locator(`select[name="${name}"]`);
  await expect(select).toHaveCount(1);
  await expect(select).toHaveAccessibleName(/\S+/);
  const option = select.locator('option').filter({ hasText: label });
  await expect(option).toHaveCount(1);
  const value = await option.getAttribute('value');
  expect(value).toBeTruthy();
  await select.selectOption(value!);
  return value!;
}

async function submitForm(page: Page, form: Locator): Promise<void> {
  const action = await form.getAttribute('action');
  expect(action).toBeTruthy();
  const path = new URL(action!, baseURL).pathname;
  const post = page.waitForResponse((candidate) => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === path);
  await form.locator('button[type="submit"]').click();
  expect([200, 201, 302, 303]).toContain((await post).status());
  await expectArabicAndNoOverflow(page);
}

async function userIdFromPage(page: Page, email: string): Promise<string> {
  let match = new URL(page.url()).pathname.match(/^\/access\/users\/([^/]+)\/?$/);
  if (!match) {
    await page.goto('/access/users');
    const row = page.getByRole('row').filter({ hasText: email });
    await expect(row).toBeVisible();
    const href = await row.getByRole('link').first().getAttribute('href');
    expect(href).toBeTruthy();
    match = new URL(href!, baseURL).pathname.match(/^\/access\/users\/([^/]+)\/?$/);
  }
  expect(match).not.toBeNull();
  return decodeURIComponent(match![1]);
}

test('administrator grants a branch-scoped role, then revokes the active user immediately', async ({ page, browser }, testInfo: TestInfo) => {
  const fixture = await readFixture();
  const suffix = randomUUID().replaceAll('-', '').slice(0, 12);
  const branchName = `Synthetic access branch ${suffix}`;
  const branchCode = `br-${suffix}`;
  const departmentName = `Synthetic access department ${suffix}`;
  const departmentCode = `dp-${suffix}`;
  const roleName = `Synthetic access role ${suffix}`;
  const userName = `Synthetic access user ${suffix}`;
  const userEmail = `synthetic-access-user-${suffix}@example.test`;

  await login(page, fixture.admin.email, fixture.admin.password);
  const accessNavigation = page.locator('.dashboard-sidebar');
  await expect(accessNavigation.getByRole('link', { name: 'المؤسسة', exact: true })).toBeVisible();
  await expect(accessNavigation.getByRole('link', { name: 'المستخدمون', exact: true })).toBeVisible();
  await expect(accessNavigation.getByRole('link', { name: 'الأدوار والصلاحيات', exact: true })).toBeVisible();
  await expect(accessNavigation.getByRole('link', { name: 'سجل التدقيق', exact: true })).toBeVisible();

  const organizationResponse = await page.goto('/access/organization');
  expect(organizationResponse?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);
  await expectRowWithName(page, fixture.root_branch.name);
  await page.locator('[data-open-dialog="branch-create"]').click();
  const branchForm = page.locator('#branch-create').getByTestId('branch-form');
  await fillField(branchForm, 'name', branchName);
  await fillField(branchForm, 'code', branchCode);
  await submitForm(page, branchForm);
  await expectRowWithName(page, branchName);

  await page.locator('[data-open-dialog="department-create"]').click();
  const departmentForm = page.locator('#department-create').getByTestId('department-form');
  const branchId = await selectOptionByVisibleText(departmentForm, 'branch_id', branchName);
  await fillField(departmentForm, 'name', departmentName);
  await fillField(departmentForm, 'code', departmentCode);
  await submitForm(page, departmentForm);
  await expectRowWithName(page, departmentName);

  await page.goto('/access/roles');
  const roleForm = page.getByTestId('role-form');
  await fillField(roleForm, 'name', roleName);
  const permission = roleForm.locator('#new-role-organization-read');
  await permission.locator('xpath=ancestor::details[1]').locator('summary').click();
  await expect(permission).toHaveAttribute('name', 'permissions[]');
  await expect(permission).toHaveAccessibleName('عرض المؤسسة');
  await permission.check();
  await submitForm(page, roleForm);
  await expect(page.getByTestId('role-form').locator('input[name="name"]')).toHaveValue(roleName);

  await page.goto('/access/users');
  const userForm = page.locator('.admin-create-panel #user-create-form');
  await expect(userForm).toBeVisible();
  await fillField(userForm, 'name', userName);
  await fillField(userForm, 'email', userEmail);
  await fillField(userForm, 'password', restrictedPassword);
  expect(await selectOptionByVisibleText(userForm, 'branch_id', branchName)).toBe(branchId);
  await selectOptionByVisibleText(userForm, 'department_id', departmentName);
  await submitForm(page, userForm);
  const userId = await userIdFromPage(page, userEmail);
  const userUrl = `/access/users/${encodeURIComponent(userId)}?role_q=${encodeURIComponent(roleName)}`;
  await page.goto(userUrl);
  const accessForm = page.getByTestId('user-access-form');
  const roleCheckbox = accessForm.getByLabel(roleName);
  await expect(roleCheckbox).toHaveAttribute('name', 'role_ids[]');
  const roleId = await roleCheckbox.getAttribute('value');
  expect(roleId).toBeTruthy();
  await roleCheckbox.check();
  await submitForm(page, accessForm);
  await page.reload();
  await expect(accessForm.getByLabel(roleName)).toBeChecked();

  const restrictedContext = await browser.newContext({ baseURL });
  try {
    const restrictedPage = await restrictedContext.newPage();
    await login(restrictedPage, userEmail, restrictedPassword);
    const scopedResponse = await restrictedPage.goto('/access/organization');
    expect(scopedResponse?.status()).toBe(200);
    await expectArabicAndNoOverflow(restrictedPage);
    await expectRowWithName(restrictedPage, branchName);
    await expect(restrictedPage.getByRole('row').filter({ hasText: fixture.root_branch.name })).toHaveCount(0);
    await testInfo.attach('restricted-organization-view', {
      body: await restrictedPage.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });

    for (const path of ['/access/users', '/access/roles', '/access/audit']) {
      const denied = await restrictedPage.goto(path);
      expect(denied?.status(), `${path} must be denied to the restricted user`).toBe(403);
    }

    const auditResponse = await page.goto('/access/audit');
    expect(auditResponse?.status()).toBe(200);
    await expect(page.getByRole('table')).toBeVisible();
    expect(await page.getByRole('table').getByRole('row').count()).toBeGreaterThan(1);
    await testInfo.attach('access-audit-before-revocation', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });

    await page.goto(userUrl);
    const revokeForm = page.getByTestId('user-access-form');
    await revokeForm.getByLabel(roleName).uncheck();
    await submitForm(page, revokeForm);
    await page.reload();
    await expect(revokeForm.getByLabel(roleName)).not.toBeChecked();

    const revoked = await restrictedPage.goto('/access/organization');
    expect(revoked?.status()).toBe(403);

    const finalAudit = await page.goto('/access/audit');
    expect(finalAudit?.status()).toBe(200);
    expect(await page.getByRole('table').getByRole('row').count()).toBeGreaterThan(1);
    await testInfo.attach('access-audit-after-revocation', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
  } finally {
    await restrictedContext.close();
  }

  await page.goto('/access');
  if ((page.viewportSize()?.width ?? Number.POSITIVE_INFINITY) <= 850) {
    const mobileMenu = page.locator('.mobile-menu');
    await mobileMenu.click();
    await expect(mobileMenu).toHaveAttribute('aria-expanded', 'true');
  }
  const logout = page.locator('.dashboard-sidebar .logout-button');
  await expect(logout).toHaveAccessibleName('تسجيل الخروج');
  const logoutResponse = page.waitForResponse(response => response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/logout');
  await logout.click();
  expect([302, 303]).toContain((await logoutResponse).status());
  await page.goto('/access');
  await expect(page).toHaveURL(/\/login\/?$/);
  await expectArabicAndNoOverflow(page);
});
