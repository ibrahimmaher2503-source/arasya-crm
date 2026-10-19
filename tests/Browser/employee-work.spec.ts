import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { existsSync, lstatSync, readFileSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { expect, test } from '@playwright/test';

const projectRoot = resolve(process.cwd());
const runId = process.env.ARASYA_EMPLOYEE_WORK_RUN_ID ?? '';
if (!/^[a-f0-9-]{36}$/i.test(runId)) {
  throw new Error('Employee work browser test requires its generated isolated run ID.');
}
const runDirectory = resolve(projectRoot, 'storage/framework/testing/employee-work', runId);
const fixturePath = resolve(runDirectory, 'employee-work-fixture.json');
const authPath = resolve(runDirectory, 'employee-work-auth.json');
const ownerMarker = `A1-EMP-007 employee-work run ${runId}`;
const syntheticEnvironmentNames = [
  'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'APP_URL', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE',
  'DB_CONNECTION', 'DB_DATABASE', 'DB_FOREIGN_KEYS', 'SESSION_DRIVER', 'SESSION_COOKIE', 'SESSION_FILES', 'SESSION_SECURE_COOKIE', 'SESSION_SAME_SITE',
  'VIEW_COMPILED_PATH', 'CACHE_STORE', 'QUEUE_CONNECTION', 'MAIL_MAILER', 'LOG_CHANNEL', 'BCRYPT_ROUNDS', 'ARASYA_BROWSER_BASE_URL',
  'ARASYA_EMPLOYEE_WORK_RUN_ID', 'ARASYA_EMPLOYEE_WORK_RUN_DIRECTORY', 'ARASYA_EMPLOYEE_WORK_FIXTURE', 'ARASYA_EMPLOYEE_WORK_AUTH',
] as const;
const childEnvironment: NodeJS.ProcessEnv = {
  PATH: process.env.PATH,
  SystemRoot: process.env.SystemRoot,
  TEMP: process.env.TEMP,
  TMP: process.env.TMP,
};
for (const name of syntheticEnvironmentNames) {
  const value = process.env[name];
  if (value === undefined) {
    throw new Error(`Employee work browser environment is missing ${name}.`);
  }
  childEnvironment[name] = value;
}

const runFixture = (mode: 'seed' | 'cleanup'): void => {
  const result = spawnSync('php', ['tests/Browser/employee-work-fixtures.php', mode], {
    cwd: projectRoot,
    env: childEnvironment,
    stdio: 'inherit',
    timeout: 45_000,
  });
  if (result.error) {
    throw result.error;
  }
  if (result.status !== 0) {
    throw new Error(`Employee work ${mode} process failed with exit code ${result.status ?? 'unknown'}.`);
  }
};

const removeOwnedRunDirectory = (): void => {
  if (!existsSync(runDirectory)) {
    return;
  }
  const expectedDirectory = resolve(projectRoot, 'storage/framework/testing/employee-work', runId);
  if (runDirectory.toLowerCase() !== expectedDirectory.toLowerCase()
    || lstatSync(runDirectory).isSymbolicLink()
    || readFileSync(join(runDirectory, '.owner'), 'utf8') !== ownerMarker) {
    throw new Error(`Refusing to remove an unverified employee-work run directory: ${runDirectory}`);
  }
  rmSync(runDirectory, { recursive: true });
};

type Fixture = {
  run_id: string;
  peer_profile_id: string;
  auth_sha256: string;
};
let fixture: Fixture;

test.beforeAll(() => {
  try {
    runFixture('seed');
    fixture = JSON.parse(readFileSync(fixturePath, 'utf8')) as Fixture;
    if (fixture.run_id !== runId) {
      throw new Error('Employee work fixture run ID does not match the browser run.');
    }
  } catch (error) {
    try {
      if (existsSync(fixturePath)) {
        runFixture('cleanup');
      }
      removeOwnedRunDirectory();
    } catch (cleanupError) {
      throw new Error(`Employee work setup failed; its owned run directory was retained at ${runDirectory}.`, { cause: cleanupError });
    }
    throw error;
  }
});

test.beforeEach(async ({ page }) => {
  const authState = readFileSync(authPath, 'utf8');
  if (createHash('sha256').update(authState).digest('hex') !== fixture.auth_sha256) {
    throw new Error('Employee work browser state does not match its owner record.');
  }
  const state = JSON.parse(authState) as {
    cookies: Array<{ name: string; value: string; domain: string; path: string; expires: number; httpOnly: boolean; secure: boolean; sameSite: 'Lax' }>;
  };
  await page.context().addCookies(state.cookies);
  const cookieNames = (await page.context().cookies('http://127.0.0.1:8787')).map(({ name }) => name);
  expect(cookieNames).toContain(`employee_work_${runId.replaceAll('-', '').slice(0, 16)}`);
});

test.afterAll(() => {
  if (existsSync(fixturePath)) {
    runFixture('cleanup');
  }
  if (existsSync(fixturePath) || existsSync(authPath)) {
    throw new Error(`Employee work fixture state remains in ${runDirectory}; preserving its ownership record.`);
  }
  removeOwnedRunDirectory();
});

test('shows scoped work with RTL/LTR, accessible controls, and responsive theme behavior', async ({ page }, testInfo) => {
  const response = await page.goto('/employees/work?locale=ar');
  expect(response?.status()).toBe(200);
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'متابعة أعمال الموظفين', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Synthetic current employee' })).toBeVisible();
  await expect(page.locator('.employee-work__task-section').getByText('Synthetic task due today', { exact: true })).toBeVisible();
  await expect(page.getByText('Synthetic work evidence.txt', { exact: true })).toBeVisible();
  await expect(page.getByText('Synthetic recorded work-evidence note', { exact: true })).toBeVisible();
  await expect(page.getByText('Peer task must stay hidden', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Peer evidence must stay hidden.txt', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Peer note must stay hidden', { exact: true })).toHaveCount(0);
  await expect(page.locator('#work-supervisor')).toBeDisabled();
  await expect(page.locator('[name="note"], textarea[name="note"]')).toHaveCount(0);

  await page.locator('#work-search').focus();
  await page.keyboard.press('Tab');
  await expect(page.locator('#work-employee')).toBeFocused();
  const themeToggle = page.locator('[data-theme-toggle]');
  await themeToggle.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('html')).toHaveCSS('color-scheme', 'dark');
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

  const documentWidth = async (): Promise<{ viewport: number; document: number }> => page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  let dimensions = await documentWidth();
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1);
  const rtlSide = await page.locator('.employee-work__side').boundingBox();
  const rtlTasks = await page.locator('.employee-work__task-section').boundingBox();
  expect(rtlSide).not.toBeNull();
  expect(rtlTasks).not.toBeNull();
  expect(rtlSide!.x).toBeGreaterThan(rtlTasks!.x);
  await page.screenshot({ path: testInfo.outputPath('employee-work-arabic-desktop.png'), fullPage: true });

  await page.setViewportSize({ width: 768, height: 1024 });
  dimensions = await documentWidth();
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1);
  const tabletSide = await page.locator('.employee-work__side').boundingBox();
  const tabletTasks = await page.locator('.employee-work__task-section').boundingBox();
  expect(tabletSide).not.toBeNull();
  expect(tabletTasks).not.toBeNull();
  expect(tabletTasks!.y).toBeLessThan(tabletSide!.y);

  await page.setViewportSize({ width: 390, height: 844 });
  dimensions = await documentWidth();
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport + 1);
  const mobileSide = await page.locator('.employee-work__side').boundingBox();
  const mobileTasks = await page.locator('.employee-work__task-section').boundingBox();
  expect(mobileSide).not.toBeNull();
  expect(mobileTasks).not.toBeNull();
  expect(mobileTasks!.y).toBeLessThan(mobileSide!.y);
  await page.screenshot({ path: testInfo.outputPath('employee-work-arabic-mobile.png'), fullPage: true });

  await page.setViewportSize({ width: 1440, height: 1000 });
  const english = await page.goto('/employees/work?locale=en&status=pending&per_page=25&sort=title&direction=desc');
  expect(english?.status()).toBe(200);
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.getByRole('heading', { name: 'Employee work follow-up', level: 1 })).toBeVisible();
  await expect(page.locator('select[name="status"]')).toHaveValue('pending');
  await expect(page.locator('select[name="per_page"]')).toHaveValue('25');
  await expect(page.locator('select[name="sort"]')).toHaveValue('title');
  await expect(page.locator('select[name="direction"]')).toHaveValue('desc');
  await expect(page.locator('input[name="locale"]')).toHaveCount(1);
  const ltrSide = await page.locator('.employee-work__side').boundingBox();
  const ltrTasks = await page.locator('.employee-work__task-section').boundingBox();
  expect(ltrSide).not.toBeNull();
  expect(ltrTasks).not.toBeNull();
  expect(ltrSide!.x).toBeLessThan(ltrTasks!.x);

  await page.locator('#work-search').fill('Synthetic');
  await page.getByRole('button', { name: 'Apply filters' }).click();
  await expect(page).toHaveURL(/status=pending/);
  await expect(page).toHaveURL(/per_page=25/);
  await expect(page).toHaveURL(/sort=title/);
  await expect(page).toHaveURL(/direction=desc/);
  await expect(page.locator('#work-search')).toHaveValue('Synthetic');
});

test('rejects selecting a different employee profile', async ({ page }) => {
  const response = await page.goto(`/employees/work?locale=en&employee_id=${fixture.peer_profile_id}`);
  expect(response?.status()).toBe(404);
  await expect(page.getByText('Synthetic peer employee', { exact: true })).toHaveCount(0);
  await expect(page.getByText('Peer task must stay hidden', { exact: true })).toHaveCount(0);
});
