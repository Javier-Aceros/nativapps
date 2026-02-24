import '@testing-library/jest-dom/vitest'
import { beforeAll, afterEach, afterAll } from 'vitest'
import { cleanup } from '@testing-library/react'
import { server } from './server'

// Start the MSW server before all tests, reset handlers after each,
// and stop it after all tests to avoid test pollution.
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  cleanup()             // unmount React trees between tests
  server.resetHandlers() // remove per-test MSW overrides
})
afterAll(() => server.close())
