import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { expect, test, type Page, type Route, type TestInfo } from '@playwright/test';

const syntheticOrigin = 'http://user-profile.test';
const fixtureScript = resolve('tests/Browser/user-profile-fixtures.php');
const runId = process.env.ARASYA_USER_PROFILE_RUN_ID ?? '';
const runDirectory = resolve(process.env.ARASYA_USER_PROFILE_RUN_DIRECTORY ?? '');
const projectRoot = resolve(process.cwd());
const expectedOwner = `A1-CORE-016 user-profile ${runId}`;
const documents = new Map<'ar' | 'en', string>();

if (!/^[a-f0-9-]{36}$/i.test(runId)
  || !runDirectory.startsWith(resolve(projectRoot, 'storage/framework/testing/user-profile') + '\\')
  || !existsSync(join(runDirectory, '.owner'))
  || readFileSync(join(runDirectory, '.owner'), 'utf8') !== expectedOwner) {
  throw new Error('User-profile browser test requires its owned, isolated synthetic run directory.');
}

type FixtureRun = { requests: string[]; unexpected: string[]; external: string[] };

function renderHtml(projectName: string, locale: 'ar' | 'en'): string {
  const htmlPath = join(runDirectory, `${projectName}-user-profile-${locale}.html`);
  if (existsSync(htmlPath)) throw new Error(`Refusing to overwrite the rendered fixture: ${htmlPath}`);

  const childEnvironment: NodeJS.ProcessEnv = {
    PATH: process.env.PATH,
    SystemRoot: process.env.SystemRoot,
    SystemDrive: process.env.SystemDrive,
    TEMP: process.env.TEMP,
    TMP: process.env.TMP,
    APP_ENV: process.env.APP_ENV,
    APP_KEY: process.env.APP_KEY,
    APP_DEBUG: process.env.APP_DEBUG,
    APP_URL: process.env.APP_URL,
    APP_CONFIG_CACHE: process.env.APP_CONFIG_CACHE,
    APP_ROUTES_CACHE: process.env.APP_ROUTES_CACHE,
    APP_EVENTS_CACHE: process.env.APP_EVENTS_CACHE,
    APP_SERVICES_CACHE: process.env.APP_SERVICES_CACHE,
    APP_PACKAGES_CACHE: process.env.APP_PACKAGES_CACHE,
    DB_CONNECTION: process.env.DB_CONNECTION,
    DB_DATABASE: process.env.DB_DATABASE,
    DB_URL: process.env.DB_URL,
    DB_HOST: process.env.DB_HOST,
    DB_PORT: process.env.DB_PORT,
    DB_USERNAME: process.env.DB_USERNAME,
    DB_PASSWORD: process.env.DB_PASSWORD,
    SESSION_DRIVER: process.env.SESSION_DRIVER,
    CACHE_STORE: process.env.CACHE_STORE,
    QUEUE_CONNECTION: process.env.QUEUE_CONNECTION,
    MAIL_MAILER: process.env.MAIL_MAILER,
    LOG_CHANNEL: process.env.LOG_CHANNEL,
    VIEW_COMPILED_PATH: process.env.VIEW_COMPILED_PATH,
    ARASYA_USER_PROFILE_RUN_ID: runId,
    ARASYA_USER_PROFILE_RUN_DIRECTORY: runDirectory,
  };
  const result = spawnSync('php', [fixtureScript, htmlPath, locale], {
    cwd: projectRoot,
    env: childEnvironment,
    stdio: 'inherit',
    timeout: 30_000,
  });

  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`User-profile ${locale} Blade fixture exited with ${result.status ?? 'unknown'}.`);
  if (!existsSync(htmlPath)) throw new Error(`The user-profile ${locale} Blade fixture was not created.`);

  return readFileSync(htmlPath, 'utf8');
}

async function installFixture(page: Page, locales: Array<'ar' | 'en'> = ['ar', 'en']): Promise<FixtureRun> {
  const run: FixtureRun = { requests: [], unexpected: [], external: [] };
  page.on('request', request => {
    const url = new URL(request.url());
    run.requests.push(`${request.method()} ${url.pathname}`);
    if (url.origin !== syntheticOrigin) run.external.push(url.origin);
  });

  await page.route('**/*', async (route: Route) => {
    const request = route.request();
    const url = new URL(request.url());
    if (url.origin !== syntheticOrigin) {
      run.external.push(url.origin);
      await route.abort();
      return;
    }

    if (request.method() === 'GET' && url.pathname === '/favicon.ico') {
      await route.fulfill({ status: 204 });
      return;
    }

    if (request.method() === 'GET' && url.pathname === '/access/users/SYNTHETIC-USER') {
      const locale = url.searchParams.get('locale') === 'en' ? 'en' : 'ar';
      const html = documents.get(locale);
      if (!html || !locales.includes(locale)) {
        run.unexpected.push(`${request.method()} ${url.pathname}?locale=${locale}`);
        await route.abort();
        return;
      }
      await route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
      return;
    }

    run.unexpected.push(`${request.method()} ${url.pathname}`);
    await route.abort();
  });

  return run;
}

async function visitProfile(page: Page, locale: 'ar' | 'en'): Promise<void> {
  const response = await page.goto(`/access/users/SYNTHETIC-USER?locale=${locale}`);
  expect(response?.status(), 'The local route-fulfilled Blade page should load').toBe(200);
  await expect(page.locator('html')).toHaveAttribute('lang', locale);
  await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
  await expect(page.locator('.dashboard-sidebar')).toHaveCount(1);
  await expect(page.locator('.dashboard-topbar')).toHaveCount(1);
  await expect(page.getByTestId('admin-page-header')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
  await expect(page.locator('.user-profile')).toHaveCount(1);
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const widths = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }));
  expect(widths.document, 'document width').toBeLessThanOrEqual(widths.viewport);
  expect(widths.body, 'body width').toBeLessThanOrEqual(widths.viewport);
}

async function setTheme(page: Page, theme: 'light' | 'dark'): Promise<string> {
  await page.locator('html').evaluate((element, value) => {
    if (value === 'dark') element.setAttribute('data-theme', 'dark');
    else element.removeAttribute('data-theme');
  }, theme);
  return page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-background').trim());
}

async function saveScreenshot(page: Page, locale: 'ar' | 'en', theme: 'light' | 'dark', testInfo: TestInfo): Promise<void> {
  await page.screenshot({
    path: testInfo.outputPath(`user-profile-${locale}-${theme}.png`),
    fullPage: true,
  });
}

function expectOfflineOnly(run: FixtureRun): void {
  expect(run.external, 'No browser request may reach an external origin').toEqual([]);
  expect(run.unexpected, 'Only the synthetic Blade document is allowed').toEqual([]);
}

test.beforeAll(async ({}, testInfo) => {
  for (const locale of ['ar', 'en'] as const) {
    documents.set(locale, renderHtml(testInfo.project.name, locale));
  }
});

test('renders the real shell across locale, theme, desktop/mobile widths, and captures review screenshots', async ({ page }, testInfo) => {
  const run = await installFixture(page);

  for (const locale of ['ar', 'en'] as const) {
    await visitProfile(page, locale);
    await expectNoHorizontalOverflow(page);

    let lightBackground = '';
    for (const theme of ['light', 'dark'] as const) {
      const background = await setTheme(page, theme);
      expect(background).not.toBe('');
      if (theme === 'light') lightBackground = background;
      else expect(background).not.toBe(lightBackground);
      await saveScreenshot(page, locale, theme, testInfo);
    }
  }

  expectOfflineOnly(run);
});

test('keeps real account and access forms separate, labelled, keyboard navigable, and responsive', async ({ page }, testInfo) => {
  const run = await installFixture(page, ['en']);
  await visitProfile(page, 'en');

  const accountForm = page.locator('#user-form');
  const accessForm = page.locator('form[data-testid="user-access-form"]');
  await expect(accountForm).toHaveAttribute('method', 'post');
  await expect(accountForm).toHaveAttribute('action', '/access/users/SYNTHETIC-USER');
  await expect(accessForm).toHaveAttribute('method', 'post');
  await expect(accessForm).toHaveAttribute('action', '/access/users/SYNTHETIC-USER/access');
  await expect(accountForm.locator('form')).toHaveCount(0);
  await expect(accessForm.locator('form')).toHaveCount(0);
  await expect(accountForm.locator('input[name="_token"]')).toHaveValue('synthetic-user-profile-csrf-token');
  await expect(accessForm.locator('input[name="_token"]')).toHaveValue('synthetic-user-profile-csrf-token');
  await expect(accountForm.locator('input[name="version"]')).toHaveValue('12');
  await expect(accessForm.locator('input[name="version"]')).toHaveValue('12');
  await expect(accountForm.getByRole('button', { name: 'Save user details' })).toHaveAttribute('type', 'submit');
  await expect(accessForm.getByRole('button', { name: 'Save roles and permissions' })).toHaveAttribute('type', 'submit');

  await expect(page.getByLabel('Full name')).toHaveValue('Maha Saleh');
  await expect(page.getByLabel('Email address')).toHaveValue('maha.saleh@example.test');
  await expect(accountForm.getByLabel('Branch', { exact: true })).toHaveValue('branch-retained');
  await expect(accountForm.getByLabel('Department', { exact: true })).toHaveValue('department-retained');
  await expect(page.locator('#user-password')).toHaveValue('');
  await expect(page.locator('#user-password')).not.toHaveJSProperty('required', true);
  await expect(accountForm.locator('input[name="active"][type="checkbox"]')).toBeChecked();
  expect(await accountForm.evaluate(form => new FormData(form).getAll('active'))).toEqual(['0', '1']);
  await expect(accessForm.locator('input[name="role_ids[]"][type="checkbox"][value="role-1"]')).toBeChecked();
  await expect(accessForm.locator('input[name="role_ids[]"][type="hidden"][value="role-retained"]')).toHaveCount(1);

  const sectionNavigation = page.getByRole('navigation', { name: 'User detail sections' });
  for (const section of ['user-basic-data', 'user-organization', 'user-access', 'user-security']) {
    const link = sectionNavigation.locator(`a[href="#${section}"]`);
    await expect(link).toHaveCount(1);
    await expect(page.locator(`#${section}`)).toHaveCount(1);
  }

  const skipLink = page.locator('.skip-link');
  await page.keyboard.press('Tab');
  await expect(skipLink).toBeFocused();
  await expect(skipLink).toHaveCSS('outline-style', 'solid');
  await expect(skipLink).toHaveCSS('outline-width', '3px');
  const organizationLink = sectionNavigation.getByRole('link', { name: 'Organization', exact: true });
  await organizationLink.focus();
  await page.keyboard.press('Enter');
  expect(new URL(page.url()).hash).toBe('#user-organization');
  await expect(organizationLink).toHaveAttribute('aria-current', 'location');
  await expect(sectionNavigation.locator('a[aria-current="location"]')).toHaveCount(1);
  await expect(page.locator('#user-organization')).toBeInViewport();

  const layout = await page.evaluate(() => {
    const rect = (selector: string) => {
      const element = document.querySelector(selector);
      if (!element) throw new Error(`Missing layout element: ${selector}`);
      const box = element.getBoundingClientRect();
      return { x: box.x, y: box.y, right: box.right, bottom: box.bottom, width: box.width, height: box.height };
    };
    const basicSection = rect('#user-basic-data');
    const organizationSection = rect('#user-organization');
    return {
      viewport: { width: innerWidth, height: innerHeight },
      basicSection,
      organizationSection,
      fullName: rect('#user-name'),
      email: rect('#user-email'),
      formSectionBorder: getComputedStyle(document.querySelector('#user-basic-data .ui-form-section') as Element).borderTopWidth,
    };
  });
  await testInfo.attach('user-profile-layout-measurements.json', {
    body: Buffer.from(JSON.stringify(layout, null, 2)),
    contentType: 'application/json',
  });

  if (testInfo.project.name.startsWith('desktop')) {
    expect(Math.abs(layout.basicSection.y - layout.organizationSection.y)).toBeLessThanOrEqual(1);
    expect(layout.basicSection.right <= layout.organizationSection.x || layout.organizationSection.right <= layout.basicSection.x).toBe(true);
  } else {
    expect(layout.organizationSection.y).toBeGreaterThanOrEqual(layout.basicSection.bottom);
  }
  expect(layout.formSectionBorder).toBe('0px');
  await expectNoHorizontalOverflow(page);
  await saveScreenshot(page, 'en', 'light', testInfo);

  const actionContainer = accountForm.locator('.user-profile__form-actions');
  const actionOrder = await actionContainer.evaluate(container => {
    const controls = Array.from(container.querySelectorAll<HTMLElement>(':scope > a, :scope > button'));
    const label = (control: HTMLElement) => control.textContent?.trim() ?? '';
    const visualOrder = [...controls]
      .sort((left, right) => {
        const leftRect = left.getBoundingClientRect();
        const rightRect = right.getBoundingClientRect();
        return leftRect.top - rightRect.top || leftRect.left - rightRect.left;
      })
      .map(label);

    return { domOrder: controls.map(label), visualOrder };
  });
  expect(actionOrder.domOrder).toHaveLength(2);
  expect(actionOrder.domOrder, 'Account action keyboard order must match visual order').toEqual(actionOrder.visualOrder);

  const actionControl = (name: string) => {
    if (name === 'Cancel') return accountForm.getByRole('link', { name, exact: true });
    if (name === 'Save user details') return accountForm.getByRole('button', { name, exact: true });
    throw new Error(`Unexpected account form action: ${name}`);
  };
  await actionControl(actionOrder.visualOrder[0]).focus();
  await page.keyboard.press('Tab');
  await expect(actionControl(actionOrder.visualOrder[1])).toBeFocused();

  expectOfflineOnly(run);
});
