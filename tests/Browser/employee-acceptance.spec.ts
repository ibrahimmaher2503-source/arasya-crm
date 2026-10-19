import { expect, Page, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

type Login = { id: string; email: string; password: string };
type EmployeeFixture = {
  admin: Login;
  supervisor: Login;
  link: Login;
  branch_id: string;
  department_id: string;
  manager_id: string;
  peer_employee_id: string;
  private_employee_id: string;
  other_branch_id: string;
  other_department_id: string;
  other_employee_id: string;
};
type Employee = {
  id: string;
  code: string;
  name: string;
  role: string;
  branch_id: string;
  department_id: string;
  manager_id: string | null;
  user_id: string | null;
  hire_date: string;
  status: string;
  version: number;
};
type EmployeePage = { data: Employee[]; current_page: number; per_page: number; total: number };
type EmployeeListResponse = { data: EmployeePage };

const privateNote = 'SYNTHETIC-PRIVATE-NOTE-EMPLOYEE';

async function login(page: Page, user: Login): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(user.email);
  await page.locator('input[name="password"]').fill(user.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
}

function localized(path: string, locale: 'ar' | 'en'): string {
  return `${path}${path.includes('?') ? '&' : '?'}locale=${locale}`;
}

async function assertAppearance(page: Page, path: string, locale: 'ar' | 'en', screenshotPath?: string): Promise<void> {
  await page.goto(localized(path, locale));
  const html = page.locator('html');
  await expect(html).toHaveAttribute('lang', locale);
  await expect(html).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('[data-dashboard-shell]')).toHaveCount(1);
  await expect(page.locator('[data-dashboard-shell] header.admin-topbar')).toHaveCount(1);
  await expect(page.locator('[data-dashboard-shell] main#main-content h1')).toHaveCount(1);

  const alignment = await page.evaluate(() => {
    const topbar = document.querySelector('header.admin-topbar')?.getBoundingClientRect();
    const main = document.querySelector('main#main-content')?.getBoundingClientRect();
    return topbar && main ? { x: Math.abs(topbar.x - main.x), width: Math.abs(topbar.width - main.width), available: main.width / window.innerWidth } : null;
  });
  expect(alignment).not.toBeNull();
  expect(alignment!.x).toBeLessThanOrEqual(1);
  expect(alignment!.width).toBeLessThanOrEqual(2);
  expect(alignment!.available).toBeGreaterThanOrEqual(0.6);

  await page.keyboard.press('Tab');
  await expect(page.locator('.skip-link')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeFocused();

  const themeToggle = page.locator('[data-theme-toggle]');
  await themeToggle.click();
  await expect(html).toHaveAttribute('data-theme', 'dark');
  await expect(themeToggle).toHaveAttribute('aria-pressed', 'true');
  await themeToggle.click();
  await expect(html).toHaveAttribute('data-theme', 'light');
  await expect(themeToggle).toHaveAttribute('aria-pressed', 'false');

  const menu = page.locator('.admin-topbar .mobile-menu');
  if (await menu.isVisible()) {
    await menu.click();
    await expect(menu).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('[data-dashboard-shell]')).toHaveClass(/is-nav-open/);
    await page.locator('.dashboard-sidebar .sidebar-close').click();
    await expect(menu).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('[data-dashboard-shell]')).not.toHaveClass(/is-nav-open/);
  }

  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();
  if (screenshotPath) await page.screenshot({ path: screenshotPath, fullPage: true });
}

function filtersFor(page: Page) {
  return page.locator('form[method="get"][role="search"]:has([name="per_page"])');
}

async function submitFilters(page: Page): Promise<void> {
  const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await filtersFor(page).locator('[name="q"]').press('Enter');
  await navigation;
}

async function listResponse(page: Page, path: string): Promise<EmployeePage> {
  const response = await page.request.get(path, { headers: { Accept: 'application/json' } });
  expect(response.status()).toBe(200);
  const payload = await response.json() as EmployeeListResponse;
  expect(payload.data).toEqual(expect.objectContaining({ data: expect.any(Array), current_page: expect.any(Number), per_page: expect.any(Number), total: expect.any(Number) }));

  return payload.data;
}

test('employees stay scoped and usable through the real browser session', async ({ page, browser }, info) => {
  const fixture = JSON.parse(readFileSync(resolve('storage/framework/testing/employee-fixture.json'), 'utf8')) as EmployeeFixture;
  const suffix = `${info.project.name}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
  const workerCode = `BROWSER-EMPLOYEE-000-${suffix}`;
  const workerName = `Browser acceptance worker ${suffix}`;
  const updatedWorkerName = `Updated browser worker ${suffix}`;
  const workerRole = 'Technician';

  await login(page, fixture.admin);
  if (info.project.name === 'employee-tablet') {
    await assertAppearance(page, '/access/users', 'en');
    await assertAppearance(page, '/crm/leads', 'en');
  }
  await assertAppearance(page, '/employees', 'ar', info.outputPath('employee-directory.png'));
  await assertAppearance(page, '/employees', 'en');

  const managerOptions = page.locator('details.admin-options');
  await expect(managerOptions).toHaveCount(1);
  await managerOptions.locator('summary').click();
  const managerSearch = managerOptions.locator('form:has(input[name="manager_q"])');
  await expect(managerSearch).toHaveCount(1);
  await managerSearch.locator('input[name="manager_q"]').fill('Synthetic supervisor');
  const managerSearchNavigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await managerSearch.locator('button[type="submit"]').click();
  await managerSearchNavigation;
  expect(new URL(page.url()).searchParams.get('manager_q')).toBe('Synthetic supervisor');

  const filters = filtersFor(page);
  await expect(filters).toHaveCount(1);
  await expect(filters.locator(`[name="manager_id"] option[value="${fixture.manager_id}"]`)).toHaveCount(1);
  await expect(filters.locator('[name="q"]')).toHaveCount(1);
  for (const name of ['status', 'branch_id', 'department_id', 'manager_id', 'sort', 'direction', 'per_page']) {
    await expect(filters.locator(`[name="${name}"]`)).toHaveCount(1);
  }

  await filters.locator('[name="q"]').fill('BROWSER-EMPLOYEE-032');
  await filters.locator('[name="status"]').selectOption('active');
  await filters.locator('[name="branch_id"]').selectOption(fixture.branch_id);
  await filters.locator('[name="department_id"]').selectOption(fixture.department_id);
  await filters.locator('[name="manager_id"]').selectOption(fixture.manager_id);
  await filters.locator('[name="sort"]').selectOption('code');
  await filters.locator('[name="direction"]').selectOption('asc');
  await filters.locator('[name="per_page"]').selectOption('10');
  await submitFilters(page);
  const searchUrl = new URL(page.url());
  expect(searchUrl.searchParams.get('q')).toBe('BROWSER-EMPLOYEE-032');
  expect(searchUrl.searchParams.get('status')).toBe('active');
  expect(searchUrl.searchParams.get('branch_id')).toBe(fixture.branch_id);
  expect(searchUrl.searchParams.get('department_id')).toBe(fixture.department_id);
  expect(searchUrl.searchParams.get('manager_id')).toBe(fixture.manager_id);
  expect(searchUrl.searchParams.get('sort')).toBe('code');
  expect(searchUrl.searchParams.get('direction')).toBe('asc');
  expect(searchUrl.searchParams.get('per_page')).toBe('10');
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody')).toContainText('BROWSER-EMPLOYEE-032');

  const statusFilters = filtersFor(page);
  await statusFilters.locator('[name="q"]').fill('');
  await statusFilters.locator('[name="status"]').selectOption('on_leave');
  await submitFilters(page);
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody')).toContainText('BROWSER-EMPLOYEE-065');
  await expect(page.locator('tbody')).not.toContainText('BROWSER-EMPLOYEE-064');

  const branchFilters = filtersFor(page);
  await branchFilters.locator('[name="status"]').selectOption('');
  await branchFilters.locator('[name="branch_id"]').selectOption(fixture.other_branch_id);
  await branchFilters.locator('[name="department_id"]').selectOption(fixture.other_department_id);
  await branchFilters.locator('[name="manager_id"]').selectOption('');
  await submitFilters(page);
  await expect(page.locator('tbody tr')).toHaveCount(1);
  await expect(page.locator('tbody')).toContainText('BROWSER-BRANCH-OTHER');

  const pageFilters = filtersFor(page);
  await pageFilters.locator('[name="q"]').fill('');
  await pageFilters.locator('[name="branch_id"]').selectOption('');
  await pageFilters.locator('[name="department_id"]').selectOption('');
  await pageFilters.locator('[name="manager_id"]').selectOption('');
  await pageFilters.locator('[name="sort"]').selectOption('code');
  await pageFilters.locator('[name="direction"]').selectOption('asc');
  await pageFilters.locator('[name="per_page"]').selectOption('10');
  await submitFilters(page);
  await expect(page.locator('tbody tr')).toHaveCount(10);
  const firstPageCodes = (await page.locator('tbody tr td:nth-child(2)').allTextContents()).map(code => code.trim());
  expect(firstPageCodes).toHaveLength(10);
  const secondPageLink = page.locator('section[aria-label="Employee list"] a[href*="page=2"]').first();
  await expect(secondPageLink).toBeVisible();
  await secondPageLink.click();
  await expect(page).toHaveURL(/page=2/);
  await expect(page.locator('tbody tr')).toHaveCount(10);
  const secondPageCodes = (await page.locator('tbody tr td:nth-child(2)').allTextContents()).map(code => code.trim());
  expect(secondPageCodes).toHaveLength(10);
  expect(firstPageCodes.some(code => secondPageCodes.includes(code))).toBe(false);

  const directoryPage = await listResponse(page, '/employees?locale=en&per_page=10&page=2&sort=code&direction=asc');
  expect(directoryPage.current_page).toBe(2);
  expect(directoryPage.per_page).toBe(10);
  expect(directoryPage.total).toBeGreaterThanOrEqual(67);
  expect(directoryPage.data).toHaveLength(10);

  await assertAppearance(page, '/employees/create', 'ar', info.outputPath('employee-form.png'));
  await assertAppearance(page, '/employees/create', 'en');
  const adminEmployeeName = `Admin browser employee ${suffix}`;
  await page.locator('#employee-code').fill(`BROWSER-ADMIN-${suffix}`);
  await page.locator('#employee-name').fill(adminEmployeeName);
  await page.locator('#employee-role').fill('Coordinator');
  await page.locator('#employee-branch-id').selectOption(fixture.branch_id);
  await page.locator('#employee-department-id').selectOption(fixture.department_id);
  await page.locator('#employee-user-id').selectOption(fixture.link.id);
  await page.locator('#employee-hire-date').fill('2026-01-01');
  await page.locator('#employee-private-notes').fill('Private browser administrative note');
  await page.getByRole('button', { name: 'Save employee', exact: true }).click();
  await page.waitForURL(url => /^\/employees\/[^/]+$/.test(url.pathname));
  const adminEmployeeId = new URL(page.url()).pathname.split('/').pop()!;
  await page.reload();
  await expect(page.getByText(adminEmployeeName, { exact: true })).toBeVisible();
  const adminSaved = await page.request.get(`/employees/${adminEmployeeId}`, { headers: { Accept: 'application/json' } });
  expect((await adminSaved.json()).data.user_id).toBe(fixture.link.id);
  await assertAppearance(page, `/employees/${adminEmployeeId}`, 'ar', info.outputPath('employee-detail.png'));
  await assertAppearance(page, `/employees/${adminEmployeeId}`, 'en');
  await page.goto(`/employees/${adminEmployeeId}/edit?locale=en`);
  await page.locator('#employee-name').fill(`${adminEmployeeName} updated`);
  await page.locator('#employee-user-id').selectOption('');
  await page.getByRole('button', { name: 'Save employee', exact: true }).click();
  await page.waitForURL(url => url.pathname === `/employees/${adminEmployeeId}`);
  await page.reload();
  await expect(page.getByText(`${adminEmployeeName} updated`, { exact: true })).toBeVisible();
  const adminUpdated = await page.request.get(`/employees/${adminEmployeeId}`, { headers: { Accept: 'application/json' } });
  expect((await adminUpdated.json()).data.user_id).toBeNull();

  const mobile = info.project.name === 'employee-mobile';
  const supervisorContext = await browser.newContext({ viewport: page.viewportSize()!, isMobile: mobile, hasTouch: mobile });
  try {
    const supervisorPage = await supervisorContext.newPage();
    await login(supervisorPage, fixture.supervisor);
    await assertAppearance(supervisorPage, '/employees/create?worker=1', 'ar', info.outputPath('employee-worker-form.png'));
    await assertAppearance(supervisorPage, '/employees/create?worker=1', 'en');

    await supervisorPage.locator('#employee-code').fill(workerCode);
    await supervisorPage.locator('#employee-name').fill(workerName);
    await supervisorPage.locator('#employee-role').fill(workerRole);
    await supervisorPage.locator('#employee-hire-date').fill('2026-01-01');
    await supervisorPage.locator('form[method="post"] button[type="submit"]').last().click();
    await supervisorPage.waitForURL(url => /^\/employees\/[^/]+$/.test(url.pathname) && url.searchParams.get('locale') === 'en');
    const workerId = new URL(supervisorPage.url()).pathname.split('/').pop()!;

    const taskTitle = `Employee evidence task ${suffix}`;
    await supervisorPage.getByRole('link', { name: 'Work evidence', exact: true }).click();
    const evidenceCsrf = await supervisorPage.locator('form[action*="/evidence"] input[name="_token"]').inputValue();
    const taskResponse = await supervisorPage.request.post('/tasks', {
      data: {
        _token: evidenceCsrf,
        title: taskTitle,
        priority: 'normal',
        due_at: '2026-12-01',
        idempotency_key: `employee-acceptance-${suffix}`,
      },
      headers: { Accept: 'application/json' },
    });
    expect(taskResponse.status()).toBe(201);
    const taskId = String((await taskResponse.json() as { data: { id: string } }).data.id);

    let workerResponse = await supervisorPage.request.get(`/employees/${workerId}?locale=en`, { headers: { Accept: 'application/json' } });
    expect(workerResponse.status()).toBe(200);
    let workerPayload = await workerResponse.json() as { data: Employee };
    expect(workerPayload.data.code).toBe(workerCode);
    expect(workerPayload.data.name).toBe(workerName);
    expect(workerPayload.data.role).toBe(workerRole);
    expect(workerPayload.data.manager_id).toBe(fixture.manager_id);
    expect(workerPayload.data.user_id).toBeNull();

    await supervisorPage.goto(`/employees/${workerId}/edit?locale=en`);
    await supervisorPage.locator('#employee-name').fill(updatedWorkerName);
    await supervisorPage.locator('#employee-role').fill('Senior technician');
    await supervisorPage.locator('form[method="post"] button[type="submit"]').last().click();
    await supervisorPage.waitForURL(url => url.pathname === `/employees/${workerId}` && url.searchParams.get('locale') === 'en');
    await supervisorPage.reload();
    await expect(supervisorPage.getByText(updatedWorkerName, { exact: true })).toBeVisible();

    await assertAppearance(supervisorPage, `/employees/${workerId}`, 'ar', info.outputPath('employee-worker-detail.png'));
    await assertAppearance(supervisorPage, `/employees/${workerId}`, 'en');

    await supervisorPage.goto(`/employees/${workerId}?tab=evidence&locale=en`);
    await expect(supervisorPage).toHaveURL(url => url.pathname === `/employees/${workerId}` && url.searchParams.get('tab') === 'evidence');
    const evidenceOptions = supervisorPage.locator('details.admin-options');
    await expect(evidenceOptions).toHaveCount(1);
    await evidenceOptions.locator('summary').click();
    const taskSearch = evidenceOptions.locator('form:has(input[name="task_id"])');
    await expect(taskSearch).toHaveCount(1);
    await taskSearch.locator('input[name="task_id"]').fill(taskId);
    const taskSearchNavigation = supervisorPage.waitForNavigation({ waitUntil: 'domcontentloaded' });
    await taskSearch.locator('button[type="submit"]').click();
    await taskSearchNavigation;
    expect(new URL(supervisorPage.url()).searchParams.get('task_id')).toBe(taskId);
    expect(new URL(supervisorPage.url()).searchParams.get('tab')).toBe('evidence');
    await expect(supervisorPage.locator(`#employee-job-id option[value="${taskId}"]`)).toContainText(taskTitle);
    await supervisorPage.locator('#employee-job-id').selectOption(taskId);
    const evidenceNote = `Recorded browser evidence ${suffix}`;
    await supervisorPage.locator('#employee-evidence-note').fill(evidenceNote);
    const recordEvidence = supervisorPage.waitForNavigation({ waitUntil: 'domcontentloaded' });
    await supervisorPage.getByRole('button', { name: 'Record work evidence', exact: true }).click();
    await recordEvidence;
    await expect(supervisorPage).toHaveURL(url => url.pathname === `/employees/${workerId}` && url.searchParams.get('tab') === 'evidence');
    await expect(supervisorPage.getByText(evidenceNote, { exact: false })).toBeVisible();

    const privateProfilePath = `/employees/${fixture.private_employee_id}?locale=en`;
    const privateHtmlResponse = await supervisorPage.request.get(privateProfilePath, { headers: { Accept: 'text/html' } });
    expect(privateHtmlResponse.status()).toBe(200);
    expect(await privateHtmlResponse.text()).not.toContain(privateNote);
    const privateJsonResponse = await supervisorPage.request.get(privateProfilePath, { headers: { Accept: 'application/json' } });
    expect(privateJsonResponse.status()).toBe(200);
    const privatePayload = await privateJsonResponse.json() as { data: Record<string, unknown> };
    expect(privatePayload.data).not.toHaveProperty('private_notes');
    expect(privatePayload.data).not.toHaveProperty('phone');
    expect(JSON.stringify(privatePayload)).not.toContain(privateNote);
    await supervisorPage.goto(privateProfilePath);
    await expect(supervisorPage.getByText(privateNote, { exact: true })).toHaveCount(0);

    const hiddenHtmlResponse = await supervisorPage.request.get(`/employees/${fixture.other_employee_id}?locale=en`, { headers: { Accept: 'text/html' } });
    expect([403, 404]).toContain(hiddenHtmlResponse.status());
    const hiddenJsonResponse = await supervisorPage.request.get(`/employees/${fixture.other_employee_id}?locale=en`, { headers: { Accept: 'application/json' } });
    expect([403, 404]).toContain(hiddenJsonResponse.status());

    await supervisorPage.goto(`/employees/${workerId}/edit?locale=en`);
    const csrf = await supervisorPage.locator('input[name="_token"]').first().inputValue();
    const version = Number(await supervisorPage.locator('input[name="version"]').inputValue());
    const attempts = [
      { label: 'branch', data: { branch_id: fixture.other_branch_id, department_id: fixture.other_department_id, manager_id: fixture.other_employee_id } },
      { label: 'manager', data: { manager_id: fixture.peer_employee_id } },
      { label: 'login account', data: { user_id: fixture.link.id } },
    ];
    for (const attempt of attempts) {
      const response = await supervisorPage.request.post(`/employees/${workerId}?locale=en`, {
        data: { _token: csrf, version, ...attempt.data },
        headers: { Accept: 'application/json' },
      });
      expect(response.status(), `${attempt.label} assignment must be rejected for a supervisor`).toBe(403);
    }

    workerResponse = await supervisorPage.request.get(`/employees/${workerId}?locale=en`, { headers: { Accept: 'application/json' } });
    workerPayload = await workerResponse.json() as { data: Employee };
    expect(workerPayload.data.branch_id).toBe(fixture.branch_id);
    expect(workerPayload.data.department_id).toBe(fixture.department_id);
    expect(workerPayload.data.manager_id).toBe(fixture.manager_id);
    expect(workerPayload.data.user_id).toBeNull();
    expect(workerPayload.data.version).toBe(version);

    const workersPage = await listResponse(supervisorPage, '/employees/workers?locale=en&per_page=50&page=1&sort=code&direction=asc');
    expect(workersPage.total).toBeGreaterThanOrEqual(66);
    expect(workersPage.data.map(employee => employee.code)).toContain(workerCode);
    expect(workersPage.data.map(employee => employee.code)).not.toContain('BROWSER-BRANCH-OTHER');
    expect(workersPage.data.every(employee => employee.user_id === null)).toBe(true);
    const otherBranchSearch = await listResponse(supervisorPage, '/employees/workers?locale=en&per_page=10&q=BROWSER-BRANCH-OTHER');
    expect(otherBranchSearch.total).toBe(0);

    await assertAppearance(supervisorPage, '/employees/workers', 'ar', info.outputPath('employee-workers.png'));
    await assertAppearance(supervisorPage, '/employees/workers', 'en');
  } finally {
    await supervisorContext.close();
  }
});

test('employee writes require CSRF and deactivated identity sessions stay revoked', async ({ page, browser }, info) => {
  test.skip(info.project.name !== 'employee-desktop', 'Run identity revocation once in the serial browser runtime.');

  type AccessUser = { id: string; name: string; email: string; branch_id: string; department_id: string; active: boolean; version: number };
  const fixture = JSON.parse(readFileSync(resolve('storage/framework/testing/employee-fixture.json'), 'utf8')) as EmployeeFixture;
  const anonymousContext = await browser.newContext({ viewport: page.viewportSize()! });
  try {
    const anonymousPage = await anonymousContext.newPage();
    const anonymousRead = await anonymousPage.request.get('/employees?locale=en', { headers: { Accept: 'application/json' } });
    expect(anonymousRead.status()).toBe(401);
  } finally {
    await anonymousContext.close();
  }

  const supervisorContext = await browser.newContext({ viewport: page.viewportSize()! });
  const supervisorPage = await supervisorContext.newPage();
  const userPath = `/access/users/${fixture.supervisor.id}?locale=en`;
  let supervisorDeactivated = false;
  let adminCsrf = '';
  let workerId = '';
  let profileBefore: Employee | undefined;
  let taskIdsBefore: string[] = [];
  const reportTextBefore: Record<'evidence' | 'audit', string> = { evidence: '', audit: '' };

  try {
    await login(supervisorPage, fixture.supervisor);
    const listedWorkers = await listResponse(supervisorPage, '/employees/workers?locale=en&per_page=50&page=1&sort=code&direction=asc');
    const worker = listedWorkers.data.find(employee => employee.code.startsWith('BROWSER-EMPLOYEE-000-'))
      ?? listedWorkers.data.find(employee => employee.id === fixture.peer_employee_id);
    expect(worker).toBeDefined();
    workerId = worker!.id;

    const profileBeforeResponse = await supervisorPage.request.get(`/employees/${workerId}?locale=en`, { headers: { Accept: 'application/json' } });
    expect(profileBeforeResponse.status()).toBe(200);
    profileBefore = (await profileBeforeResponse.json() as { data: Employee }).data;
    const taskListBeforeResponse = await supervisorPage.request.get('/tasks?locale=en', { headers: { Accept: 'application/json' } });
    expect(taskListBeforeResponse.status()).toBe(200);
    taskIdsBefore = ((await taskListBeforeResponse.json() as { data: Array<{ id: string }> }).data).map(task => task.id).sort();
    for (const tab of ['evidence', 'audit'] as const) {
      await supervisorPage.goto(`/employees/${workerId}?tab=${tab}&locale=en`);
      reportTextBefore[tab] = (await supervisorPage.locator('main#main-content').innerText()).trim();
    }

    const csrflessWrite = await supervisorPage.request.post(`/employees/${workerId}?locale=en`, {
      data: { name: `Must not persist ${Date.now()}`, version: profileBefore!.version },
      headers: { Accept: 'application/json' },
    });
    expect(csrflessWrite.status()).toBe(419);
    const profileAfterCsrflessWrite = await supervisorPage.request.get(`/employees/${workerId}?locale=en`, { headers: { Accept: 'application/json' } });
    expect(profileAfterCsrflessWrite.status()).toBe(200);
    const unchangedProfile = (await profileAfterCsrflessWrite.json() as { data: Employee }).data;
    expect(unchangedProfile.version).toBe(profileBefore!.version);
    expect(unchangedProfile.name).toBe(profileBefore!.name);
    expect(unchangedProfile).toEqual(profileBefore);

    await login(page, fixture.admin);
    const userBeforeResponse = await page.request.get(userPath, { headers: { Accept: 'application/json' } });
    expect(userBeforeResponse.status()).toBe(200);
    const userBefore = (await userBeforeResponse.json() as { data: AccessUser }).data;
    expect(userBefore).toEqual(expect.objectContaining({
      id: fixture.supervisor.id,
      name: expect.any(String),
      email: expect.any(String),
      branch_id: expect.any(String),
      department_id: expect.any(String),
      active: true,
      version: expect.any(Number),
    }));
    await page.goto(userPath);
    adminCsrf = await page.locator('form[data-testid="user-form"] input[name="_token"]').inputValue();

    const deactivation = await page.request.post(userPath, {
      data: {
        _token: adminCsrf,
        version: userBefore.version,
        name: userBefore.name,
        email: userBefore.email,
        branch_id: userBefore.branch_id,
        department_id: userBefore.department_id,
        active: false,
      },
      headers: { Accept: 'application/json' },
    });
    supervisorDeactivated = deactivation.status() === 200;
    expect(deactivation.status()).toBe(200);
    const deactivatedUser = (await deactivation.json() as { data: AccessUser }).data;
    expect(deactivatedUser.active).toBe(false);
    expect(deactivatedUser.version).toBe(userBefore.version + 1);
    expect(deactivatedUser.name).toBe(userBefore.name);
    expect(deactivatedUser.email).toBe(userBefore.email);
    expect(deactivatedUser.branch_id).toBe(userBefore.branch_id);
    expect(deactivatedUser.department_id).toBe(userBefore.department_id);

    const revokedResponse = await supervisorPage.request.get('/employees?locale=en', { headers: { Accept: 'application/json' } });
    expect(revokedResponse.status()).toBe(401);
  } finally {
    if (supervisorDeactivated) {
      const latestUserResponse = await page.request.get(userPath, { headers: { Accept: 'application/json' } });
      expect(latestUserResponse.status()).toBe(200);
      const latestUser = (await latestUserResponse.json() as { data: AccessUser }).data;
      expect(latestUser.active).toBe(false);
      const activation = await page.request.post(userPath, {
        data: {
          _token: adminCsrf,
          version: latestUser.version,
          name: latestUser.name,
          email: latestUser.email,
          branch_id: latestUser.branch_id,
          department_id: latestUser.department_id,
          active: true,
        },
        headers: { Accept: 'application/json' },
      });
      expect(activation.status()).toBe(200);
      const activatedUser = (await activation.json() as { data: AccessUser }).data;
      expect(activatedUser.active).toBe(true);
      expect(activatedUser.version).toBe(latestUser.version + 1);
      expect(activatedUser.name).toBe(latestUser.name);
      expect(activatedUser.email).toBe(latestUser.email);
      expect(activatedUser.branch_id).toBe(latestUser.branch_id);
      expect(activatedUser.department_id).toBe(latestUser.department_id);
    }

    try {
      if (supervisorDeactivated) {
        const stillRevoked = await supervisorPage.request.get('/employees?locale=en', { headers: { Accept: 'application/json' } });
        expect(stillRevoked.status()).toBe(401);

        const freshContext = await browser.newContext({ viewport: page.viewportSize()! });
        try {
          const freshPage = await freshContext.newPage();
          await login(freshPage, fixture.supervisor);
          const freshEmployees = await listResponse(freshPage, '/employees?locale=en&per_page=50&page=1&sort=code&direction=asc');
          expect(freshEmployees.data.some(employee => employee.id === workerId)).toBe(true);
          const freshProfileResponse = await freshPage.request.get(`/employees/${workerId}?locale=en`, { headers: { Accept: 'application/json' } });
          expect(freshProfileResponse.status()).toBe(200);
          const freshProfile = (await freshProfileResponse.json() as { data: Employee }).data;
          expect(freshProfile.version).toBe(profileBefore!.version);
          expect(freshProfile.name).toBe(profileBefore!.name);
          expect(freshProfile).toEqual(profileBefore);

          const freshTasksResponse = await freshPage.request.get('/tasks?locale=en', { headers: { Accept: 'application/json' } });
          expect(freshTasksResponse.status()).toBe(200);
          const taskIdsAfter = ((await freshTasksResponse.json() as { data: Array<{ id: string }> }).data).map(task => task.id).sort();
          expect(taskIdsAfter).toEqual(taskIdsBefore);
          for (const tab of ['evidence', 'audit'] as const) {
            await freshPage.goto(`/employees/${workerId}?tab=${tab}&locale=en`);
            expect((await freshPage.locator('main#main-content').innerText()).trim()).toBe(reportTextBefore[tab]);
          }
        } finally {
          await freshContext.close();
        }
      }
    } finally {
      await supervisorContext.close();
    }
  }
});
