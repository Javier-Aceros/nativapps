import { useState } from 'react'
import { useSenderStore } from '../store/senderStore'
import { useSendMessage } from '../hooks/useSendMessage'
import type { Channel } from '../../../core/types'
import './SenderForm.css'

const CHANNELS: { value: Channel; label: string; description: string }[] = [
  { value: 'email', label: 'Email', description: 'Envío simulado por REST' },
  { value: 'slack', label: 'Slack', description: 'Webhook real' },
  { value: 'sms', label: 'SMS', description: 'XML SOAP legacy' },
]

interface ValidationErrors {
  title?: string
  content?: string
  channels?: string
}

function validate(
  title: string,
  content: string,
  channels: Channel[],
): ValidationErrors {
  const errors: ValidationErrors = {}
  if (!title.trim()) {
    errors.title = 'El título es requerido.'
  } else if (title.trim().length > 100) {
    errors.title = 'El título no puede superar los 100 caracteres.'
  }
  if (!content.trim()) {
    errors.content = 'El contenido es requerido.'
  } else if (content.trim().length < 10) {
    errors.content = 'El contenido debe tener al menos 10 caracteres.'
  } else if (content.length > 65535) {
    errors.content = 'El contenido no puede superar los 65 535 caracteres.'
  }
  if (channels.length === 0) {
    errors.channels = 'Selecciona al menos un canal de distribución.'
  }
  return errors
}

function getApiErrorMessage(error: Error | null): string | null {
  if (!error) return null
  const axiosError = error as Error & {
    response?: { data?: { detail?: string; message?: string } }
  }
  const data = axiosError.response?.data
  if (data?.detail) return data.detail
  if (data?.message) return data.message
  return 'Error al procesar el mensaje. Inténtalo de nuevo.'
}

export function SenderForm() {
  const { title, content, channels, setTitle, setContent, toggleChannel, reset } =
    useSenderStore()
  const {
    mutate,
    isPending,
    isSuccess,
    isError,
    error,
    data,
    reset: resetMutation,
  } = useSendMessage()
  const [validationErrors, setValidationErrors] = useState<ValidationErrors>({})

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const errors = validate(title, content, channels)
    setValidationErrors(errors)
    if (Object.keys(errors).length > 0) return
    mutate(
      { title, content, channels },
      {
        onSuccess: () => {
          reset()
          setValidationErrors({})
        },
      },
    )
  }

  function handleSendAnother() {
    reset()
    resetMutation()
    setValidationErrors({})
  }

  const apiErrorMessage = getApiErrorMessage(isError ? error : null)

  return (
    <div className="sender-card">
      <h2 className="sender-card__title">Nuevo Mensaje</h2>

      {isSuccess && data && (
        <div className="sender-success">
          <p className="sender-success__headline">Mensaje procesado exitosamente</p>
          <div className="sender-success__summary">
            <span className="sender-success__label">Resumen IA</span>
            <p className="sender-success__text">{data.summary}</p>
          </div>
          <p className="sender-success__note">
            Los canales seleccionados están siendo procesados en segundo plano.
          </p>
          <button className="btn-link" onClick={handleSendAnother}>
            Enviar otro mensaje
          </button>
        </div>
      )}

      {!isSuccess && (
        <form onSubmit={handleSubmit} noValidate>
          <div className="form-field">
            <label htmlFor="title" className="form-label">
              Título
            </label>
            <input
              id="title"
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="Ej. Reporte semanal de ventas"
              className={`form-input${validationErrors.title || title.length > 100 ? ' form-input--error' : ''}`}
              disabled={isPending}
            />
            <span
              className={`form-char-count${title.length > 100 ? ' form-char-count--error' : ''}`}
            >
              {title.length}/100
            </span>
            {validationErrors.title && (
              <span className="form-field-error">{validationErrors.title}</span>
            )}
          </div>

          <div className="form-field">
            <label htmlFor="content" className="form-label">
              Contenido
            </label>
            <textarea
              id="content"
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder="Escribe el contenido completo aquí (mínimo 10 caracteres)..."
              rows={6}
              className={`form-textarea${validationErrors.content || content.length > 65535 ? ' form-input--error' : ''}`}
              disabled={isPending}
            />
            <span className={`form-char-count${content.length > 65535 ? ' form-char-count--error' : ''}`}>
              {content.length}/65 535
            </span>
            {validationErrors.content && (
              <span className="form-field-error">{validationErrors.content}</span>
            )}
          </div>

          <div className="form-field">
            <span className="form-label">Canales de distribución</span>
            <div className="channel-grid">
              {CHANNELS.map(({ value, label, description }) => (
                <label
                  key={value}
                  className={`channel-option${channels.includes(value) ? ' channel-option--selected' : ''}${isPending ? ' channel-option--disabled' : ''}`}
                >
                  <input
                    type="checkbox"
                    checked={channels.includes(value)}
                    onChange={() => toggleChannel(value)}
                    disabled={isPending}
                    className="channel-option__checkbox"
                  />
                  <span className="channel-option__label">{label}</span>
                  <span className="channel-option__desc">{description}</span>
                </label>
              ))}
            </div>
            {validationErrors.channels && (
              <span className="form-field-error">{validationErrors.channels}</span>
            )}
          </div>

          {isError && apiErrorMessage && (
            <div className="api-error" role="alert">
              {apiErrorMessage}
            </div>
          )}

          <div className="form-actions">
            <button type="submit" className="btn-primary" disabled={isPending}>
              {isPending ? (
                <span className="btn-primary__loading">
                  <span className="spinner" />
                  Procesando con IA…
                </span>
              ) : (
                'Enviar y Procesar'
              )}
            </button>
          </div>
        </form>
      )}
    </div>
  )
}
