/**
 * Augments vitest's `expect` with @testing-library/jest-dom matchers.
 * This file is automatically included in the compilation (via tsconfig "include": ["src"])
 * and makes the augmentation available project-wide.
 */
import type { TestingLibraryMatchers } from '@testing-library/jest-dom/matchers'

declare module 'vitest' {
  interface Assertion<T = unknown>
    extends TestingLibraryMatchers<unknown, T> {}
  interface AsymmetricMatchersContaining
    extends TestingLibraryMatchers<unknown, unknown> {}
}
