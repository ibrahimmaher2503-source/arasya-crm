import { randomUUID } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { expect, test, type Page, type TestInfo, type Route } from '@playwright/test';

type Progress = {
  tour_id: string;
  version: number;
  step_key: string | null;
  status: string | null;
};

type TourConfig = {
  tour_id: string;
  version: number;
  steps: Array<{ key: string; anchor: string; title: string; body: string }>;
  csrf_token: string;
  messages: {
    save_failed: string;
    status_failed: string;
    missing_anchors: string;
  };
};

type FixtureOptions = { failRead?: boolean; failWrite?: boolean; articleIds?: string[] };
type FixtureRun = {
  requests: string[];
  unexpected: string[];
  external: string[];
  writes: Array<{ version: number; step_key: string; status: string }>;
  progress: () => Progress;
};

const syntheticOrigin = 'http://tutorial-help.test';
const fixtureScript = resolve('tests/Browser/tutorial-help-fixtures.php');
const outputOwners = new Map<string, string>();

function renderHtml(testInfo: TestInfo, locale: 'ar' | 'en', articleId?: string): string {
  const htmlPath = testInfo.outputPath(`rendered-help-${locale}${articleId ? `-${articleId}` : ''}.html`);
  const outputDirectory = dirname(htmlPath);
  mkdirSync(outputDirectory, { recursive: true });

  let ownerToken = outputOwners.get(outputDirectory);
  if (!ownerToken) {
    ownerToken = randomUUID();
    writeFileSync(join(outputDirectory, '.tutorial-help-owner'), ownerToken, { flag: 'wx' });
    outputOwners.set(outputDirectory, ownerToken);
  }

  const args = [fixtureScript, locale, htmlPath, outputDirectory, ownerToken];
  if (articleId) args.push(articleId);
  const result = spawnSync('php', args, {
    cwd: process.cwd(),
    stdio: 'inherit',
    env: {
      PATH: process.env.PATH ?? '',
      SystemRoot: process.env.SystemRoot ?? '',
      SystemDrive: process.env.SystemDrive ?? '',
      TEMP: process.env.TEMP ?? '',
      TMP: process.env.TMP ?? '',
      APP_ENV: 'testing',
      APP_CONFIG_CACHE: join(outputDirectory, 'config-cache-must-not-exist.php'),
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: ':memory:',
      SESSION_DRIVER: 'array',
      CACHE_STORE: 'array',
      QUEUE_CONNECTION: 'sync',
      VIEW_COMPILED_PATH: join(outputDirectory, 'compiled-views'),
    },
  });

  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`Synthetic Help fixture exited with ${result.status}.`);
  if (!existsSync(htmlPath)) throw new Error('The synthetic Help fixture did not create its caller-owned HTML output.');

  return readFileSync(htmlPath, 'utf8');
}

async function installFixture(
  page: Page,
  testInfo: TestInfo,
  locales: Array<'ar' | 'en'> = ['ar'],
  options: FixtureOptions = {},
): Promise<FixtureRun> {
  const documents = new Map(locales.map(locale => [locale, renderHtml(testInfo, locale)]));
  const articles = new Map<string, string>();
  for (const locale of locales) {
    for (const articleId of options.articleIds ?? []) {
      articles.set(`${locale}:${articleId}`, renderHtml(testInfo, locale, articleId));
    }
  }
  const run: FixtureRun = {
    requests: [],
    unexpected: [],
    external: [],
    writes: [],
    progress: () => progress,
  };
  let progress: Progress = { tour_id: 'help-center', version: 1, step_key: null, status: null };

  page.on('request', request => {
    const url = new URL(request.url());
    const route = `${request.method()} ${url.pathname}`;
    run.requests.push(route);
    if (url.origin !== syntheticOrigin) run.external.push(url.origin);
    if (request.method() !== 'GET' && url.pathname !== '/help/api/progress/help-center') {
      run.unexpected.push(route);
    }
  });

  await page.route('**/*', async (route: Route) => {
    const request = route.request();
    const url = new URL(request.url());

    if (url.origin !== syntheticOrigin) {
      run.external.push(url.origin);
      await route.abort();
      return;
    }

    if (url.pathname === '/help' && request.method() === 'GET') {
      const locale = url.searchParams.get('locale') === 'en' ? 'en' : 'ar';
      const html = documents.get(locale);
      if (!html) {
        run.unexpected.push(`${request.method()} ${url.pathname}?locale=${locale}`);
        await route.abort();
        return;
      }
      await route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
      return;
    }

    const articleMatch = url.pathname.match(/^\/help\/articles\/([a-z0-9-]+)$/);
    if (articleMatch && request.method() === 'GET') {
      const locale = url.searchParams.get('locale') === 'en' ? 'en' : 'ar';
      const html = articles.get(`${locale}:${articleMatch[1]}`);
      if (!html) {
        run.unexpected.push(`${request.method()} ${url.pathname}?locale=${locale}`);
        await route.abort();
        return;
      }
      await route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
      return;
    }

    if (url.pathname === '/help/api/progress/help-center') {
      if (request.method() === 'GET') {
        if (options.failRead) {
          await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: { code: 'progress_unavailable' } }) });
          return;
        }
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: progress }) });
        return;
      }

      if (request.method() === 'PATCH') {
        const payload = request.postDataJSON() as { version: number; step_key: string; status: string };
        run.writes.push(payload);
        if (request.headers()['x-csrf-token'] !== 'synthetic-help-csrf-token') {
          await route.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ error: { code: 'csrf_failed' } }) });
          return;
        }
        if (options.failWrite) {
          await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ error: { code: 'progress_unavailable' } }) });
          return;
        }
        progress = { tour_id: 'help-center', ...payload };
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: progress }) });
        return;
      }
    }

    run.unexpected.push(`${request.method()} ${url.pathname}`);
    await route.abort();
  });

  return run;
}

async function visitHelp(page: Page, locale: 'ar' | 'en'): Promise<void> {
  const response = await page.goto(`/help?locale=${locale}`);
  expect(response?.status(), 'The local route-fulfilled synthetic Blade document should load').toBe(200);
  await expect(page.locator('[data-product-tour]')).toHaveCount(1);
}

async function readTourConfig(page: Page): Promise<TourConfig> {
  const text = await page.locator('[data-product-tour-config]').textContent();
  if (!text) throw new Error('The real rendered Help component did not include its tour configuration.');
  return JSON.parse(text) as TourConfig;
}

function expectMockOnly(run: FixtureRun): void {
  expect(run.external, 'No browser request may reach an external origin').toEqual([]);
  expect(run.unexpected, 'Only the synthetic Help page and its mocked progress endpoint are allowlisted').toEqual([]);
}

test('synthetic Blade fixture supports RTL/LTR, light/dark, responsive width, and screenshots', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo, ['ar', 'en']);

  for (const locale of ['ar', 'en'] as const) {
    await visitHelp(page, locale);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

    const dimensions = await page.evaluate(() => ({
      viewport: document.documentElement.clientWidth,
      document: document.documentElement.scrollWidth,
      body: document.body.scrollWidth,
    }));
    expect(dimensions.document, `${locale} document width`).toBeLessThanOrEqual(dimensions.viewport);
    expect(dimensions.body, `${locale} body width`).toBeLessThanOrEqual(dimensions.viewport);

    await page.screenshot({ path: testInfo.outputPath(`help-${locale}-light.png`), fullPage: true });
    const lightBackground = await page.locator('html').evaluate(element => getComputedStyle(element).backgroundColor);
    await page.locator('[data-theme-toggle]').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    const darkBackground = await page.locator('html').evaluate(element => getComputedStyle(element).backgroundColor);
    expect(darkBackground).not.toBe(lightBackground);
    await page.screenshot({ path: testInfo.outputPath(`help-${locale}-dark.png`), fullPage: true });
  }

  expect(run.writes, 'Rendering and theme changes do not save tour progress').toEqual([]);
  expectMockOnly(run);
});

test('synthetic article link opens its rendered guide', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo, ['en'], { articleIds: ['contact-privacy'] });
  await visitHelp(page, 'en');

  await page.locator('article[data-help-article="contact-privacy"] a').click();
  await expect(page).toHaveURL(`${syntheticOrigin}/help/articles/contact-privacy?locale=en`);
  await expect(page.locator('[data-help-article="contact-privacy"]')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Audited reveal' })).toBeVisible();
  await expect(page.getByText(/requires contact\.reveal/)).toBeVisible();
  expect(run.writes).toEqual([]);
  expectMockOnly(run);
});

test('synthetic progress mock exercises start, navigation, pause, resume, reload, restart, skip, close, and Escape', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo);
  await visitHelp(page, 'ar');
  const config = await readTourConfig(page);
  const popover = page.locator('[data-tour-popover]');
  const start = page.locator('[data-tour-start]');
  const restart = page.locator('[data-tour-restart]');

  await start.click();
  await expect(popover).toBeVisible();
  await expect(popover).toHaveAttribute('aria-modal', 'true');
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[0].title);
  await expect(page.locator('[data-tour-close]')).toBeFocused();

  await page.keyboard.press('Shift+Tab');
  await expect(page.locator('[data-tour-pause]')).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.locator('[data-tour-close]')).toBeFocused();

  await page.locator('[data-tour-next]').click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[1].title);
  await page.locator('[data-tour-previous]').click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[0].title);
  await page.locator('[data-tour-next]').click();
  await page.locator('[data-tour-pause]').click();
  await expect(popover).toBeHidden();
  await expect(start).toBeFocused();
  expect(run.progress()).toMatchObject({ step_key: 'search', status: 'paused' });

  await start.click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[1].title);
  await page.locator('[data-tour-pause]').click();
  await page.reload();
  await start.click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[1].title);

  await restart.click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[0].title);
  await page.locator('[data-tour-skip]').click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[1].title);
  expect(run.progress()).toMatchObject({ step_key: 'search', status: 'skipped' });
  await page.locator('[data-tour-close]').click();
  await expect(popover).toBeHidden();
  await expect(restart).toBeFocused();

  await restart.click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[0].title);
  await page.keyboard.press('Escape');
  await expect(popover).toBeHidden();
  await expect(restart).toBeFocused();
  expect(run.writes.length).toBeGreaterThan(8);
  expect(run.writes.every(write => write.version === 1 && config.steps.some(step => step.key === write.step_key))).toBe(true);
  expectMockOnly(run);
});

test('synthetic tour isolates background keyboard focus and restores it on close', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo);
  await visitHelp(page, 'ar');
  const start = page.locator('[data-tour-start]');
  const popover = page.locator('[data-tour-popover]');
  const search = page.locator('[data-tour="help.index.search"] input[name="q"]');

  await start.click();
  await expect(popover).toBeVisible();
  await expect(page.locator('[data-tour-close]')).toBeFocused();
  await expect.poll(() => search.evaluate(element => element.closest('[inert]') !== null)).toBe(true);
  await page.keyboard.press('Tab');
  expect(await page.evaluate(() => document.activeElement?.closest('[data-tour-popover]') !== null)).toBe(true);

  await page.locator('[data-tour-close]').click();
  await expect(popover).toBeHidden();
  await expect(start).toBeFocused();
  await expect.poll(() => search.evaluate(element => element.closest('[inert]') === null)).toBe(true);
  await page.keyboard.press('Shift+Tab');
  await expect(page.locator('[data-tour="help.index.search"] button[type="submit"]')).toBeFocused();
  expectMockOnly(run);
});

test('synthetic keyboard arrows follow RTL/LTR and popover stays in the viewport after resize', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo, ['ar', 'en']);

  for (const locale of ['ar', 'en'] as const) {
    await visitHelp(page, locale);
    const config = await readTourConfig(page);
    await page.locator('[data-tour-start]').click();
    const forward = locale === 'ar' ? 'ArrowLeft' : 'ArrowRight';
    const backward = locale === 'ar' ? 'ArrowRight' : 'ArrowLeft';

    await page.keyboard.press(forward);
    await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[1].title);
    await page.keyboard.press(backward);
    await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[0].title);
    await page.setViewportSize({ width: 360, height: 740 });
    await expect.poll(async () => page.locator('[data-tour-popover]').evaluate(element => {
      const rect = element.getBoundingClientRect();
      return rect.left >= 0 && rect.top >= 0 && rect.right <= window.innerWidth && rect.bottom <= window.innerHeight;
    })).toBe(true);
    await page.locator('[data-tour-pause]').click();
  }

  expectMockOnly(run);
});

test('synthetic engine skips hidden and removed anchors without marking the tour completed', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo);
  await visitHelp(page, 'ar');
  const config = await readTourConfig(page);
  await page.locator('[data-tour="help.index.search"]').evaluate(element => { (element as HTMLElement).hidden = true; });
  await page.locator('[data-tour="help.index.collections"]').evaluate(element => element.remove());

  await page.locator('[data-tour-start]').click();
  await page.locator('[data-tour-next]').click();
  await expect(page.locator('[data-tour-title]')).toHaveText(config.steps[3].title);
  expect(run.progress()).toMatchObject({ step_key: 'tour-controls', status: 'skipped' });
  await page.locator('[data-tour-next]').click();
  await expect(page.locator('[data-tour-popover]')).toBeHidden();
  await expect(page.locator('[data-tour-message]')).toHaveText(config.messages.missing_anchors);
  expect(run.progress()).toMatchObject({ step_key: 'tour-controls', status: 'skipped' });
  expect(run.progress().status).not.toBe('completed');
  expectMockOnly(run);
});

test('synthetic persistence failures announce the configured status and save errors', async ({ page }, testInfo) => {
  const readFailure = await installFixture(page, testInfo, ['ar'], { failRead: true });
  await visitHelp(page, 'ar');
  const config = await readTourConfig(page);
  await expect(page.locator('[data-tour-status]')).toHaveText(config.messages.status_failed);
  expectMockOnly(readFailure);

  await page.goto('about:blank');
  const writeFailure = await installFixture(page, testInfo, ['en'], { failWrite: true });
  await visitHelp(page, 'en');
  const englishConfig = await readTourConfig(page);
  await page.locator('[data-tour-start]').click();
  await expect(page.locator('[data-tour-message]')).toHaveText(englishConfig.messages.save_failed);
  expect(writeFailure.writes.length).toBeGreaterThan(0);
  expectMockOnly(writeFailure);
});

test('synthetic rendered Help controls are named, IDs are unique, and the page has one h1', async ({ page }, testInfo) => {
  const run = await installFixture(page, testInfo);
  await visitHelp(page, 'ar');

  const ids = await page.locator('[id]').evaluateAll(elements => elements.map(element => element.id));
  expect(new Set(ids).size, 'Rendered Help and shell IDs should be unique').toBe(ids.length);

  const controls = await page.locator('input:not([type="hidden"]), select, textarea').evaluateAll(elements =>
    elements.map(element => ({
      name: element.getAttribute('aria-label')?.trim()
        || (element as HTMLInputElement).labels?.[0]?.textContent?.trim()
        || '',
    })),
  );
  expect(controls.length).toBeGreaterThan(0);
  expect(controls.every(control => control.name.length > 0), 'Every visible form control should have an accessible name').toBe(true);

  const buttons = page.getByRole('button');
  const buttonCount = await buttons.count();
  expect(buttonCount).toBeGreaterThan(0);
  for (let index = 0; index < buttonCount; index += 1) {
    await expect(buttons.nth(index), `button ${index + 1} should have an accessible name`).toHaveAccessibleName(/\S+/);
  }
  await expect(page.getByRole('heading', { level: 1 }), 'The shared shell and Help view must expose one page-level heading').toHaveCount(1);
  expect(run.writes).toEqual([]);
  expectMockOnly(run);
});

// This page.route-backed progress store is an in-memory browser contract, not the MariaDB proof.
