import { describe, it, expect } from 'vitest'
import { http, HttpResponse } from 'msw'
import { server } from '../../../test/server'
import { historyService } from './historyService'
import type { DeliveryLog, MessageWithLogs, PaginatedResponse } from '../../../core/types'

// ─── Fixtures ────────────────────────────────────────────────────────────────

const mockLog: DeliveryLog = {
  id: 1,
  message_id: 1,
  channel: 'email',
  attempt: 1,
  status: 'success',
  created_at: '2026-02-24T00:00:00.000Z',
}

const mockMessage: MessageWithLogs = {
  id: 1,
  title: 'Título de prueba',
  original_content: 'Contenido original',
  summary: 'Resumen generado por IA',
  status: 'completed',
  created_at: '2026-02-24T00:00:00.000Z',
  delivery_logs: [mockLog],
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('historyService', () => {
  describe('getAll', () => {
    it('unwraps the paginated response and returns the inner data array', async () => {
      const paginated: PaginatedResponse<MessageWithLogs> = {
        data: [mockMessage],
        current_page: 1,
        last_page: 1,
        total: 1,
        per_page: 15,
      }
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated)))

      await expect(historyService.getAll()).resolves.toEqual([mockMessage])
    })

    it('returns an empty array when the page has no items', async () => {
      const paginated: PaginatedResponse<MessageWithLogs> = {
        data: [],
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 15,
      }
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated)))

      await expect(historyService.getAll()).resolves.toEqual([])
    })
  })

  describe('retryMessage', () => {
    it('POSTs to /messages/:id/retry and returns the updated message', async () => {
      server.use(
        http.post('/api/messages/42/retry', () => HttpResponse.json(mockMessage)),
      )

      await expect(historyService.retryMessage(42)).resolves.toEqual(mockMessage)
    })
  })

  describe('retryChannel', () => {
    it('POSTs to /messages/:id/channels/:channel/retry and returns the log', async () => {
      server.use(
        http.post('/api/messages/7/channels/sms/retry', () => HttpResponse.json(mockLog)),
      )

      await expect(historyService.retryChannel(7, 'sms')).resolves.toEqual(mockLog)
    })
  })
})
