import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';

const baseURL = process.env.ARASYA_CLOSURE_ORIGIN || '';
const evidencePath = process.env.ARASYA_CLOSURE_EVIDENCE;
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(baseURL)) throw new Error('Owned loopback required');
if (!evidencePath) throw new Error('Owned role-permission evidence directory required');

export default defineConfig({
  testDir: resolve(__dirname, 'tests', 'Browser'),
  testMatch: 'role-permissions.spec.ts',
  workers: 1,
  retries: 0,
  timeout: 60000,
  globalTimeout: 600000,
  reporter: 'list',
  outputDir: resolve(evidencePath, 'playwright'),
  use: { baseURL, browserName: 'chromium', trace: 'off', screenshot: 'only-on-failure' },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 1000 } } },
    { name: 'tablet', use: { viewport: { width: 820, height: 1180 }, hasTouch: true } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
