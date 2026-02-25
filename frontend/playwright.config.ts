import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  // Allow up to 90 s per test — the full-flow test calls a real AI API
  timeout: 90_000,
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
    // Screenshot + trace on failure for self-diagnosing CI failures
    screenshot: 'only-on-failure',
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
