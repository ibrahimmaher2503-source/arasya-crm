import { randomUUID } from 'node:crypto';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';

const authState = '.local/windows-qa/tasks-auth.json';
const deniedState = '.local/windows-qa/tasks-denied.json';
const otherState = '.local/windows-qa/tasks-other.json';
const baseURL = process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765';

type FormSnapshot = {
  action: string;
  fields: Record<string, string>;
  csrf: string;
};

async function formFor(submitter: Locator): Promise<FormSnapshot> {
  return submitter.locator('xpath=ancestor::form').evaluate((form) => {
    const element = form as HTMLFormElement;
    const action = element.getAttribute('action');
    const fields = Object.fromEntries(
      Array.from(new FormData(element).entries(), ([name, value]) => [name, String(value)]),
    );
    if (!action) throw new Error('The rendered task form has no action URL.');
    if (!fields._token) throw new Error('The rendered task form has no CSRF token.');
    return { action: new URL(action, document.baseURI).toString(), fields, csrf: fields._token };
  });
}

function postForm(page: Page, form: FormSnapshot) {
  // page.request shares this page's browser context cookies; the rendered token supplies CSRF.
  return page.request.post(form.action, {
    form: form.fields,
    headers: { 'X-CSRF-TOKEN': form.csrf },
    maxRedirects: 0,
  });
}

async function expectArabicAndNoOverflow(page: Page): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
}

async function expectTaskStatus(page: Page, status: 'pending' | 'in_progress' | 'completed'): Promise<void> {
  const marker = page.getByTestId('task-status');
  await expect(marker).toBeVisible();
  const value = await marker.getAttribute('data-status');
  if (value === null) {
    const labels = { pending: 'قيد الانتظار', in_progress: 'قيد التنفيذ', completed: 'مكتملة' };
    await expect(marker).toContainText(labels[status]);
  } else {
    expect(value).toBe(status);
  }
}

async function createTask(page: Page, testInfo: TestInfo): Promise<{ id: string; title: string }> {
  const title = `synthetic-tasks-browser-${randomUUID()}`;
  const listing = await page.goto('/tasks');
  expect(listing?.status()).toBe(200);
  await expectArabicAndNoOverflow(page);
  await page.getByRole('link', { name: 'مهمة جديدة', exact: true }).click();
  await expect(page).toHaveURL(/\/tasks\/create$/);

  await page.getByLabel('عنوان المهمة', { exact: true }).fill(title);
  await expect(page.getByLabel('الأولوية', { exact: true })).toHaveValue('normal');
  await page.getByLabel('تاريخ الاستحقاق', { exact: true }).fill(new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 16));

  const post = page.waitForResponse((response) => response.request().method() === 'POST'
    && new URL(response.url()).pathname.startsWith('/tasks'));
  await page.getByRole('button', { name: 'إنشاء المهمة', exact: true }).click();
  expect([302, 303]).toContain((await post).status());
  await expect(page).toHaveURL(/\/tasks\/(?!create$)[^/]+$/);

  const id = decodeURIComponent(new URL(page.url()).pathname.split('/').at(-1)!);
  await testInfo.attach('synthetic-task-id', { body: id, contentType: 'text/plain' });
  await expect(page.getByRole('heading', { name: title, level: 1 })).toBeVisible();
  await expectTaskStatus(page, 'pending');
  await expectArabicAndNoOverflow(page);
  return { id, title };
}

test.describe('synthetic Tasks cycle', () => {
  test.use({ storageState: authState });

  test('creates, starts, attaches evidence and completes with one replayed start request', async ({ page, browser }, testInfo) => {
    const { id, title } = await createTask(page, testInfo);

    const startButton = page.getByRole('button', { name: 'بدء العمل', exact: true });
    const start = await formFor(startButton);
    const firstStart = page.waitForResponse((response) => response.request().method() === 'POST' && response.url() === start.action);
    await startButton.click();
    const first = await firstStart;
    expect([302, 303]).toContain(first.status());
    const replay = await postForm(page, start);
    expect([302, 303]).toContain(replay.status());
    expect(replay.headers().location).toBe(first.headers().location);
    await page.reload();
    await expectTaskStatus(page, 'in_progress');
    await expectArabicAndNoOverflow(page);

    const fileName = `evidence-${randomUUID()}.txt`;
    await page.getByLabel('ملف الدليل', { exact: true }).setInputFiles({
      name: fileName,
      mimeType: 'text/plain',
      buffer: Buffer.from(`Synthetic evidence for ${title}.`),
    });
    const evidenceForm = await formFor(page.getByRole('button', { name: 'إرفاق الدليل', exact: true }));
    const evidencePost = page.waitForResponse((response) => response.request().method() === 'POST' && response.url() === evidenceForm.action);
    await page.getByRole('button', { name: 'إرفاق الدليل', exact: true }).click();
    expect([302, 303]).toContain((await evidencePost).status());
    await expect(page.getByText(fileName, { exact: true })).toBeVisible();

    const completePost = page.waitForResponse((response) => response.request().method() === 'POST'
      && new URL(response.url()).pathname.startsWith(`/tasks/${id}`));
    await page.getByRole('button', { name: 'إكمال المهمة', exact: true }).click();
    expect([302, 303]).toContain((await completePost).status());
    await page.reload();
    await expectTaskStatus(page, 'completed');
    await expect(page.getByRole('region', { name: 'سجل التدقيق' })).toBeVisible();
    await expect(page.getByText(fileName, { exact: true })).toBeVisible();
    await expectArabicAndNoOverflow(page);
    await testInfo.attach('completed-task-screen', {
      body: await page.screenshot({ fullPage: true }),
      contentType: 'image/png',
    });
    const evidenceUrl = await page.getByRole('link', { name: fileName, exact: true }).getAttribute('href');
    expect(evidenceUrl).toBeTruthy();
    const download = await page.request.get(evidenceUrl!);
    expect(download.status()).toBe(200);
    expect((await download.body()).toString()).toContain(title);

    const otherContext = await browser.newContext({ storageState: otherState, baseURL });
    try {
      const otherPage = await otherContext.newPage();
      const list = await otherPage.goto('/tasks');
      expect(list?.status()).toBe(200);
      await expect(otherPage.getByText(title, { exact: true })).toHaveCount(0);
      const detail = await otherPage.goto(`/tasks/${encodeURIComponent(id)}`);
      expect([403, 404]).toContain(detail?.status());
      expect([403, 404]).toContain((await otherPage.request.get(evidenceUrl!)).status());
    } finally {
      await otherContext.close();
    }
  });

  test('denies a write from a read-only session', async ({ browser }, testInfo) => {
    const context = await browser.newContext({ storageState: deniedState, baseURL });
    try {
      const page = await context.newPage();
      const response = await page.goto('/tasks');
      expect(response?.status()).toBe(200);
      expect((await page.goto('/tasks/create'))?.status()).toBe(403);
      await page.goto('/tasks');
      const title = `synthetic-tasks-denied-${randomUUID()}`;
      const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
      expect(csrf).toBeTruthy();
      await testInfo.attach('denied-task-title', { body: title, contentType: 'text/plain' });
      const denied = await page.request.post('/tasks', {
        form: { title, priority: 'normal', due_at: new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 16) },
        headers: { 'X-CSRF-TOKEN': csrf! },
        maxRedirects: 0,
      });
      expect(denied.status()).toBe(403);
      await page.goto('/tasks');
      await expect(page.getByText(title, { exact: true })).toHaveCount(0);
    } finally {
      await context.close();
    }
  });

  test('keeps assistant confirmation blocked when the shared gateway is unavailable', async ({ page }, testInfo) => {
    const { id } = await createTask(page, testInfo);
    await testInfo.attach('assistant-unchanged-task-id', { body: id, contentType: 'text/plain' });
    const preview = page.waitForResponse((response) => response.request().method() === 'POST'
      && new URL(response.url()).pathname.includes('/assistant'));
    await page.getByRole('button', { name: 'معاينة إجراء المساعد', exact: true }).click();
    expect([302, 303]).toContain((await preview).status());
    await expect(page.getByText(/تنفيذ المساعد غير متاح/)).toBeVisible();
    await expect(page.getByLabel('أؤكد تنفيذ الإجراء', { exact: true })).toBeVisible();
    const confirm = page.getByRole('button', { name: 'تأكيد التنفيذ', exact: true });
    await expect(confirm).toBeDisabled();

    const form = await formFor(confirm);
    const confirmationName = await page.getByLabel('أؤكد تنفيذ الإجراء', { exact: true }).getAttribute('name');
    const confirmationValue = await page.getByLabel('أؤكد تنفيذ الإجراء', { exact: true }).getAttribute('value');
    if (confirmationName && confirmationValue) form.fields[confirmationName] = confirmationValue;
    expect((await postForm(page, form)).status()).toBe(503);
    await page.reload();
    await expectTaskStatus(page, 'pending');
    await expect(page.getByText('إيصال التنفيذ', { exact: true })).toHaveCount(0);
  });

  test('Arabic task list and creation form fit the configured desktop and mobile viewports', async ({ page }) => {
    const listing = await page.goto('/tasks');
    expect(listing?.status()).toBe(200);
    await expectArabicAndNoOverflow(page);
    await page.goto('/tasks/create');
    const title = page.getByLabel('عنوان المهمة', { exact: true });
    await expect(title).toBeVisible();
    await title.focus();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('الأولوية', { exact: true })).toBeFocused();
    await expectArabicAndNoOverflow(page);
  });
});
