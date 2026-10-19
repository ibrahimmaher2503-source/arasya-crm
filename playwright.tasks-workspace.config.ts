import { randomUUID } from 'node:crypto';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from '@playwright/test';

const projectRoot = resolve(__dirname);
const runId = process.env.ARASYA_TASKS_WORKSPACE_RUN_ID || randomUUID();
const runDirectory = resolve(projectRoot, 'storage/framework/testing/tasks-workspace', runId);
const baseURL = 'http://127.0.0.1:8776';
const appKey = 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=';
const ownedPath = (environmentName: string, fileName: string): string => {
  const derived = resolve(runDirectory, fileName);
  const inherited = process.env[environmentName];
  if (inherited && resolve(inherited).toLowerCase() !== derived.toLowerCase()) {
    throw new Error(`${environmentName} does not belong to the inherited task-workspace run ID.`);
  }
  return derived;
};
const fixturePath = ownedPath('ARASYA_TASKS_WORKSPACE_FIXTURE', 'fixture.json');
const authorizedState = ownedPath('ARASYA_TASKS_WORKSPACE_STATE', 'authorized-state.json');
const readOnlyState = ownedPath('ARASYA_TASKS_WORKSPACE_READONLY_STATE', 'read-only-state.json');
const cachePath = (name: string): string => `storage/framework/testing/tasks-workspace/${runId}/${name}`;

mkdirSync(runDirectory, { recursive: true, mode: 0o700 });
process.env.ARASYA_TASKS_WORKSPACE_RUN_ID = runId;
process.env.ARASYA_TASKS_WORKSPACE_FIXTURE = fixturePath;
process.env.ARASYA_TASKS_WORKSPACE_STATE = authorizedState;
process.env.ARASYA_TASKS_WORKSPACE_READONLY_STATE = readOnlyState;
process.env.ARASYA_BROWSER_BASE_URL = baseURL;
console.log(`Tasks workspace isolated run directory: ${runDirectory}`);

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: '**/tasks-workspace.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  globalTimeout: 360_000,
  expect: { timeout: 8_000 },
  outputDir: resolve(runDirectory, 'playwright-results'),
  reporter: [['list'], ['html', { outputFolder: resolve(runDirectory, 'playwright-report'), open: 'never' }]],
  use: {
    browserName: 'chromium',
    baseURL,
    trace: 'off',
    screenshot: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 1000 } } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
  webServer: {
    command: 'cmd.exe /d /s /c "php tests/Browser/tasks-workspace-fixtures.php seed && php -S 127.0.0.1:8776 -t public tests/Browser/tasks-workspace-fixtures.php"',
    cwd: projectRoot,
    url: `${baseURL}/__tasks-workspace-health`,
    reuseExistingServer: false,
    timeout: 120_000,
    env: {
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
      DB_DATABASE: resolve(runDirectory, 'tasks.sqlite'),
      DB_FOREIGN_KEYS: 'true',
      SESSION_DRIVER: 'file',
      SESSION_COOKIE: `tasks_workspace_${runId.replaceAll('-', '').slice(0, 16)}`,
      SESSION_SECURE_COOKIE: 'false',
      SESSION_SAME_SITE: 'lax',
      CACHE_STORE: 'array',
      QUEUE_CONNECTION: 'sync',
      MAIL_MAILER: 'array',
      LOG_CHANNEL: 'stderr',
      ARASYA_BROWSER_BASE_URL: baseURL,
      ARASYA_TASKS_WORKSPACE_RUN_ID: runId,
      ARASYA_TASKS_WORKSPACE_FIXTURE: fixturePath,
      ARASYA_TASKS_WORKSPACE_STATE: authorizedState,
      ARASYA_TASKS_WORKSPACE_READONLY_STATE: readOnlyState,
    },
  },
});
