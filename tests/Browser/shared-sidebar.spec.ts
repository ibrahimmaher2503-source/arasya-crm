import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test, type Locator, type Page } from '@playwright/test';

type DemoLogin = { email: string; password: string };
type DemoFixture = { admin: DemoLogin; sales: DemoLogin };

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8773';
const fixturePath = resolve(process.env.ARASYA_BROWSER_FIXTURE || 'storage/app/private/demo-credentials.json');
const parsedBaseURL = new URL(baseURL);

if (parsedBaseURL.protocol !== 'http:'
  || !['127.0.0.1', 'localhost'].includes(parsedBaseURL.hostname)
  || parsedBaseURL.username !== ''
  || parsedBaseURL.password !== '') {
  throw new Error('Sidebar browser checks may use only unauthenticated loopback HTTP.');
}

async function readFixture(): Promise<DemoFixture> {
  const parsed = JSON.parse(await readFile(fixturePath, 'utf8')) as Partial<DemoFixture>;

  for (const actor of ['admin', 'sales'] as const) {
    const login = parsed[actor];
    if (!login || typeof login.email !== 'string' || login.email === ''
      || typeof login.password !== 'string' || login.password === '') {
      throw new Error('The ignored demo fixture must provide ' + actor + ' email and password fields.');
    }
  }

  return parsed as DemoFixture;
}

async function login(page: Page, user: DemoLogin): Promise<void> {
  const response = await page.goto('/login?locale=ar');
  expect(response?.status()).toBe(200);
  await page.getByLabel('البريد الإلكتروني', { exact: true }).fill(user.email);
  await page.getByLabel('كلمة المرور', { exact: true }).fill(user.password);
  const post = page.waitForResponse(candidate => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await page.getByRole('button', { name: 'تسجيل الدخول', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect.poll(() => new URL(page.url()).pathname).toBe('/dashboard');
}

async function setTheme(page: Page, theme: 'light' | 'dark'): Promise<void> {
  const root = page.locator('html');
  const isDark = await root.getAttribute('data-theme') === 'dark';
  if ((theme === 'dark') !== isDark) {
    await page.locator('[data-theme-toggle]').click();
  }

  await expect(root).toHaveCSS('color-scheme', theme);
  await expect(page.locator('[data-theme-toggle]')).toHaveAttribute('aria-pressed', String(theme === 'dark'));
}

async function openMobileNavigation(page: Page, locale: 'ar' | 'en'): Promise<void> {
  const toggle = page.locator('.mobile-menu');
  await expect(toggle).toHaveAccessibleName(locale === 'ar' ? 'فتح القائمة' : 'Open navigation');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#primary-navigation')).toBeVisible();
}

function isMobileViewport(page: Page): boolean {
  return (page.viewportSize()?.width ?? Infinity) <= 850;
}

function navGroup(navigation: Locator, label: string): Locator {
  return navigation.locator('summary').getByText(label, { exact: true }).locator('xpath=ancestor::details[1]');
}

test('authenticated sidebar keeps its localized name, search state, and active destination across viewport themes', async ({ page }) => {
  const fixture = await readFixture();
  await login(page, fixture.admin);
  const mobile = isMobileViewport(page);

  for (const locale of ['ar', 'en'] as const) {
    let expectedNavigationLinks: string[] | undefined;
    const response = await page.goto('/dashboard?locale=' + locale);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

    for (const theme of ['light', 'dark'] as const) {
      expect((await page.goto('/dashboard?locale=' + locale))?.status()).toBe(200);
      await setTheme(page, theme);

      if (mobile) {
        await openMobileNavigation(page, locale);
      } else {
        const widthToggle = page.locator('[data-sidebar-collapse]');
        const stableName = locale === 'ar' ? 'تبديل عرض القائمة' : 'Toggle navigation width';
        await expect(widthToggle).toBeVisible();
        await expect(widthToggle).toHaveAccessibleName(stableName);
        await widthToggle.focus();
        await page.keyboard.press('Enter');
        await expect(widthToggle).toHaveAttribute('aria-expanded', 'false');
        await expect(widthToggle).toHaveAccessibleName(stableName);
        await page.keyboard.press('Enter');
        await expect(widthToggle).toHaveAttribute('aria-expanded', 'true');
        await expect(widthToggle).toHaveAccessibleName(stableName);
      }

      const navigation = page.locator('#primary-navigation');
      await expect(navigation).toBeVisible();
      const navigationLinks = await navigation.locator('a[href]').evaluateAll(links => links
        .map(link => (link as HTMLAnchorElement).getAttribute('href')!)
        .sort());
      if (expectedNavigationLinks === undefined) expectedNavigationLinks = navigationLinks;
      else expect(navigationLinks).toEqual(expectedNavigationLinks);
      const current = navigation.locator('a[aria-current="page"]');
      await expect(current).toHaveCount(1);
      await expect(current).toHaveAttribute('href', '/dashboard?locale=' + locale);

      const workspace = navGroup(navigation, locale === 'ar' ? 'مركز العمل' : 'Workspace');
      const crm = navGroup(navigation, locale === 'ar' ? 'إدارة علاقات العملاء' : 'CRM');
      const employees = navGroup(navigation, locale === 'ar' ? 'الموظفون' : 'Employees');
      await expect(workspace).toHaveJSProperty('open', true);
      await expect(employees).toHaveJSProperty('open', false);

      await crm.locator('summary').click();
      await expect(crm).toHaveJSProperty('open', true);
      const search = page.locator('.sidebar-search input[data-nav-search]');
      await search.fill(locale === 'ar' ? 'العملاء المحتملون' : 'Leads');
      await expect(crm).toHaveJSProperty('open', true);
      await expect(employees.locator('xpath=..')).toBeHidden();
      await search.fill('');
      await expect(workspace).toHaveJSProperty('open', true);
      await expect(crm).toHaveJSProperty('open', true);
      await expect(employees).toHaveJSProperty('open', false);

      for (const path of ['/access/users', '/crm/leads', '/tasks']) {
        const pageResponse = await page.goto(path + '?locale=' + locale);
        expect(pageResponse?.status()).toBe(200);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
        await setTheme(page, theme);

        if (mobile) {
          await openMobileNavigation(page, locale);
        }

        const screenNavigationLinks = await page.locator('#primary-navigation a[href]').evaluateAll(links => links
          .map(link => (link as HTMLAnchorElement).getAttribute('href')!)
          .sort());
        expect(screenNavigationLinks).toEqual(expectedNavigationLinks);
        const activeLinks = page.locator('#primary-navigation a[aria-current="page"]');
        await expect(activeLinks).toHaveCount(1);
        await expect(activeLinks).toHaveAttribute('href', path + '?locale=' + locale);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
      }
    }
  }
});

test('mobile sidebar dismisses with Escape and an outside click in RTL and LTR', async ({ page }) => {
  test.skip(!isMobileViewport(page), 'Mobile drawer interactions run at the mobile breakpoint.');

  const fixture = await readFixture();
  await login(page, fixture.admin);

  const viewport = page.viewportSize();
  expect(viewport).not.toBeNull();

  for (const locale of ['ar', 'en'] as const) {
    const response = await page.goto('/dashboard?locale=' + locale);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

    const shell = page.locator('.dashboard-shell');
    const toggle = page.locator('.mobile-menu');
    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(shell).toHaveClass(/is-nav-open/);
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(shell).not.toHaveClass(/is-nav-open/);
    await expect(toggle).toBeFocused();

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await shell.click({ position: { x: locale === 'ar' ? 2 : viewport!.width - 2, y: Math.floor(viewport!.height / 2) } });
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(shell).not.toHaveClass(/is-nav-open/);
  }
});

test('sales navigation exposes only routes its session can open', async ({ page }) => {
  const fixture = await readFixture();
  await login(page, fixture.sales);
  const mobile = isMobileViewport(page);

  for (const locale of ['ar', 'en'] as const) {
    const response = await page.goto('/dashboard?locale=' + locale);
    expect(response?.status()).toBe(200);
    await expect(page.locator('html')).toHaveAttribute('lang', locale);
    await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');

    if (mobile) {
      await openMobileNavigation(page, locale);
    }

    const navigation = page.locator('#primary-navigation');
    for (const path of ['/access/users', '/access/roles', '/access/audit']) {
      const route = new URL(path + '?locale=' + locale, baseURL).toString();
      const routeResponse = await page.request.get(route);
      const link = navigation.locator('a[href="' + path + '?locale=' + locale + '"]');

      if (routeResponse.status() === 200) {
        await expect(link).toHaveCount(1);
      } else {
        expect(routeResponse.status()).toBe(403);
        await expect(link).toHaveCount(0);
      }
    }
  }
});
