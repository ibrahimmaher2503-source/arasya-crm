import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/Browser',
  testMatch: '**/login-design.spec.ts',
  outputDir: '.local/windows-qa/login-test-results',
  workers: 1,
  retries: 0,
  timeout: 30000,
  reporter: 'list',
  use: { baseURL: 'http://127.0.0.1:8773', browserName: 'chromium', trace: 'off' },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1672, height: 941 } } },
    { name: 'tablet', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 } } },
  ],
});
