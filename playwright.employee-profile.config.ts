import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'employee-profile.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  globalTimeout: 240_000,
  outputDir: '.local/windows-qa/employee-profile-results',
  reporter: [['list']],
  use: {
    browserName: 'chromium',
    channel: 'chromium',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    trace: 'off',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'employee-profile-desktop', use: { viewport: { width: 1440, height: 1000 } } },
    { name: 'employee-profile-mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
  ],
});
