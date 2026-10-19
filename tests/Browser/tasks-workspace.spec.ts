import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

type WorkspaceFixture = {
  run_id: string;
  tag: string;
  authorized_task_count: number;
  titles: Record<string, string>;
  evidence_name: string;
  foreign_evidence_name: string;
};

const fixturePath = process.env.ARASYA_TASKS_WORKSPACE_FIXTURE;
const authorizedState = process.env.ARASYA_TASKS_WORKSPACE_STATE;
const readOnlyState = process.env.ARASYA_TASKS_WORKSPACE_READONLY_STATE;
const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8776';

function readFixture(): WorkspaceFixture {
  if (!fixturePath) throw new Error('The isolated task-workspace fixture path is missing.');
  const fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as WorkspaceFixture;
  if (!fixture.run_id || !fixture.titles.today || !fixture.evidence_name) {
    throw new Error('The isolated task-workspace fixture is incomplete.');
  }
  return fixture;
}

async function expectDocumentShell(page: Page, locale: 'ar' | 'en'): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.getByRole('main')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
}

async function saveScreenshot(page: Page, testInfo: TestInfo, label: string): Promise<void> {
  if (!fixturePath) throw new Error('The isolated task-workspace fixture path is missing.');
  const screenshotDirectory = resolve(fixturePath, '..', 'screenshots');
  mkdirSync(screenshotDirectory, { recursive: true, mode: 0o700 });
  const screenshotPath = resolve(screenshotDirectory, `tasks-workspace-${label}-${testInfo.project.name}.png`);
  const screenshot = await page.screenshot({ path: screenshotPath, fullPage: true });
  await testInfo.attach(`tasks-workspace-${label}`, { body: screenshot, contentType: 'image/png' });
}

test.describe('A1-TASK-004 daily workspace', () => {
  test.use({ storageState: authorizedState || undefined });

  test('renders scoped task context in RTL and LTR with light and dark screenshots', async ({ page }, testInfo) => {
    const fixture = readFixture();
    const arabicResponse = await page.goto('/tasks?locale=ar');
    expect(arabicResponse?.status()).toBe(200);
    await expectDocumentShell(page, 'ar');
    await expect(page.getByRole('heading', { name: 'مهامي اليومية', level: 1 })).toBeVisible();
    await expect(page.getByTestId('tasks-summary')).toContainText('إجمالي المهام');
    await expect(page.getByTestId('tasks-summary')).toContainText(String(fixture.authorized_task_count));
    await expect(page.getByTestId('tasks-table').getByText(fixture.titles.today, { exact: true })).toBeVisible();
    await expect(page.getByText(fixture.titles.foreign, { exact: true })).toHaveCount(0);
    await expect(page.getByTestId('tasks-evidence')).toContainText(fixture.evidence_name);
    await expect(page.getByTestId('tasks-evidence')).not.toContainText(fixture.foreign_evidence_name);
    await expect(page.getByTestId('tasks-create-action')).toBeVisible();
    await expect(page.locator('[data-testid="tasks-table"] tr[data-task-id]')).toHaveCount(10);
    await saveScreenshot(page, testInfo, 'rtl-light');

    const themeToggle = page.locator('[data-theme-toggle]');
    await expect(themeToggle).toHaveCount(1);
    await themeToggle.click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await saveScreenshot(page, testInfo, 'rtl-dark');
    await themeToggle.click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

    const englishResponse = await page.goto('/tasks?locale=en');
    expect(englishResponse?.status()).toBe(200);
    await expectDocumentShell(page, 'en');
    await expect(page.getByRole('heading', { name: 'My daily tasks', level: 1 })).toBeVisible();
    await expect(page.getByLabel('Search tasks', { exact: true })).toBeVisible();
    await expect(page.getByTestId('tasks-upcoming')).toContainText('Next seven days');
    await saveScreenshot(page, testInfo, 'ltr-light');
  });

  test('filters and paginates bounded results while retaining the query and locale', async ({ page }) => {
    const fixture = readFixture();
    const response = await page.goto('/tasks?locale=en');
    expect(response?.status()).toBe(200);
    await page.locator('input[name="q"]').fill('Synthetic workspace page');
    await page.locator('select[name="status"]').selectOption('pending');
    await page.locator('select[name="due_preset"]').selectOption('upcoming');
    await page.locator('.tasks-filter-advanced summary').click();
    await page.locator('select[name="per_page"]').selectOption('10');
    await page.locator('select[name="sort"]').selectOption('title');
    await page.locator('select[name="direction"]').selectOption('asc');
    await page.getByRole('button', { name: 'Apply', exact: true }).click();

    await expect(page).toHaveURL(/\/tasks\?/);
    let currentUrl = new URL(page.url());
    expect(currentUrl.searchParams.get('q')).toBe('Synthetic workspace page');
    expect(currentUrl.searchParams.get('locale')).toBe('en');
    expect(currentUrl.searchParams.get('due_preset')).toBe('upcoming');
    expect(currentUrl.searchParams.get('sort')).toBe('title');
    expect(currentUrl.searchParams.get('direction')).toBe('asc');
    expect(currentUrl.searchParams.get('per_page')).toBe('10');
    const resultTable = page.getByTestId('tasks-table');
    await expect(resultTable.getByText(fixture.titles.page_1, { exact: true })).toBeVisible();
    await expect(resultTable.getByText(fixture.titles.page_10, { exact: true })).toBeVisible();
    await expect(resultTable.getByText(fixture.titles.page_11, { exact: true })).toHaveCount(0);
    await expect(page.locator('[data-testid="tasks-table"] tr[data-task-id]')).toHaveCount(10);

    await page.getByRole('link', { name: '2', exact: true }).click();
    currentUrl = new URL(page.url());
    expect(currentUrl.searchParams.get('page')).toBe('2');
    expect(currentUrl.searchParams.get('q')).toBe('Synthetic workspace page');
    expect(currentUrl.searchParams.get('locale')).toBe('en');
    await expect(resultTable.getByText(fixture.titles.page_11, { exact: true })).toBeVisible();
    await expect(resultTable.getByText(fixture.titles.page_12, { exact: true })).toBeVisible();
    await expect(page.locator('[data-testid="tasks-table"] tr[data-task-id]')).toHaveCount(2);
  });

  test('supports keyboard filters, honest empty state, and bounded mobile table scrolling', async ({ page }) => {
    await page.goto('/tasks?locale=en');
    const search = page.locator('input[name="q"]');
    await search.focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('select[name="status"]')).toBeFocused();

    await page.goto('/tasks?locale=en&q=no-synthetic-task-matches-this');
    await expect(page.getByTestId('tasks-empty-state')).toContainText('No matching tasks');
    await expect(page.getByRole('link', { name: 'Reset filters' })).toBeVisible();
    await expectDocumentShell(page, 'en');

    await page.goto('/tasks?locale=en');
    const tableRegion = page.locator('[data-testid="tasks-table"]');
    if (test.info().project.name === 'mobile') {
      expect(await tableRegion.evaluate((element) => element.scrollWidth > element.clientWidth)).toBe(true);
    }
    await expectDocumentShell(page, 'en');
  });
});

test('read-only session sees tasks but no create or lifecycle actions', async ({ browser }) => {
  if (!readOnlyState) throw new Error('The isolated read-only browser state path is missing.');
  const context = await browser.newContext({ baseURL, storageState: readOnlyState });
  try {
    const page = await context.newPage();
    const response = await page.goto('/tasks?locale=en');
    expect(response?.status()).toBe(200);
    await expectDocumentShell(page, 'en');
    await expect(page.getByTestId('tasks-create-action')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /start|complete|attach/i })).toHaveCount(0);
    await expect(page.locator('[data-testid="tasks-table"] tr[data-task-id]')).toHaveCount(10);
  } finally {
    await context.close();
  }
});
