import { useState } from 'react'
import { useHistory } from '../hooks/useHistory'
import { useRetry } from '../hooks/useRetry'
import type { Channel, DeliveryLog, MessageWithLogs } from '../../../core/types'
import './HistoryDashboard.css'

// ─── Constants ────────────────────────────────────────────────────────────────

const CHANNEL_LABEL: Record<string, string> = {
  email: 'Email',
  slack: 'Slack',
  sms: 'SMS',
}

const STATUS_ICON: Record<string, string> = {
  success: '✓',
  failed: '✗',
  pending: '⟳',
}

const STATUS_LABEL: Record<string, string> = {
  success: 'Enviado',
  failed: 'Fallido',
  pending: 'Pendiente',
}

const ERROR_LABEL: Record<string, string> = {
  network_error:       'Error de red',
  config_error:        'Error de configuración',
  channel_error:       'Error del canal',
  ai_error:            'Error al contactar el servicio de IA',
  ai_summary_too_long: 'El resumen generado fue demasiado extenso',
}

const AI_SUMMARY_ERROR_LABEL: Record<string, string> = {
  ai_summary_too_long: 'El resumen generado por la IA superó el límite de caracteres permitido',
  ai_error:            'El servicio de IA no pudo procesar el contenido',
}

const VALID_PER_PAGE = [5, 10, 15, 25, 50] as const
type PerPage = (typeof VALID_PER_PAGE)[number]

const PER_PAGE_STORAGE_KEY = 'mccp:history:perPage'

function readPerPage(): PerPage {
  const stored = localStorage.getItem(PER_PAGE_STORAGE_KEY)
  const n = Number(stored) as PerPage
  return VALID_PER_PAGE.includes(n) ? n : 15
}

// ─── ChannelLog ─────────────────────────────────────────────────────────────

interface ChannelLogProps {
  log: DeliveryLog
  canRetry: boolean
  isRetrying: boolean
  aiFailure: boolean
  onRetry: () => void
}

function ChannelLog({ log, canRetry, isRetrying, aiFailure, onRetry }: ChannelLogProps) {
  if (aiFailure) {
    return (
      <div className="channel-log">
        <span className="status-badge status-badge--skipped">
          <span className="status-badge__icon" aria-hidden="true">—</span>
          <span className="status-badge__channel">
            {CHANNEL_LABEL[log.channel] ?? log.channel}
          </span>
          <span className="status-badge__status">No intentado</span>
        </span>
      </div>
    )
  }

  const errorLabel =
    !isRetrying && log.status === 'failed'
      ? (ERROR_LABEL[log.error_code ?? ''] ?? 'Error desconocido')
      : null

  const badgeStatus = isRetrying ? 'pending' : log.status

  return (
    <div className="channel-log">
      <span className={`status-badge status-badge--${badgeStatus}`}>
        <span className="status-badge__icon" aria-hidden="true">
          {isRetrying
            ? <span className="spinner spinner--inline" aria-hidden="true" />
            : (STATUS_ICON[log.status] ?? '?')}
        </span>
        <span className="status-badge__channel">
          {CHANNEL_LABEL[log.channel] ?? log.channel}
          {log.attempt > 1 && <span className="status-badge__attempt"> #{log.attempt}</span>}
        </span>
        <span className="status-badge__status">
          {isRetrying ? 'Reintentando…' : (STATUS_LABEL[log.status] ?? log.status)}
        </span>
        {errorLabel && (
          <span className="status-badge__error">{errorLabel}</span>
        )}
      </span>

      {canRetry && !isRetrying && (
        <button
          className="retry-btn retry-btn--channel"
          onClick={onRetry}
          aria-label={`Reintentar envío por ${CHANNEL_LABEL[log.channel] ?? log.channel}`}
        >
          ↻ Reintentar
        </button>
      )}
    </div>
  )
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function formatDate(iso: string): string {
  const d = new Date(iso)
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const min = String(d.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd} ${hh}:${min}`
}

// ─── Summary cell ────────────────────────────────────────────────────────────

interface SummaryCellProps {
  msg: MessageWithLogs
  isRetrying: boolean
  onRetry: () => void
}

function SummaryCell({ msg, isRetrying, onRetry }: SummaryCellProps) {
  if (isRetrying) {
    return (
      <span className="summary--retrying">
        <span className="spinner spinner--inline" aria-hidden="true" /> Reintentando con IA…
      </span>
    )
  }

  if (msg.summary) {
    return <>{msg.summary}</>
  }

  if (msg.status === 'failed') {
    return (
      <div className="summary-error-group">
        <span className="summary--error">
          {AI_SUMMARY_ERROR_LABEL[msg.delivery_logs[0]?.error_code ?? ''] ?? 'Error desconocido'}
        </span>
        <button
          className="retry-btn retry-btn--ai"
          onClick={onRetry}
          aria-label="Reintentar procesamiento con IA"
        >
          ↻ Reintentar con IA
        </button>
      </div>
    )
  }

  return <span className="summary--empty">—</span>
}

// ─── Pagination ──────────────────────────────────────────────────────────────

interface PaginationProps {
  page: number
  lastPage: number
  total: number
  perPage: PerPage
  from: number
  to: number
  onPageChange: (page: number) => void
  onPerPageChange: (perPage: PerPage) => void
}

function Pagination({
  page, lastPage, total, perPage, from, to,
  onPageChange, onPerPageChange,
}: PaginationProps) {
  return (
    <div className="pagination">
      <span className="pagination__info">
        Mostrando <strong>{from}–{to}</strong> de <strong>{total}</strong>
      </span>

      <div className="pagination__controls">
        <label className="pagination__per-page-label" htmlFor="per-page-select">
          Por página:
        </label>
        <select
          id="per-page-select"
          className="pagination__per-page"
          value={perPage}
          onChange={(e) => onPerPageChange(Number(e.target.value) as PerPage)}
        >
          {VALID_PER_PAGE.map((n) => (
            <option key={n} value={n}>{n}</option>
          ))}
        </select>

        <div className="pagination__nav" role="navigation" aria-label="Páginas">
          <button
            className="pagination__btn"
            onClick={() => onPageChange(1)}
            disabled={page === 1}
            aria-label="Primera página"
          >
            «
          </button>
          <button
            className="pagination__btn"
            onClick={() => onPageChange(page - 1)}
            disabled={page === 1}
            aria-label="Página anterior"
          >
            ‹
          </button>
          <span className="pagination__page-info">
            {page} / {lastPage}
          </span>
          <button
            className="pagination__btn"
            onClick={() => onPageChange(page + 1)}
            disabled={page >= lastPage}
            aria-label="Página siguiente"
          >
            ›
          </button>
          <button
            className="pagination__btn"
            onClick={() => onPageChange(lastPage)}
            disabled={page >= lastPage}
            aria-label="Última página"
          >
            »
          </button>
        </div>
      </div>
    </div>
  )
}

// ─── HistoryDashboard ────────────────────────────────────────────────────────

export function HistoryDashboard() {
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<PerPage>(readPerPage)

  const { data, isLoading, isError, refetch } = useHistory(page, perPage)
  const { retryMessage, retryChannel, isRetrying } = useRetry()

  const messages = data?.data
  const total = data?.total ?? 0
  const lastPage = data?.last_page ?? 1
  const from = total === 0 ? 0 : (page - 1) * perPage + 1
  const to = Math.min(page * perPage, total)

  function handlePerPageChange(value: PerPage) {
    localStorage.setItem(PER_PAGE_STORAGE_KEY, String(value))
    setPerPage(value)
    setPage(1)
  }

  return (
    <section className="history-section" aria-label="Historial de Mensajes">
      <div className="history-section__header">
        <h2 className="history-section__title">Historial de Mensajes</h2>
        <button
          className="history-section__refresh"
          onClick={() => void refetch()}
          aria-label="Actualizar historial"
        >
          ↻ Actualizar
        </button>
      </div>

      {isLoading && (
        <div className="history-state" aria-live="polite">
          <span className="spinner" /> Cargando historial…
        </div>
      )}

      {isError && (
        <div className="history-state history-state--error" role="alert">
          No se pudo cargar el historial. Verifica la conexión con el backend.
        </div>
      )}

      {!isLoading && !isError && total === 0 && (
        <div className="history-state history-state--empty">
          Aún no hay mensajes enviados.
        </div>
      )}

      {!isLoading && !isError && messages && messages.length > 0 && (
        <>
          <div className="history-table-wrapper">
            <table className="history-table">
              <thead>
                <tr>
                  <th className="history-table__th history-table__th--date">Fecha</th>
                  <th className="history-table__th">Título</th>
                  <th className="history-table__th">Resumen IA</th>
                  <th className="history-table__th history-table__th--channels">Canales</th>
                </tr>
              </thead>
              <tbody>
                {messages.map((msg) => {
                  const aiKey = `ai-${msg.id}`
                  const retryingAi = isRetrying(aiKey)

                  return (
                    <tr key={msg.id} className="history-table__row">
                      <td className="history-table__td history-table__td--date">
                        {formatDate(msg.created_at)}
                      </td>
                      <td className="history-table__td history-table__td--title">
                        {msg.title}
                      </td>
                      <td className="history-table__td history-table__td--summary">
                        <SummaryCell
                          msg={msg}
                          isRetrying={retryingAi}
                          onRetry={() => void retryMessage(msg.id)}
                        />
                      </td>
                      <td className="history-table__td history-table__td--channels">
                        <div className="channel-badges">
                          {msg.delivery_logs.map((log) => {
                            const channelKey = `channel-${msg.id}-${log.channel}`
                            const retryingChannel = isRetrying(channelKey)
                            const canRetry = msg.summary !== null && log.status === 'failed'

                            return (
                              <ChannelLog
                                key={log.id}
                                log={log}
                                aiFailure={msg.summary === null && msg.status === 'failed'}
                                canRetry={canRetry}
                                isRetrying={retryingChannel}
                                onRetry={() =>
                                  void retryChannel(msg.id, log.channel as Channel)
                                }
                              />
                            )
                          })}
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          <Pagination
            page={page}
            lastPage={lastPage}
            total={total}
            perPage={perPage}
            from={from}
            to={to}
            onPageChange={setPage}
            onPerPageChange={handlePerPageChange}
          />
        </>
      )}
    </section>
  )
}
