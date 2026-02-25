import { useCallback, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { historyService } from '../services/historyService'
import type { Channel } from '../../../core/types'

const MIN_LOADING_MS = 800

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * Manages retry state for messages (AI retry) and individual channels.
 *
 * Uses a ref as the authoritative source (synchronous, no stale closure issues)
 * and a parallel state copy for triggering re-renders.
 *
 * Guarantees:
 * - At most one in-flight request per (message, channel) combination.
 * - Loading indicator visible for at least MIN_LOADING_MS, even if the error
 *   comes back immediately.
 */
export function useRetry() {
  const queryClient = useQueryClient()

  // Ref: synchronous guard (prevents double-submit on fast clicks)
  const retryingRef = useRef<Set<string>>(new Set())
  // State: triggers re-renders so components see the updated loading keys
  const [retryingKeys, setRetryingKeys] = useState<Set<string>>(new Set())

  const addKey = (key: string) => {
    retryingRef.current.add(key)
    setRetryingKeys(new Set(retryingRef.current))
  }

  const removeKey = (key: string) => {
    retryingRef.current.delete(key)
    setRetryingKeys(new Set(retryingRef.current))
  }

  /** Returns true while a retry is in-flight for the given key. */
  const isRetrying = useCallback(
    (key: string) => retryingKeys.has(key),
    [retryingKeys],
  )

  /** Retry the full AI + channel flow for a failed message. */
  const retryMessage = useCallback(
    async (messageId: number) => {
      const key = `ai-${messageId}`
      if (retryingRef.current.has(key)) return

      addKey(key)
      const start = Date.now()

      try {
        await historyService.retryMessage(messageId)
      } finally {
        const elapsed = Date.now() - start
        if (elapsed < MIN_LOADING_MS) await delay(MIN_LOADING_MS - elapsed)
        removeKey(key)
        void queryClient.invalidateQueries({ queryKey: ['messages'] })
      }
    },
    [queryClient],  
  )

  /** Retry delivery for a single channel of an already-processed message. */
  const retryChannel = useCallback(
    async (messageId: number, channel: Channel) => {
      const key = `channel-${messageId}-${channel}`
      if (retryingRef.current.has(key)) return

      addKey(key)
      const start = Date.now()

      try {
        await historyService.retryChannel(messageId, channel)
      } finally {
        const elapsed = Date.now() - start
        if (elapsed < MIN_LOADING_MS) await delay(MIN_LOADING_MS - elapsed)
        removeKey(key)
        void queryClient.invalidateQueries({ queryKey: ['messages'] })
      }
    },
    [queryClient],  
  )

  return { retryMessage, retryChannel, isRetrying }
}
