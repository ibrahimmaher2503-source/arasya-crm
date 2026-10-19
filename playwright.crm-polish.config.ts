import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: '**/crm-polish.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90_000,
  globalTimeout: 600_000,
  expect: { timeout: 8_000 },
  reporter: [['list'], ['html', { outputFolder: '.local/windows-qa/crm-polish-report', open: 'never' }]],
  use: {
    browserName: 'chromium',
    baseURL: process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8773',
    trace: 'off',
    screenshot: 'off',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
  },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 1000 } } },
    { name: 'tablet', use: { viewport: { width: 768, height: 1024 }, isMobile: true, hasTouch: true } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
