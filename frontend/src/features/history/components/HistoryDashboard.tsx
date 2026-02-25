import { useHistory } from '../hooks/useHistory'
import type { DeliveryLog } from '../../../core/types'
import './HistoryDashboard.css'

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
  network_error:      'Error de red',
  config_error:       'Error de configuración',
  channel_error:      'Error del canal',
  ai_error:           'Error al contactar el servicio de IA',
  ai_summary_too_long: 'El resumen generado fue demasiado extenso',
}

const AI_SUMMARY_ERROR_LABEL: Record<string, string> = {
  ai_summary_too_long: 'El resumen generado por la IA superó el límite de caracteres permitido',
  ai_error:            'El servicio de IA no pudo procesar el contenido',
}

function ChannelLog({ log }: { log: DeliveryLog }) {
  const errorLabel =
    log.status === 'failed'
      ? (ERROR_LABEL[log.error_code ?? ''] ?? 'Error desconocido')
      : null

  return (
    <div className="channel-log">
      <span className={`status-badge status-badge--${log.status}`}>
        <span className="status-badge__icon" aria-hidden="true">
          {STATUS_ICON[log.status] ?? '?'}
        </span>
        <span className="status-badge__channel">
          {CHANNEL_LABEL[log.channel] ?? log.channel}
        </span>
        <span className="status-badge__status">
          {STATUS_LABEL[log.status] ?? log.status}
        </span>
        {errorLabel && (
          <span className="status-badge__error">{errorLabel}</span>
        )}
      </span>
    </div>
  )
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const min = String(d.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd} ${hh}:${min}`
}

export function HistoryDashboard() {
  const { data: messages, isLoading, isError, refetch } = useHistory()

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

      {!isLoading && !isError && messages?.length === 0 && (
        <div className="history-state history-state--empty">
          Aún no hay mensajes enviados.
        </div>
      )}

      {!isLoading && !isError && messages && messages.length > 0 && (
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
              {messages.map((msg) => (
                <tr key={msg.id} className="history-table__row">
                  <td className="history-table__td history-table__td--date">
                    {formatDate(msg.created_at)}
                  </td>
                  <td className="history-table__td history-table__td--title">
                    {msg.title}
                  </td>
                  <td className="history-table__td history-table__td--summary">
                    {msg.summary ?? (
                      msg.status === 'failed'
                        ? <span className="summary--error">
                            {AI_SUMMARY_ERROR_LABEL[msg.delivery_logs[0]?.error_code ?? ''] ?? 'Error desconocido'}
                          </span>
                        : <span className="summary--empty">—</span>
                    )}
                  </td>
                  <td className="history-table__td history-table__td--channels">
                    <div className="channel-badges">
                      {msg.delivery_logs.map((log) => (
                        <ChannelLog key={log.id} log={log} />
                      ))}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
