// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright configuration for Friday Night Skate v2 E2E tests.
 *
 * Tests run against the live DDEV site. Start DDEV before running:
 *   ddev start
 *
 * Run tests from inside DDEV:
 *   ddev exec "cd tests/e2e && npx playwright test"
 *
 * Or from the host (if Playwright browsers are installed locally):
 *   cd tests/e2e && npx playwright test
 */
module.exports = defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  timeout: 30_000,
  expect: {
    timeout: 10_000,
  },
  fullyParallel: false,
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'https://fridaynightskate2.ddev.site',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
