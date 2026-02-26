import { describe, it, expect } from 'vitest'
import { renderHook, act, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http, HttpResponse } from 'msw'
import type { ReactNode } from 'react'
import { server } from '../../../test/server'
import { useRetry } from './useRetry'

// ─── Wrapper ─────────────────────────────────────────────────────────────────

/**
 * Fresh QueryClient + provider per test to avoid state leakage.
 * Retries disabled so query failures are immediate.
 */
function createWrapper() {
  const qc = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  )
}

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const mockMessage = {
  id: 1,
  title: 'T',
  original_content: 'C',
  summary: 'S',
  status: 'completed' as const,
  created_at: '2026-02-24T00:00:00.000Z',
  delivery_logs: [],
}

const mockLog = {
  id: 1,
  message_id: 1,
  channel: 'email' as const,
  attempt: 1,
  status: 'success' as const,
  created_at: '2026-02-24T00:00:00.000Z',
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('useRetry', () => {
  // ── isRetrying ──────────────────────────────────────────────────────────────

  describe('isRetrying', () => {
    it('returns false for any key before a retry has started', () => {
      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      expect(result.current.isRetrying('ai-99')).toBe(false)
      expect(result.current.isRetrying('channel-99-slack')).toBe(false)
    })
  })

  // ── retryMessage ────────────────────────────────────────────────────────────

  describe('retryMessage', () => {
    it('marks ai-{id} as retrying while the request is in-flight', async () => {
      // Never-resolving handler: keeps the request in-flight for the test duration
      server.use(http.post('/api/messages/1/retry', () => new Promise(() => {})))

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      act(() => { void result.current.retryMessage(1) })

      await waitFor(() => expect(result.current.isRetrying('ai-1')).toBe(true))
    })

    it('ignores a second call for the same message while the first is in-flight', async () => {
      let callCount = 0
      server.use(
        http.post('/api/messages/1/retry', async () => {
          callCount++
          await new Promise(() => {}) // never resolves — holds the guard in place
          return HttpResponse.json(mockMessage)
        }),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      // First call — puts the key in the guard set
      act(() => { void result.current.retryMessage(1) })
      await waitFor(() => expect(result.current.isRetrying('ai-1')).toBe(true))

      // Second call — must be a no-op due to the ref guard
      act(() => { void result.current.retryMessage(1) })

      // Allow any async microtasks to flush
      await Promise.resolve()

      expect(callCount).toBe(1)
    })

    it('clears the retrying key after the request and delay complete', async () => {
      server.use(
        http.post('/api/messages/1/retry', () => HttpResponse.json(mockMessage)),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      await act(async () => {
        await result.current.retryMessage(1)
      })

      expect(result.current.isRetrying('ai-1')).toBe(false)
    }, 3000)

    it('uses independent keys for different message IDs', async () => {
      server.use(
        http.post('/api/messages/1/retry', () => new Promise(() => {})),
        http.post('/api/messages/2/retry', () => new Promise(() => {})),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      act(() => { void result.current.retryMessage(1) })
      act(() => { void result.current.retryMessage(2) })

      await waitFor(() => {
        expect(result.current.isRetrying('ai-1')).toBe(true)
        expect(result.current.isRetrying('ai-2')).toBe(true)
      })
    })
  })

  // ── retryChannel ────────────────────────────────────────────────────────────

  describe('retryChannel', () => {
    it('marks channel-{id}-{channel} as retrying while the request is in-flight', async () => {
      server.use(
        http.post('/api/messages/2/channels/slack/retry', () => new Promise(() => {})),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      act(() => { void result.current.retryChannel(2, 'slack') })

      await waitFor(() =>
        expect(result.current.isRetrying('channel-2-slack')).toBe(true),
      )
    })

    it('ignores a second call for the same channel while the first is in-flight', async () => {
      let callCount = 0
      server.use(
        http.post('/api/messages/2/channels/email/retry', async () => {
          callCount++
          await new Promise(() => {})
          return HttpResponse.json(mockLog)
        }),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      act(() => { void result.current.retryChannel(2, 'email') })
      await waitFor(() =>
        expect(result.current.isRetrying('channel-2-email')).toBe(true),
      )

      act(() => { void result.current.retryChannel(2, 'email') })
      await Promise.resolve()

      expect(callCount).toBe(1)
    })

    it('clears the retrying key after the request and delay complete', async () => {
      server.use(
        http.post('/api/messages/1/channels/email/retry', () => HttpResponse.json(mockLog)),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      await act(async () => {
        await result.current.retryChannel(1, 'email')
      })

      expect(result.current.isRetrying('channel-1-email')).toBe(false)
    }, 3000)

    it('allows concurrent retries on different channels of the same message', async () => {
      server.use(
        http.post('/api/messages/3/channels/email/retry', () => new Promise(() => {})),
        http.post('/api/messages/3/channels/sms/retry', () => new Promise(() => {})),
      )

      const { result } = renderHook(() => useRetry(), { wrapper: createWrapper() })

      act(() => { void result.current.retryChannel(3, 'email') })
      act(() => { void result.current.retryChannel(3, 'sms') })

      await waitFor(() => {
        expect(result.current.isRetrying('channel-3-email')).toBe(true)
        expect(result.current.isRetrying('channel-3-sms')).toBe(true)
      })
    })
  })
})
