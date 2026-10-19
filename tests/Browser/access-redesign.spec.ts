import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
const fixturePath = resolve('storage/framework/testing/access-fixture.json');
const parsedBaseURL = new URL(baseURL);
const screens = [
  { key: 'overview', path: '/access' },
  { key: 'organization', path: '/access/organization' },
  { key: 'users', path: '/access/users' },
  { key: 'roles', path: '/access/roles' },
  { key: 'audit', path: '/access/audit' },
] as const;

if (parsedBaseURL.protocol !== 'http:'
  || !['127.0.0.1', 'localhost'].includes(parsedBaseURL.hostname)
  || parsedBaseURL.username !== ''
  || parsedBaseURL.password !== '') {
  throw new Error('Access redesign browser checks may use only unauthenticated loopback HTTP.');
}

type AccessFixture = {
  root_branch: { name: string };
  root_department: { name: string };
  admin: { name: string; email: string; password: string };
};

async function readFixture(): Promise<AccessFixture> {
  const fixture = JSON.parse(await readFile(fixturePath, 'utf8')) as AccessFixture;
  expect(fixture.admin.email).toBeTruthy();
  expect(fixture.admin.password).toBeTruthy();
  expect(fixture.root_branch.name).toContain('Synthetic');
  expect(fixture.root_department.name).toContain('Synthetic');
  return fixture;
}

async function login(page: Page): Promise<AccessFixture> {
  const fixture = await readFixture();
  const response = await page.goto('/login');
  expect(response?.status()).toBe(200);

  await page.getByLabel('البريد الإلكتروني', { exact: true }).fill(fixture.admin.email);
  await page.getByLabel('كلمة المرور', { exact: true }).fill(fixture.admin.password);
  const post = page.waitForResponse(candidate => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await page.getByRole('button', { name: 'تسجيل الدخول', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(new URL('/dashboard', baseURL).toString());
  return fixture;
}

async function navigateToAccessPage(page: Page, path: string, locale: 'ar' | 'en'): Promise<void> {
  const url = new URL(path, baseURL);
  url.searchParams.set('locale', locale);
  const response = await page.goto(url.toString());
  expect(response?.status()).toBe(200);
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('.dashboard-sidebar')).toHaveCount(1);
  await expect(page.locator('.dashboard-topbar')).toBeVisible();
  await expect(page.getByTestId('admin-page-header')).toBeVisible();
  await expectNoHorizontalOverflow(page);
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const widths = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }));
  expect(widths.document).toBeLessThanOrEqual(widths.viewport);
  expect(widths.body).toBeLessThanOrEqual(widths.viewport);
}

async function setTheme(page: Page, theme: 'light' | 'dark'): Promise<string> {
  await page.evaluate(value => {
    if (value === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    else document.documentElement.removeAttribute('data-theme');
  }, theme);
  return page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-background').trim());
}

async function saveReferenceScreenshot(page: Page, screen: string, testInfo: TestInfo): Promise<void> {
  await page.screenshot({
    path: resolve('test-results', `admin-reference-${screen}-${testInfo.project.name}.png`),
    fullPage: true,
  });
}

async function expectDialogKeyboard(page: Page, opener: Locator): Promise<void> {
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);

  await page.keyboard.press('Tab');
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Shift+Tab');
  expect(await dialog.evaluate(element => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener).toBeFocused();
}

test('access pages render real summary and synthetic directory data across locale, theme, and viewport modes', async ({ page }, testInfo) => {
  if (testInfo.project.name === 'desktop-chromium') {
    await page.setViewportSize({ width: 1672, height: 941 });
  }

  const fixture = await login(page);

  for (const screen of screens) {
    for (const locale of ['ar', 'en'] as const) {
      await navigateToAccessPage(page, screen.path, locale);
      await expect(page.locator('.sidebar-account')).toContainText(fixture.admin.name.split(' ').slice(0, 2).join(' '));

      const lightBackground = await setTheme(page, 'light');
      expect(lightBackground).toBeTruthy();

      if (screen.key === 'overview') {
        const summary = page.getByTestId('overview-summary');
        await expect(summary).toBeVisible();
        const labels = locale === 'ar'
          ? [/المستخدم/, /الأدوار/, /الفروع/, /التدقيق|سجل/]
          : [/users?/i, /roles?/i, /branches/i, /audit/i];
        for (const label of labels) {
          const metricLabel = summary.getByText(label).first();
          await expect(metricLabel).toBeVisible();
          await expect(metricLabel.locator('xpath=..')).toContainText(/[0-9٠-٩]+/);
        }
      }

      if (screen.key === 'users') {
        await expect(page.getByTestId('users-table')).toContainText(fixture.admin.email);
      }
      if (screen.key === 'organization') {
        await expect(page.getByRole('table', { name: /فروع الشركة|Company branches/ })).toBeVisible();
        await expect(page.getByRole('table', { name: /أقسام الشركة|Company departments/ })).toBeVisible();
      }

      if (locale === 'ar') await saveReferenceScreenshot(page, screen.key, testInfo);

      const darkBackground = await setTheme(page, 'dark');
      expect(darkBackground).not.toBe(lightBackground);
      await expectNoHorizontalOverflow(page);
      await setTheme(page, 'light');

      if (testInfo.project.name === 'mobile-chromium') {
        const menu = page.locator('.mobile-menu');
        await expect(menu).toBeVisible();
        await menu.click();
        await expect(menu).toHaveAttribute('aria-expanded', 'true');
        await page.locator('.sidebar-close').click();
        await expect(menu).toHaveAttribute('aria-expanded', 'false');
        await expectNoHorizontalOverflow(page);
      }
    }
  }
});

test('user filtering stays server-side and the creation panel supports keyboard focus', async ({ page }) => {
  await login(page);
  await navigateToAccessPage(page, '/access/users', 'ar');

  const filters = page.getByTestId('users-filters');
  await expect(filters).toHaveAttribute('method', /get/i);
  const search = filters.getByRole('searchbox');
  await expect(search).toBeVisible();
  await search.fill('No matching synthetic user 93f1');
  const filteredResponse = page.waitForResponse(response => {
    const url = new URL(response.url());
    return response.request().method() === 'GET'
      && url.pathname === '/access/users'
      && url.searchParams.size > 0;
  });
  await search.press('Enter');
  expect((await filteredResponse).status()).toBe(200);
  await expect(page.getByTestId('users-table').locator('a[href^="/access/users/"]')).toHaveCount(0);

  await navigateToAccessPage(page, '/access/users', 'ar');
  const userForm = page.getByTestId('user-form');
  await expect(userForm).toBeVisible();
  await expect(page.locator('.admin-create-panel').getByTestId('user-form')).toBeVisible();

  const opener = page.locator('button[data-focus-form]');
  await expect(opener).toBeVisible();
  await opener.focus();
  await page.keyboard.press('Enter');
  const firstField = userForm.locator('input:not([type="hidden"]), select, textarea').first();
  await expect(firstField).toBeFocused();
  await page.keyboard.press('Tab');
  expect(await userForm.evaluate(element => element.contains(document.activeElement))).toBe(true);
});

test('organization rows edit through one keyboard-accessible dialog per entity type', async ({ page }) => {
  const fixture = await login(page);
  await navigateToAccessPage(page, '/access/organization', 'ar');

  const branchTable = page.getByRole('table', { name: /فروع الشركة|Company branches/ });
  const departmentTable = page.getByRole('table', { name: /أقسام الشركة|Company departments/ });
  await expect(branchTable.locator('tbody input, tbody select, tbody textarea')).toHaveCount(0);
  await expect(departmentTable.locator('tbody input, tbody select, tbody textarea')).toHaveCount(0);
  await expect(page.locator('dialog#branch-edit')).toHaveCount(1);
  await expect(page.locator('dialog#department-edit')).toHaveCount(1);

  const branchRow = branchTable.getByRole('row').filter({ hasText: fixture.root_branch.name });
  const branchOpener = branchRow.getByRole('button', { name: /تعديل|edit/i });
  await branchOpener.click();
  await expectDialogKeyboard(page, branchOpener);

  const departmentRow = departmentTable.getByRole('row').filter({ hasText: fixture.root_department.name });
  const departmentOpener = departmentRow.getByRole('button', { name: /تعديل|edit/i });
  await departmentOpener.click();
  await expectDialogKeyboard(page, departmentOpener);
});

test('role permission groups filter and select only within the chosen group', async ({ page }) => {
  await login(page);
  await navigateToAccessPage(page, '/access/roles', 'ar');

  const groups = page.locator('[data-permission-groups]');
  await expect(groups).toBeVisible();
  const groupRoots = groups.locator('[data-permission-group]');
  const groupCount = await groupRoots.count();
  expect(groupCount).toBeGreaterThan(1);
  await expect(groups.locator('[data-permission-group][open]')).toHaveCount(0);

  const roleForm = page.getByTestId('role-form');
  const roleList = page.locator('.admin-table');
  const editorToListGap = await page.evaluate(() => {
    const editor = document.querySelector('[data-testid="role-form"]');
    const list = document.querySelector('.admin-table');
    if (!editor || !list) return Number.POSITIVE_INFINITY;
    return list.getBoundingClientRect().top - editor.getBoundingClientRect().bottom;
  });
  expect(editorToListGap).toBeGreaterThanOrEqual(0);
  expect(editorToListGap).toBeLessThan(50);
  await expect(roleList).toBeVisible();

  const search = groups.getByRole('searchbox');
  await search.fill('المؤسسة');
  const visibleGroups = groups.locator('[data-permission-group]:visible');
  await expect.poll(() => visibleGroups.count()).toBeLessThan(groupCount);
  const organizationGroup = groupRoots.filter({ hasText: 'المؤسسة' }).first();
  await expect(organizationGroup).toBeVisible();

  const liveCount = groups.locator('[aria-live="polite"]');
  await expect(liveCount).toHaveCount(1);
  const initialCount = await liveCount.innerText();
  const selectedTotal = roleForm.locator('[data-permission-total]');
  await expect(selectedTotal).toHaveText(String(await groups.locator('input[type="checkbox"]:checked').count()));
  const permissionInputs = organizationGroup.locator('input[type="checkbox"]');
  const permissionCount = await permissionInputs.count();
  expect(permissionCount).toBeGreaterThan(0);

  await organizationGroup.getByRole('button', { name: /تحديد الكل|select all/i }).click();
  await expect(groups.locator('input[type="checkbox"]:checked')).toHaveCount(permissionCount);
  await expect(groupRoots.filter({ hasNotText: 'المؤسسة' }).locator('input[type="checkbox"]:checked')).toHaveCount(0);
  await expect(selectedTotal).toHaveText(String(await groups.locator('input[type="checkbox"]:checked').count()));
  await expect(organizationGroup.locator('[data-permission-state]')).toHaveClass(/is-selected/);
  expect(await liveCount.innerText()).not.toBe(initialCount);

  await organizationGroup.getByRole('button', { name: /مسح|إلغاء تحديد|clear/i }).click();
  await expect(groups.locator('input[type="checkbox"]:checked')).toHaveCount(0);
  await expect(selectedTotal).toHaveText('0');
  await expect(organizationGroup.locator('[data-permission-state]')).not.toHaveClass(/is-selected/);
  await expect(liveCount).toHaveText(initialCount);

  await organizationGroup.getByRole('button', { name: /تحديد الكل|select all/i }).click();
  await expect(groups.locator('input[type="checkbox"]:checked')).toHaveCount(permissionCount);
  await expect(organizationGroup.locator('[data-permission-state]')).toHaveClass(/is-selected/);
  await roleForm.locator('button[type="reset"]').first().click();
  await expect(groups.locator('input[type="checkbox"]:checked')).toHaveCount(0);
  await expect(selectedTotal).toHaveText('0');
  await expect(organizationGroup.locator('[data-permission-state]')).not.toHaveClass(/is-selected/);
});

test('audit GET filters lead to a keyboard-accessible row detail dialog', async ({ page }) => {
  await login(page);
  await navigateToAccessPage(page, '/access/audit', 'ar');

  const filters = page.getByTestId('audit-filters');
  await expect(filters).toHaveCount(1);
  const search = filters.getByRole('searchbox');
  await search.fill('audit.viewed');
  const filteredResponse = page.waitForResponse(response => {
    const url = new URL(response.url());
    return response.request().method() === 'GET'
      && url.pathname === '/access/audit'
      && url.searchParams.size > 0;
  });
  await search.press('Enter');
  expect((await filteredResponse).status()).toBe(200);

  const auditTable = page.getByRole('table');
  const firstRow = auditTable.locator('tbody tr').first();
  await expect(firstRow).toBeVisible();
  const opener = firstRow.getByRole('button');
  await expect(opener).toBeVisible();
  await opener.click();
  await expectDialogKeyboard(page, opener);
});
