import { describe, it, expect, beforeEach } from 'vitest'
import { http, HttpResponse } from 'msw'
import userEvent from '@testing-library/user-event'
import { screen, waitFor } from '@testing-library/react'
import { server } from '../../../test/server'
import { mockMessage } from '../../../test/handlers'
import { renderWithProviders } from '../../../test/utils'
import { useSenderStore } from '../store/senderStore'
import { SenderForm } from './SenderForm'

// ─── Helpers ────────────────────────────────────────────────────────────────

async function fillValidForm(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Título'), 'Mi Título de Prueba')
  await user.type(
    screen.getByLabelText('Contenido'),
    'Contenido suficientemente largo para pasar la validación.',
  )
  await user.click(screen.getByRole('checkbox', { name: /Email/i }))
}

// ─── Test Suite ──────────────────────────────────────────────────────────────

describe('SenderForm', () => {
  beforeEach(() => {
    // Reset the Zustand store to avoid state leakage between tests
    useSenderStore.setState({ title: '', content: '', channels: [] })
  })

  // ── Rendering ──────────────────────────────────────────────────────────────

  describe('rendering', () => {
    it('renders all form fields and the submit button', () => {
      renderWithProviders(<SenderForm />)

      expect(screen.getByLabelText('Título')).toBeInTheDocument()
      expect(screen.getByLabelText('Contenido')).toBeInTheDocument()
      expect(
        screen.getByRole('button', { name: /Enviar y Procesar/i }),
      ).toBeInTheDocument()
    })

    it('renders checkboxes for all three channels', () => {
      renderWithProviders(<SenderForm />)

      expect(
        screen.getByRole('checkbox', { name: /Email/i }),
      ).toBeInTheDocument()
      expect(
        screen.getByRole('checkbox', { name: /Slack/i }),
      ).toBeInTheDocument()
      expect(
        screen.getByRole('checkbox', { name: /SMS/i }),
      ).toBeInTheDocument()
    })

    it('all channels start unchecked', () => {
      renderWithProviders(<SenderForm />)

      expect(screen.getByRole('checkbox', { name: /Email/i })).not.toBeChecked()
      expect(screen.getByRole('checkbox', { name: /Slack/i })).not.toBeChecked()
      expect(screen.getByRole('checkbox', { name: /SMS/i })).not.toBeChecked()
    })
  })

  // ── Validation ─────────────────────────────────────────────────────────────

  describe('client-side validation', () => {
    it('shows an error when title is empty and the form is submitted', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      expect(
        screen.getByText('El título es requerido.'),
      ).toBeInTheDocument()
    })

    it('shows an error when content is empty', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await user.type(screen.getByLabelText('Título'), 'Título válido')
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      expect(
        screen.getByText('El contenido es requerido.'),
      ).toBeInTheDocument()
    })

    it('shows an error when content is shorter than 10 characters', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await user.type(screen.getByLabelText('Título'), 'Título válido')
      await user.type(screen.getByLabelText('Contenido'), 'Corto')
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      expect(
        screen.getByText('El contenido debe tener al menos 10 caracteres.'),
      ).toBeInTheDocument()
    })

    it('shows an error when no distribution channel is selected', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await user.type(screen.getByLabelText('Título'), 'Título válido')
      await user.type(
        screen.getByLabelText('Contenido'),
        'Contenido suficientemente largo.',
      )
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      expect(
        screen.getByText('Selecciona al menos un canal de distribución.'),
      ).toBeInTheDocument()
    })

    it('does not call the API when the form is invalid', async () => {
      const user = userEvent.setup()
      let apiCalled = false
      server.use(
        http.post('/api/messages', () => {
          apiCalled = true
          return HttpResponse.json(mockMessage, { status: 201 })
        }),
      )

      renderWithProviders(<SenderForm />)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      expect(apiCalled).toBe(false)
    })

    it('toggles a channel checkbox on click', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      const emailCheckbox = screen.getByRole('checkbox', { name: /Email/i })
      await user.click(emailCheckbox)
      expect(emailCheckbox).toBeChecked()

      await user.click(emailCheckbox)
      expect(emailCheckbox).not.toBeChecked()
    })
  })

  // ── Successful Submission ──────────────────────────────────────────────────

  describe('successful submission', () => {
    it('shows loading state while the request is in flight', async () => {
      const user = userEvent.setup()

      // Defer the response so we can inspect the loading state
      let resolveRequest!: () => void
      const requestPending = new Promise<void>((resolve) => {
        resolveRequest = resolve
      })
      server.use(
        http.post('/api/messages', async () => {
          await requestPending
          return HttpResponse.json(mockMessage, { status: 201 })
        }),
      )

      renderWithProviders(<SenderForm />)
      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      // The submit button becomes disabled and shows the loading label
      await waitFor(() =>
        expect(
          screen.getByRole('button', { name: /Procesando/i }),
        ).toBeDisabled(),
      )

      // Resolve the request and verify the loading state clears
      resolveRequest()
      await screen.findByText('Mensaje procesado exitosamente')
    })

    it('displays the success state with the AI-generated summary', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      await screen.findByText('Mensaje procesado exitosamente')
      expect(screen.getByText(mockMessage.summary)).toBeInTheDocument()
    })

    it('resets the Zustand store after a successful submission', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      await screen.findByText('Mensaje procesado exitosamente')

      const { title, content, channels } = useSenderStore.getState()
      expect(title).toBe('')
      expect(content).toBe('')
      expect(channels).toEqual([])
    })

    it('returns to the form view when "Enviar otro mensaje" is clicked', async () => {
      const user = userEvent.setup()
      renderWithProviders(<SenderForm />)

      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))
      await screen.findByText('Mensaje procesado exitosamente')

      await user.click(screen.getByText('Enviar otro mensaje'))

      expect(
        screen.getByRole('button', { name: /Enviar y Procesar/i }),
      ).toBeInTheDocument()
      expect(screen.queryByText('Mensaje procesado exitosamente')).toBeNull()
    })
  })

  // ── API Error Handling ─────────────────────────────────────────────────────

  describe('API error handling', () => {
    it('shows the RFC 7807 "detail" error message returned by the AI service', async () => {
      const user = userEvent.setup()
      server.use(
        http.post('/api/messages', () =>
          HttpResponse.json(
            { detail: 'El servicio de IA no está disponible en este momento.' },
            { status: 422 },
          ),
        ),
      )

      renderWithProviders(<SenderForm />)
      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      await screen.findByRole('alert')
      expect(
        screen.getByText('El servicio de IA no está disponible en este momento.'),
      ).toBeInTheDocument()
    })

    it('shows the Laravel validation "message" error as fallback', async () => {
      const user = userEvent.setup()
      server.use(
        http.post('/api/messages', () =>
          HttpResponse.json(
            { message: 'Error de validación en el servidor.' },
            { status: 422 },
          ),
        ),
      )

      renderWithProviders(<SenderForm />)
      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      await screen.findByRole('alert')
      expect(
        screen.getByText('Error de validación en el servidor.'),
      ).toBeInTheDocument()
    })

    it('shows a generic error message when the server returns no detail', async () => {
      const user = userEvent.setup()
      server.use(
        http.post('/api/messages', () =>
          HttpResponse.json({}, { status: 500 }),
        ),
      )

      renderWithProviders(<SenderForm />)
      await fillValidForm(user)
      await user.click(screen.getByRole('button', { name: /Enviar y Procesar/i }))

      await screen.findByRole('alert')
      expect(
        screen.getByText('Error al procesar el mensaje. Inténtalo de nuevo.'),
      ).toBeInTheDocument()
    })
  })
})
