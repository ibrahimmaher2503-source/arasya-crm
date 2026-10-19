import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test } from '@playwright/test';

const renderBlade = (view: 'show' | 'form', locale: 'ar' | 'en', outputPath: string): string => {
  const php = String.raw`
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$locale = getenv('ARASYA_PROFILE_LOCALE') === 'en' ? 'en' : 'ar';
$viewName = getenv('ARASYA_PROFILE_VIEW') === 'form' ? 'employees.form' : 'employees.show';
$employeeId = 'synthetic-employee-profile-1';
$request = Illuminate\Http\Request::create('/employees/'.$employeeId.($viewName === 'employees.form' ? '/edit' : '').'?locale='.$locale, 'GET');
$app->instance('request', $request);
$session = $app->make('session')->driver();
$session->start();
$session->put('_token', 'synthetic-profile-csrf-token');
$request->setLaravelSession($session);
$actor = ['id' => 'synthetic-editor-1', 'name' => 'Synthetic profile editor'];
$permissions = ['employees.read', 'employees.write', 'employees.manage_all', 'employee.private', 'users.read', 'tasks.read', 'audit.read'];
$errors = new Illuminate\Support\ViewErrorBag;
$employee = [
    'id' => $employeeId, 'name' => 'Synthetic employee', 'code' => 'EMP-0001',
    'role' => 'Field engineer', 'branch_id' => 'synthetic-branch-1', 'branch_name' => 'South Branch',
    'department_id' => 'synthetic-department-1', 'department_name' => 'Operations',
    'manager_id' => 'synthetic-manager-1', 'manager_name' => 'Synthetic supervisor',
    'hire_date' => '2025-03-12', 'status' => 'active', 'version' => 3,
    'user_id' => 'synthetic-account-1', 'account_visible' => true, 'can_assign_account' => true,
    'user_name' => 'synthetic.account', 'phone' => '+1-555-0100',
    'email' => 'employee@example.test', 'private_notes' => 'Synthetic private note',
];
$reports = new Illuminate\Pagination\LengthAwarePaginator([
    ['id' => 'synthetic-report-1', 'name' => 'Synthetic report one', 'code' => 'REP-001', 'role' => 'Technician', 'branch_name' => 'South Branch', 'department_name' => 'Operations', 'status' => 'active'],
    ['id' => 'synthetic-report-2', 'name' => 'Synthetic report two', 'code' => 'REP-002', 'role' => 'Coordinator', 'branch_name' => 'South Branch', 'department_name' => 'Operations', 'status' => 'on_leave'],
], 2, 10, 1, ['path' => '/employees/'.$employeeId]);
$data = [
    'actor' => $actor, 'permissions' => $permissions, 'locale' => $locale,
    'title' => $locale === 'ar' ? 'تفاصيل الموظف' : 'Employee details',
    'pageSubtitle' => $locale === 'ar' ? 'عرض بيانات الموظف ضمن نطاق صلاحياتك' : 'View employee details within your authorized scope',
    'breadcrumbs' => [], 'employee' => $employee, 'reports' => $reports,
    'profileTab' => 'overview', 'evidence' => [], 'evidenceLinks' => [],
    'auditEntries' => [], 'auditLinks' => [], 'tasks' => [], 'errors' => $errors,
    'workerCreate' => false, 'workerDefaults' => [],
    'branches' => [['id' => 'synthetic-branch-1', 'name' => 'South Branch']],
    'departments' => [['id' => 'synthetic-department-1', 'name' => 'Operations']],
    'managers' => [['id' => 'synthetic-manager-1', 'name' => 'Synthetic supervisor']],
    'users' => [['id' => 'synthetic-account-1', 'name' => 'Synthetic login account']],
    'optionSources' => [],
];
$html = view($viewName, $data)->render();
if (file_put_contents(getenv('ARASYA_PROFILE_OUTPUT'), $html) === false) {
    fwrite(STDERR, "Could not write rendered Blade artifact.\n");
    exit(1);
}
`;

  const result = spawnSync('php', ['-r', php], {
    cwd: process.cwd(),
    stdio: 'inherit',
    env: {
      ...process.env,
      APP_ENV: 'testing',
      ARASYA_PROFILE_VIEW: view,
      ARASYA_PROFILE_LOCALE: locale,
      ARASYA_PROFILE_OUTPUT: outputPath,
    },
  });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`PHP Blade renderer exited with status ${result.status}`);
  return readFileSync(outputPath, 'utf8');
};

const assertNoHorizontalOverflow = async (page: import('@playwright/test').Page): Promise<void> => {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1);
};

test('renders the real employee overview Blade with RTL/LTR, theme, keyboard, responsive layout, and honest links', async ({ page }, testInfo) => {
  const arabicHtml = renderBlade('show', 'ar', testInfo.outputPath('rendered-profile-ar.html'));
  await page.setContent(arabicHtml, { waitUntil: 'load' });
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByRole('heading', { name: 'Synthetic employee' })).toBeVisible();
  await expect(page.getByTestId('employee-profile-summary')).toContainText('Synthetic supervisor');
  await expect(page.getByTestId('employee-report-count')).toHaveAttribute('data-report-count', '2');
  const profileSummary = page.getByTestId('employee-profile-summary');
  await expect(profileSummary.getByRole('link', { name: 'تعديل الموظف' })).toHaveAttribute('href', '/employees/synthetic-employee-profile-1/edit?locale=ar');
  await expect(profileSummary.getByRole('link', { name: 'دليل الموظفين' })).toHaveAttribute('href', '/employees?locale=ar');
  await expect(page.getByText('التكامل قيد الاستكمال', { exact: false })).toBeVisible();

  const theme = page.locator('[data-theme-toggle]');
  await theme.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

  const edit = profileSummary.getByRole('link', { name: 'تعديل الموظف' });
  await edit.focus();
  await page.keyboard.press('Tab');
  await expect(profileSummary.getByRole('link', { name: 'دليل الموظفين' })).toBeFocused();
  await assertNoHorizontalOverflow(page);

  if (testInfo.project.name === 'employee-profile-desktop') {
    const main = await page.locator('.employee-profile__main').boundingBox();
    const aside = await page.locator('.employee-profile__aside').boundingBox();
    expect(main).not.toBeNull();
    expect(aside).not.toBeNull();
    expect(aside!.x).toBeLessThan(main!.x);
    await page.screenshot({ path: testInfo.outputPath('employee-profile-ar-desktop.png'), fullPage: true });
  } else {
    const main = await page.locator('.employee-profile__main').boundingBox();
    const aside = await page.locator('.employee-profile__aside').boundingBox();
    expect(main).not.toBeNull();
    expect(aside).not.toBeNull();
    expect(aside!.y).toBeGreaterThan(main!.y);
    await page.screenshot({ path: testInfo.outputPath('employee-profile-ar-mobile.png'), fullPage: true });
  }

  const englishHtml = renderBlade('show', 'en', testInfo.outputPath('rendered-profile-en.html'));
  await page.setContent(englishHtml, { waitUntil: 'load' });
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('heading', { name: 'Synthetic employee' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Edit employee' })).toHaveAttribute('href', '/employees/synthetic-employee-profile-1/edit?locale=en');
  await assertNoHorizontalOverflow(page);
});

test('renders the real shared employee edit form without submitting synthetic data', async ({ page }, testInfo) => {
  const html = renderBlade('form', 'ar', testInfo.outputPath('rendered-employee-form-ar.html'));
  await page.setContent(html, { waitUntil: 'load' });

  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.getByRole('heading', { name: 'Synthetic employee', level: 2 })).toBeVisible();
  await expect(page.locator('legend').filter({ hasText: 'البيانات الأساسية' })).toBeVisible();
  await expect(page.locator('legend').filter({ hasText: 'المعلومات الوظيفية' })).toBeVisible();
  await expect(page.locator('legend').filter({ hasText: 'الفرع والقسم والمشرف' })).toBeVisible();
  await expect(page.locator('legend').filter({ hasText: 'معلومات الاتصال الخاصة' })).toBeVisible();
  await expect(page.locator('.employee-form__form')).toHaveAttribute('method', 'post');
  await expect(page.locator('.employee-form__form')).toHaveAttribute('action', '/employees/synthetic-employee-profile-1?locale=ar');
  await expect(page.locator('.employee-form__form input[name="_token"]')).toHaveValue('synthetic-profile-csrf-token');
  await expect(page.locator('.employee-form__form input[name="version"]')).toHaveValue('3');
  await expect(page.locator('#employee-name')).toHaveValue('Synthetic employee');
  await expect(page.getByRole('button', { name: 'حفظ الموظف' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'إلغاء' })).toHaveAttribute('href', '/employees/synthetic-employee-profile-1?locale=ar');
  await assertNoHorizontalOverflow(page);

  if (testInfo.project.name === 'employee-profile-mobile') {
    await page.screenshot({ path: testInfo.outputPath('employee-profile-form-mobile.png'), fullPage: true });
  }
});
