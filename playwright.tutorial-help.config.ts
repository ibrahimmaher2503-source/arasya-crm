import { randomUUID } from 'node:crypto';
import { resolve } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const outputDir = resolve('.local/windows-qa', `tutorial-help-results-${randomUUID()}`);
const syntheticOrigin = 'http://tutorial-help.test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: 'tutorial-help.spec.ts',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 45_000,
  globalTimeout: 300_000,
  outputDir,
  reporter: 'list',
  use: {
    channel: 'chromium',
    baseURL: syntheticOrigin,
    actionTimeout: 12_000,
    navigationTimeout: 20_000,
    trace: 'retain-on-failure',
    screenshot: 'off',
  },
  projects: [
    {
      name: 'tutorial-help-desktop',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } },
    },
    {
      name: 'tutorial-help-tablet',
      use: { ...devices['Desktop Chrome'], viewport: { width: 768, height: 1024 }, isMobile: true, hasTouch: true },
    },
    {
      name: 'tutorial-help-mobile',
      use: { ...devices['Pixel 7'], viewport: { width: 390, height: 844 } },
    },
  ],
});
