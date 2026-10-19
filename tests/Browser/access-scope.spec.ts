import { randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
const fixturePath = resolve('storage/framework/testing/access-fixture.json');
const parsedBaseURL = new URL(baseURL);
if (parsedBaseURL.protocol !== 'http:'
  || !['127.0.0.1', 'localhost'].includes(parsedBaseURL.hostname)
  || parsedBaseURL.username !== ''
  || parsedBaseURL.password !== '') {
  throw new Error('Access browser checks may use only unauthenticated loopback HTTP.');
}

type AccessFixture = {
  root_department: { id: string };
  admin: { email: string; password: string };
};

const destinations = [
  { path: '/access', heading: 'إدارة الوصول' },
  { path: '/access/organization', heading: 'المؤسسة' },
  { path: '/access/users', heading: 'المستخدمون' },
  { path: '/access/roles', heading: 'الأدوار والصلاحيات' },
  { path: '/access/audit', heading: 'سجل التدقيق' },
];

async function expectArabicAndNoOverflow(page: Page): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function login(page: Page): Promise<AccessFixture> {
  const fixture = JSON.parse(await readFile(fixturePath, 'utf8')) as AccessFixture;
  expect(fixture.admin.email).toBeTruthy();
  expect(fixture.admin.password).toBeTruthy();

  const response = await page.goto('/login');
  expect(response?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);

  await page.getByLabel('البريد الإلكتروني', { exact: true }).fill(fixture.admin.email);
  await page.getByLabel('كلمة المرور', { exact: true }).fill(fixture.admin.password);
  const post = page.waitForResponse(candidate => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await page.getByRole('button', { name: 'تسجيل الدخول', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(new URL('/dashboard', baseURL).toString());
  await page.goto('/access');
  await expectArabicAndNoOverflow(page);
  return fixture;
}

async function submitForm(page: Page, form: Locator): Promise<void> {
  const action = await form.getAttribute('action');
  expect(action).toBeTruthy();
  const path = new URL(action!, baseURL).pathname;
  const post = page.waitForResponse(response => response.request().method() === 'POST'
    && new URL(response.url()).pathname === path);
  await form.locator('button[type="submit"]').click();
  expect([200, 201, 302, 303]).toContain((await post).status());
  await expectArabicAndNoOverflow(page);
}

async function persistedDepartmentRoleIds(page: Page, departmentId: string): Promise<string[]> {
  const response = await page.request.get(new URL('/access/organization', baseURL).toString(), {
    headers: { Accept: 'application/json' },
  });
  expect(response.status()).toBe(200);
  const body = await response.json() as {
    data?: { departments?: Array<{ id: string; role_ids?: string[] }> };
  };
  const department = body.data?.departments?.find(item => item.id === departmentId);
  expect(department).toBeDefined();
  expect(Array.isArray(department?.role_ids)).toBe(true);
  return department!.role_ids!;
}

test('administrator can discover and open every access screen from the shared sidebar on desktop and mobile', async ({ page }, testInfo) => {
  await login(page);

  const sidebar = page.locator('.dashboard-sidebar');
  const navigation = sidebar.locator('#primary-navigation');
  const toggle = page.locator('.mobile-menu');
  if (testInfo.project.name === 'mobile-chromium') {
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-controls', 'primary-navigation');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.dashboard-shell')).toHaveClass(/is-nav-open/);
    const close = page.locator('.sidebar-close');
    await expect(close).toBeVisible();
    await close.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.dashboard-shell')).not.toHaveClass(/is-nav-open/);
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  } else {
    await expect(toggle).toBeHidden();
  }

  await expect(navigation).toBeVisible();
  for (const destination of destinations) {
    const link = navigation.locator(`a[href="${destination.path}?locale=ar"]`);
    await expect(link).toBeVisible();
    await expect(link).toHaveAccessibleName(/\S+/);
  }

  for (const destination of destinations) {
    if (testInfo.project.name === 'mobile-chromium'
      && await toggle.getAttribute('aria-expanded') === 'false') {
      await toggle.click();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    }
    const link = navigation.locator(`a[href="${destination.path}?locale=ar"]`);
    const response = page.waitForResponse(candidate => candidate.request().method() === 'GET'
      && new URL(candidate.url()).pathname === destination.path);
    await link.click();
    expect((await response).status(), `${destination.path} must open from the sidebar`).toBe(200);
    await expect(page).toHaveURL(new URL(destination.path + '?locale=ar', baseURL).toString());
    await expect(page.getByRole('heading', { level: 1, name: destination.heading, exact: true })).toBeVisible();
    await expectArabicAndNoOverflow(page);
    if (testInfo.project.name === 'mobile-chromium') {
      await toggle.click();
      await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    }
    await expect(navigation.locator(`a[href="${destination.path}?locale=ar"]`)).toBeVisible();
  }

  await testInfo.attach(`access-sidebar-${testInfo.project.name}`, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  });
});

test('administrator can assign and remove a department default role with durable JSON read-back', async ({ page }) => {
  const roleName = `Synthetic access role department default ${randomUUID().replaceAll('-', '').slice(0, 12)}`;

  const fixture = await login(page);
  const rolesPage = await page.goto('/access/roles');
  expect(rolesPage?.status()).toBe(200);
  const roleForm = page.getByTestId('role-form');
  await roleForm.locator('input[name="name"]').fill(roleName);
  const permission = roleForm.locator('input[name="permissions[]"][value="organization.read"]');
  await permission.locator('xpath=ancestor::details[1]').locator('summary').click();
  await expect(permission).toHaveAccessibleName('عرض المؤسسة');
  await permission.check();
  await submitForm(page, roleForm);
  await expect(page).toHaveURL(/\/access\/roles\/[A-Za-z0-9]+$/);
  await expect(page.getByTestId('role-form').locator('input[name="name"]')).toHaveValue(roleName);
  await expect(page.getByTestId('role-form').locator('input[name="permissions[]"][value="organization.read"]')).toBeChecked();
  const roleMatch = new URL(page.url()).pathname.match(/^\/access\/roles\/([^/]+)$/);
  expect(roleMatch).not.toBeNull();
  const roleId = decodeURIComponent(roleMatch![1]);

  const organizationSearchPath = `/access/organization?role_q=${encodeURIComponent(roleName)}`;
  const organizationPage = await page.goto(organizationSearchPath);
  expect(organizationPage?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);
  const departmentRolesButton = page.getByTestId(`department-roles-${fixture.root_department.id}`);
  await departmentRolesButton.click();
  const departmentForm = page.locator('#department-roles').getByTestId('department-roles-form');
  await expect(departmentForm).toBeVisible();
  const roleAction = await departmentRolesButton.getAttribute('data-form-action');
  expect(roleAction).toBeTruthy();
  await expect(departmentForm).toHaveAttribute('action', new URL(roleAction!, baseURL).toString());
  const roleCheckbox = departmentForm.getByRole('checkbox', { name: roleName, exact: true });
  await expect(departmentForm.getByRole('button', { name: 'حفظ الأدوار', exact: true })).toBeVisible();
  await expect(roleCheckbox).toBeVisible();
  expect(await persistedDepartmentRoleIds(page, fixture.root_department.id)).not.toContain(roleId);

  await roleCheckbox.check();
  await submitForm(page, departmentForm);
  await expect(page).toHaveURL(new URL('/access/organization', baseURL).toString());
  const refreshedOrganizationPage = await page.goto(organizationSearchPath);
  expect(refreshedOrganizationPage?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);
  await page.getByTestId(`department-roles-${fixture.root_department.id}`).click();
  const updatedForm = page.locator('#department-roles').getByTestId('department-roles-form');
  await expect(updatedForm.getByRole('checkbox', { name: roleName, exact: true })).toBeChecked();
  expect(await persistedDepartmentRoleIds(page, fixture.root_department.id)).toContain(roleId);

  await updatedForm.getByRole('checkbox', { name: roleName, exact: true }).uncheck();
  await expect(updatedForm.getByRole('button', { name: 'حفظ الأدوار', exact: true })).toBeVisible();
  await submitForm(page, updatedForm);
  await expect(page).toHaveURL(new URL('/access/organization', baseURL).toString());
  const finalOrganizationPage = await page.goto(organizationSearchPath);
  expect(finalOrganizationPage?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);
  await page.getByTestId(`department-roles-${fixture.root_department.id}`).click();
  await expect(page.locator('#department-roles').getByTestId('department-roles-form').getByRole('checkbox', { name: roleName, exact: true })).not.toBeChecked();
  expect(await persistedDepartmentRoleIds(page, fixture.root_department.id)).not.toContain(roleId);
});
