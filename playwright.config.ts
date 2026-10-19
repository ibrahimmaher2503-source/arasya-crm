import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  globalTimeout: 120_000,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    channel: 'chromium',
    baseURL: process.env.ARASYA_BROWSER_BASE_URL || 'http://127.0.0.1:8765',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
  ],
});
