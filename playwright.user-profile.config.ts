import { randomUUID } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { defineConfig } from '@playwright/test';

const projectRoot = resolve(__dirname);
const runId = randomUUID();
const runDirectory = resolve(projectRoot, 'storage/framework/testing/user-profile', runId);
const syntheticOrigin = 'http://user-profile.test';
const cachePath = (fileName: string): string => `storage/framework/testing/user-profile/${runId}/${fileName}`;
const compiledViewsPath = join(runDirectory, 'compiled-views');

mkdirSync(runDirectory, { recursive: true, mode: 0o700 });
writeFileSync(join(runDirectory, '.owner'), `A1-CORE-016 user-profile ${runId}`, { flag: 'wx' });

const environment = {
  APP_ENV: 'testing',
  APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
  APP_DEBUG: 'false',
  APP_URL: syntheticOrigin,
  APP_CONFIG_CACHE: cachePath('config-cache.php'),
  APP_ROUTES_CACHE: cachePath('routes-cache.php'),
  APP_EVENTS_CACHE: cachePath('events-cache.php'),
  APP_SERVICES_CACHE: cachePath('services-cache.php'),
  APP_PACKAGES_CACHE: cachePath('packages-cache.php'),
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: ':memory:',
  DB_URL: '',
  DB_HOST: '',
  DB_PORT: '',
  DB_USERNAME: '',
  DB_PASSWORD: '',
  SESSION_DRIVER: 'array',
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  LOG_CHANNEL: 'stderr',
  VIEW_COMPILED_PATH: compiledViewsPath,
  ARASYA_USER_PROFILE_RUN_ID: runId,
  ARASYA_USER_PROFILE_RUN_DIRECTORY: runDirectory,
};

for (const [name, value] of Object.entries(environment)) {
  process.env[name] = value;
}

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: '**/user-profile.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  globalTimeout: 120_000,
  reporter: 'list',
  outputDir: resolve(projectRoot, 'test-results/user-profile', runId),
  use: {
    browserName: 'chromium',
    channel: 'chromium',
    baseURL: syntheticOrigin,
    trace: 'off',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop-1536x1024', use: { viewport: { width: 1536, height: 1024 } } },
    { name: 'tablet-768x1024', use: { viewport: { width: 768, height: 1024 }, hasTouch: true } },
    { name: 'mobile-390x844', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
