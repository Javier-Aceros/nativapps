import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  // Run tests in parallel across files; each file runs serially by default
  fullyParallel: true,
  // Fail the CI build on accidental test.only
  forbidOnly: !!process.env.CI,
  // One retry on CI to handle flaky network conditions
  retries: process.env.CI ? 1 : 0,
  // Single worker: our tests share one Vite server instance
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  use: {
    baseURL: 'http://localhost:5173',
    // Capture a trace only on first retry (helps debug CI failures)
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  // Start the Vite dev server automatically before the test suite runs.
  // In a local environment, it reuses an already-running server if available.
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
})
