import { describe, it, expect } from 'vitest'
import { http, HttpResponse } from 'msw'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { server } from '../../../test/server'
import { renderWithProviders } from '../../../test/utils'
import { HistoryDashboard } from './HistoryDashboard'
import type { DeliveryLog, MessageWithLogs, PaginatedResponse } from '../../../core/types'

// ─── Fixtures ────────────────────────────────────────────────────────────────

const makeLog = (overrides: Partial<DeliveryLog> = {}): DeliveryLog => ({
  id: 1,
  message_id: 1,
  channel: 'email',
  attempt: 1,
  status: 'success',
  created_at: '2026-02-24T00:00:00.000Z',
  ...overrides,
})

const makeMessage = (overrides: Partial<MessageWithLogs> = {}): MessageWithLogs => ({
  id: 1,
  title: 'Título de prueba',
  original_content: 'Contenido original',
  summary: 'Resumen generado por IA',
  status: 'completed',
  created_at: '2026-02-24T00:00:00.000Z',
  delivery_logs: [makeLog()],
  ...overrides,
})

function paginated(messages: MessageWithLogs[]): PaginatedResponse<MessageWithLogs> {
  return { data: messages, current_page: 1, last_page: 1, total: messages.length, per_page: 15 }
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('HistoryDashboard', () => {
  // ── Loading & Error & Empty ───────────────────────────────────────────────

  describe('loading state', () => {
    it('shows a loading indicator while the first fetch is in-flight', () => {
      server.use(http.get('/api/messages', () => new Promise(() => {})))

      renderWithProviders(<HistoryDashboard />)

      expect(screen.getByText(/Cargando historial/i)).toBeInTheDocument()
    })
  })

  describe('error state', () => {
    it('shows an alert when the API returns an error', async () => {
      server.use(
        http.get('/api/messages', () =>
          HttpResponse.json({ message: 'Server error' }, { status: 500 }),
        ),
      )

      renderWithProviders(<HistoryDashboard />)

      await screen.findByRole('alert')
      expect(screen.getByText(/No se pudo cargar el historial/i)).toBeInTheDocument()
    })
  })

  describe('empty state', () => {
    it('shows a message when the history is empty', async () => {
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText(/Aún no hay mensajes enviados/i)
    })
  })

  // ── Table Rendering ───────────────────────────────────────────────────────

  describe('table rendering', () => {
    it('renders the message title and AI summary', async () => {
      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([makeMessage()]))),
      )

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Título de prueba')
      expect(screen.getByText('Resumen generado por IA')).toBeInTheDocument()
    })

    it('renders a badge for each delivery log', async () => {
      const msg = makeMessage({
        delivery_logs: [
          makeLog({ id: 1, channel: 'email', status: 'success' }),
          makeLog({ id: 2, channel: 'slack', status: 'failed' }),
          makeLog({ id: 3, channel: 'sms', status: 'pending' }),
        ],
      })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Email')
      expect(screen.getByText('Slack')).toBeInTheDocument()
      expect(screen.getByText('SMS')).toBeInTheDocument()
    })

    it('shows the attempt number badge when attempt > 1', async () => {
      const msg = makeMessage({ delivery_logs: [makeLog({ attempt: 3 })] })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('#3')
    })

    it('does not show an attempt badge when attempt is 1', async () => {
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([makeMessage()]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Email')
      expect(screen.queryByText(/^#\d/)).toBeNull()
    })

    it('shows a dash placeholder when summary is null and message is not failed', async () => {
      const msg = makeMessage({ summary: null, status: 'processing' })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('—')
    })

    it('renders the refresh button', async () => {
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText(/Aún no hay mensajes enviados/i)
      expect(screen.getByRole('button', { name: /Actualizar historial/i })).toBeInTheDocument()
    })

    it('triggers a refetch when the refresh button is clicked', async () => {
      const user = userEvent.setup()
      let fetchCount = 0

      server.use(
        http.get('/api/messages', () => {
          fetchCount++
          return HttpResponse.json(paginated([]))
        }),
      )

      renderWithProviders(<HistoryDashboard />)
      await screen.findByText(/Aún no hay mensajes enviados/i)

      await user.click(screen.getByRole('button', { name: /Actualizar historial/i }))

      await waitFor(() => expect(fetchCount).toBeGreaterThan(1))
    })
  })

  // ── Channel Retry Button Visibility ───────────────────────────────────────

  describe('channel retry button visibility', () => {
    it('shows the retry button for a failed channel when the message has a summary', async () => {
      const msg = makeMessage({
        summary: 'Resumen IA',
        delivery_logs: [makeLog({ status: 'failed' })],
      })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByRole('button', { name: /Reintentar envío por Email/i })
    })

    it('does not show the retry button when the channel succeeded', async () => {
      const msg = makeMessage({ delivery_logs: [makeLog({ status: 'success' })] })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Email')
      expect(screen.queryByRole('button', { name: /Reintentar envío por/i })).toBeNull()
    })

    it('does not show the retry button when summary is null (AI never succeeded)', async () => {
      const msg = makeMessage({
        summary: null,
        status: 'failed',
        delivery_logs: [makeLog({ status: 'failed' })],
      })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Título de prueba')
      expect(screen.queryByRole('button', { name: /Reintentar envío por/i })).toBeNull()
    })

    it('hides the retry button while the channel retry is in-flight', async () => {
      const user = userEvent.setup()
      const msg = makeMessage({
        summary: 'Resumen IA',
        delivery_logs: [makeLog({ status: 'failed' })],
      })

      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([msg]))),
        // Never-resolving response keeps the component in the retrying state
        http.post('/api/messages/1/channels/email/retry', () => new Promise(() => {})),
      )

      renderWithProviders(<HistoryDashboard />)

      const retryBtn = await screen.findByRole('button', { name: /Reintentar envío por Email/i })
      await user.click(retryBtn)

      await waitFor(() =>
        expect(screen.queryByRole('button', { name: /Reintentar envío por Email/i })).toBeNull(),
      )
    })

    it('calls the channel retry endpoint when the button is clicked', async () => {
      const user = userEvent.setup()
      let retryCalled = false
      const msg = makeMessage({
        summary: 'Resumen IA',
        delivery_logs: [makeLog({ status: 'failed' })],
      })

      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([msg]))),
        http.post('/api/messages/1/channels/email/retry', () => {
          retryCalled = true
          return new Promise(() => {}) // keep in-flight — avoids MIN_LOADING_MS timer
        }),
      )

      renderWithProviders(<HistoryDashboard />)

      await user.click(
        await screen.findByRole('button', { name: /Reintentar envío por Email/i }),
      )

      await waitFor(() => expect(retryCalled).toBe(true))
    })
  })

  // ── AI Retry Button Visibility ────────────────────────────────────────────

  describe('AI retry button visibility', () => {
    it('shows "Reintentar con IA" when message status is failed', async () => {
      const msg = makeMessage({ summary: null, status: 'failed', delivery_logs: [] })
      server.use(http.get('/api/messages', () => HttpResponse.json(paginated([msg]))))

      renderWithProviders(<HistoryDashboard />)

      await screen.findByRole('button', { name: /Reintentar procesamiento con IA/i })
    })

    it('does not show AI retry button when message is completed', async () => {
      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([makeMessage()]))),
      )

      renderWithProviders(<HistoryDashboard />)

      await screen.findByText('Título de prueba')
      expect(screen.queryByRole('button', { name: /Reintentar con IA/i })).toBeNull()
    })

    it('shows the retrying indicator after clicking "Reintentar con IA"', async () => {
      const user = userEvent.setup()
      const msg = makeMessage({ id: 1, summary: null, status: 'failed', delivery_logs: [] })

      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([msg]))),
        http.post('/api/messages/1/retry', () => new Promise(() => {})),
      )

      renderWithProviders(<HistoryDashboard />)

      await user.click(
        await screen.findByRole('button', { name: /Reintentar procesamiento con IA/i }),
      )

      await screen.findByText(/Reintentando con IA/i)
    })

    it('calls the AI retry endpoint when the button is clicked', async () => {
      const user = userEvent.setup()
      let retryCalled = false
      const msg = makeMessage({ id: 1, summary: null, status: 'failed', delivery_logs: [] })

      server.use(
        http.get('/api/messages', () => HttpResponse.json(paginated([msg]))),
        http.post('/api/messages/1/retry', () => {
          retryCalled = true
          return new Promise(() => {})
        }),
      )

      renderWithProviders(<HistoryDashboard />)

      await user.click(
        await screen.findByRole('button', { name: /Reintentar procesamiento con IA/i }),
      )

      await waitFor(() => expect(retryCalled).toBe(true))
    })
  })
})
