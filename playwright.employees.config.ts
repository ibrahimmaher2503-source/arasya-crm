import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'employee-acceptance.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 180_000,
  globalTimeout: 720_000,
  outputDir: 'test-results/employees',
  reporter: [['list'], ['html', { outputFolder: 'playwright-report/employees', open: 'never' }]],
  use: {
    browserName: 'chromium',
    channel: 'chromium',
    baseURL: process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8784',
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'employee-desktop', use: { viewport: { width: 1440, height: 1000 } } },
    { name: 'employee-tablet', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'employee-mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
