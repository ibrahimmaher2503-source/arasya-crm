import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { expect, test, type Page } from '@playwright/test';

const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';
const fixturePath = resolve('storage/framework/testing/access-fixture.json');
const parsedBaseURL = new URL(baseURL);
if (parsedBaseURL.protocol !== 'http:'
  || !['127.0.0.1', 'localhost'].includes(parsedBaseURL.hostname)
  || parsedBaseURL.username !== ''
  || parsedBaseURL.password !== '') {
  throw new Error('Dashboard browser checks may use only unauthenticated loopback HTTP.');
}

type AccessFixture = {
  admin: { email: string; name: string; password: string };
};

async function expectArabicAndNoOverflow(page: Page): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function login(page: Page): Promise<string> {
  const fixture = JSON.parse(await readFile(fixturePath, 'utf8')) as AccessFixture;
  expect(fixture.admin.email).toBeTruthy();
  expect(fixture.admin.password).toBeTruthy();

  const response = await page.goto('/login');
  expect(response?.status()).toBe(200);
  await page.getByLabel('البريد الإلكتروني', { exact: true }).fill(fixture.admin.email);
  await page.getByLabel('كلمة المرور', { exact: true }).fill(fixture.admin.password);
  const post = page.waitForResponse(candidate => candidate.request().method() === 'POST'
    && new URL(candidate.url()).pathname === '/login');
  await page.getByRole('button', { name: 'تسجيل الدخول', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(new URL('/dashboard', baseURL).toString());
  return fixture.admin.name;
}

test('guests visiting the root or dashboard are sent to login', async ({ page }) => {
  for (const path of ['/', '/dashboard']) {
    const redirect = page.waitForResponse(response => response.request().method() === 'GET'
      && new URL(response.url()).pathname === path);
    const landing = await page.goto(path);
    const denied = await redirect;

    expect([302, 303]).toContain(denied.status());
    expect(new URL(denied.headers().location ?? '/', baseURL).pathname).toBe('/login');
    expect(landing?.status()).toBe(200);
    await expect(page).toHaveURL(new URL('/login', baseURL).toString());
    await expect(page.getByLabel('البريد الإلكتروني', { exact: true })).toBeVisible();
    await expectArabicAndNoOverflow(page);
  }
});

test('administrator can use the Arabic dashboard and its navigation on desktop and mobile', async ({ page }, testInfo) => {
  if (testInfo.project.name === 'desktop-chromium') {
    await page.setViewportSize({ width: 1448, height: 1086 });
  }
  const adminName = await login(page);

  const heroImage = await page.request.get(new URL('/images/dashboard-hero.jpg', baseURL).toString());
  expect(heroImage.status()).toBe(200);
  expect(heroImage.headers()['content-type']).toContain('image/jpeg');
  expect((await heroImage.body()).length).toBeGreaterThan(1000);

  await expectArabicAndNoOverflow(page);
  await expect(page.getByRole('heading', { level: 1, name: `مرحباً ${adminName}`, exact: true })).toBeVisible();
  await expect(page.getByText('بيانات تجريبية', { exact: true }).first()).toBeVisible();

  const metrics = [
    ['إجمالي المبيعات', '1,248,500'],
    ['إجمالي المشتريات', '892,300'],
    ['المخزون الحالي', '3,420'],
    ['المستحقات', '684,200'],
  ];
  await expect(page.locator('article.kpi-card')).toHaveCount(metrics.length);
  for (const [label, value] of metrics) {
    const card = page.locator('article.kpi-card').filter({ has: page.getByRole('heading', { name: label, exact: true }) });
    await expect(card).toHaveCount(1);
    await expect(card.getByText(value, { exact: true })).toBeVisible();
  }

  for (const title of ['المبيعات والمشتريات', 'توزيع المبيعات حسب الفئة']) {
    const heading = page.getByRole('heading', { name: title, exact: true });
    await expect(heading).toBeVisible();
    const chart = heading.locator('xpath=ancestor::article[1]');
    await expect(chart.getByRole('img')).toHaveCount(1);
  }

  for (const title of ['أحدث الطلبات', 'أفضل العملاء']) {
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
  }
  await expect(page.getByRole('table')).toHaveCount(2);

  const navigation = page.locator('#primary-navigation');
  const toggle = page.getByRole('button', { name: 'فتح القائمة', exact: true });
  if (testInfo.project.name === 'mobile-chromium') {
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-controls', 'primary-navigation');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('.dashboard-shell')).toHaveClass(/is-nav-open/);
    const close = page.getByRole('button', { name: 'إغلاق القائمة', exact: true });
    await expect(close).toBeVisible();
    await close.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.dashboard-shell')).not.toHaveClass(/is-nav-open/);
    await expectArabicAndNoOverflow(page);
  } else {
    await expect(toggle).toBeHidden();
  }

  await expect(navigation).toBeVisible();
  for (const path of ['/dashboard', '/access']) {
    if (testInfo.project.name === 'mobile-chromium') {
      const openMenu = page.getByRole('button', { name: 'فتح القائمة', exact: true });
      await openMenu.click();
      await expect(page.locator('.dashboard-shell')).toHaveClass(/is-nav-open/);
    }
    const link = navigation.locator(`a[href="${path}"]`);
    await expect(link).toBeVisible();
    await expect(link).toHaveAccessibleName(/\S+/);
    const response = page.waitForResponse(candidate => candidate.request().method() === 'GET'
      && new URL(candidate.url()).pathname === path);
    await link.click();
    expect((await response).status(), `${path} must open from the sidebar`).toBe(200);
    await expect(page).toHaveURL(new URL(path, baseURL).toString());
    await expectArabicAndNoOverflow(page);
  }

  await expect(page.getByRole('heading', { level: 1, name: 'إدارة الوصول', exact: true })).toBeVisible();
  const root = page.waitForResponse(candidate => candidate.request().method() === 'GET'
    && new URL(candidate.url()).pathname === '/');
  await page.goto('/');
  const rootResponse = await root;
  expect([302, 303]).toContain(rootResponse.status());
  expect(new URL(rootResponse.headers().location ?? '/', baseURL).pathname).toBe('/dashboard');
  await expect(page).toHaveURL(new URL('/dashboard', baseURL).toString());
  await expect(page.getByRole('heading', { level: 1, name: `مرحباً ${adminName}`, exact: true })).toBeVisible();
  await expectArabicAndNoOverflow(page);

  await testInfo.attach(`dashboard-${testInfo.project.name}`, {
    body: await page.screenshot({ fullPage: true }),
    contentType: 'image/png',
  });
});

test('dashboard controls support theme, search, chart updates, keyboard shortcut, and English direction', async ({ page }) => {
  await login(page);
  await expectArabicAndNoOverflow(page);

  const theme = page.getByRole('button', { name: 'تبديل المظهر', exact: true });
  await theme.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(theme).toHaveAttribute('aria-pressed', 'true');
  await theme.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  await expect(theme).toHaveAttribute('aria-pressed', 'false');

  const navigation = page.locator('#primary-navigation');
  const search = page.locator('.top-search input[data-nav-search]');
  await search.fill('الموظفون');
  await expect(page.locator('.sidebar-search input[data-nav-search]')).toHaveValue('الموظفون');
  await expect(navigation.locator('[data-nav-item]').filter({ hasText: 'الموظفون' })).toBeVisible();
  await expect(navigation.locator('[data-nav-item]').filter({ hasText: 'المشاريع' })).toBeHidden();
  await search.fill('');
  await expect(navigation.locator('[data-nav-item]').filter({ hasText: 'المشاريع' })).toBeVisible();

  const donutTotal = page.locator('[data-donut-total]');
  const firstShare = page.locator('[data-donut-share]').first();
  const firstSegment = page.locator('.donut-segment').first();
  const previousTotal = await donutTotal.getAttribute('data-previous');
  const previousShare = await firstShare.getAttribute('data-previous');
  const currentDasharray = await firstSegment.getAttribute('stroke-dasharray');
  expect(previousTotal).toBeTruthy();
  expect(previousShare).toBeTruthy();
  expect(currentDasharray).toBeTruthy();
  await page.locator('[data-donut-period]').selectOption('previous');
  await expect(donutTotal).toHaveText(previousTotal!);
  await expect(firstShare).toHaveText(previousShare!);
  await expect.poll(() => firstSegment.getAttribute('stroke-dasharray')).not.toBe(currentDasharray);
  await page.locator('[data-donut-period]').selectOption('current');
  await expect(donutTotal).toHaveText(await donutTotal.getAttribute('data-current') ?? '');
  await expect(firstShare).toHaveText(await firstShare.getAttribute('data-current') ?? '');
  await expect(firstSegment).toHaveAttribute('stroke-dasharray', currentDasharray!);

  const sales = page.locator('[data-series-choice="sales"]');
  const purchases = page.locator('[data-series-choice="purchases"]');
  await expect(sales).toHaveAttribute('aria-pressed', 'true');
  await purchases.click();
  await expect(purchases).toHaveAttribute('aria-pressed', 'true');
  await expect(sales).toHaveAttribute('aria-pressed', 'false');

  await page.keyboard.press('Control+k');
  await expect(search).toBeFocused();

  const adminName = JSON.parse(await readFile(fixturePath, 'utf8')) as AccessFixture;
  const english = await page.goto('/dashboard?locale=en');
  expect(english?.status()).toBe(200);
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  await expect(page.getByRole('heading', { level: 1, name: `Welcome ${adminName.admin.name}`, exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Sales and purchases', exact: true })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
