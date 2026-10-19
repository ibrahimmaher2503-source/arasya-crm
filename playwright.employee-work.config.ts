import { randomUUID } from 'node:crypto';
import { resolve } from 'node:path';
import { defineConfig } from '@playwright/test';

const projectRoot = resolve(__dirname);
const runId = process.env.ARASYA_EMPLOYEE_WORK_RUN_ID || randomUUID();
const runDirectory = resolve(projectRoot, 'storage/framework/testing/employee-work', runId);
const baseURL = 'http://127.0.0.1:8787';
const appKey = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
const cookieName = `employee_work_${runId.replaceAll('-', '').slice(0, 16)}`;
const fixturePath = resolve(runDirectory, 'employee-work-fixture.json');
const authPath = resolve(runDirectory, 'employee-work-auth.json');
const cachePath = (fileName: string): string => `storage/framework/testing/employee-work/${runId}/${fileName}`;
const environment = {
  APP_ENV: 'testing',
  APP_KEY: appKey,
  APP_DEBUG: 'false',
  APP_URL: baseURL,
  APP_CONFIG_CACHE: cachePath('config.php'),
  APP_ROUTES_CACHE: cachePath('routes.php'),
  APP_EVENTS_CACHE: cachePath('events.php'),
  APP_SERVICES_CACHE: cachePath('services.php'),
  APP_PACKAGES_CACHE: cachePath('packages.php'),
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: resolve(runDirectory, 'employee-work.sqlite'),
  DB_FOREIGN_KEYS: 'true',
  SESSION_DRIVER: 'file',
  SESSION_COOKIE: cookieName,
  SESSION_FILES: resolve(runDirectory, 'sessions'),
  SESSION_SECURE_COOKIE: 'false',
  SESSION_SAME_SITE: 'lax',
  VIEW_COMPILED_PATH: resolve(runDirectory, 'views'),
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  LOG_CHANNEL: 'stderr',
  BCRYPT_ROUNDS: '4',
  ARASYA_BROWSER_BASE_URL: baseURL,
  ARASYA_EMPLOYEE_WORK_RUN_ID: runId,
  ARASYA_EMPLOYEE_WORK_RUN_DIRECTORY: runDirectory,
  ARASYA_EMPLOYEE_WORK_FIXTURE: fixturePath,
  ARASYA_EMPLOYEE_WORK_AUTH: authPath,
};

for (const [name, value] of Object.entries(environment)) {
  process.env[name] = value;
}
for (const name of ['DB_USERNAME', 'DB_PASSWORD', 'DB_URL']) {
  delete process.env[name];
}

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'employee-work.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  globalTimeout: 180_000,
  outputDir: '.local/windows-qa/employee-work-results',
  reporter: [['list'], ['html', { outputFolder: '.local/windows-qa/employee-work-report', open: 'never' }]],
  use: {
    browserName: 'chromium',
    channel: 'chromium',
    baseURL,
    viewport: { width: 1440, height: 1000 },
    actionTimeout: 12_000,
    navigationTimeout: 20_000,
    trace: 'off',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: 'php -S 127.0.0.1:8787 -t public tests/Browser/employee-work-fixtures.php',
    cwd: projectRoot,
    url: `${baseURL}/__employee-work-health`,
    reuseExistingServer: false,
    timeout: 30_000,
    stdout: 'ignore',
    stderr: 'ignore',
    env: environment,
  },
});
