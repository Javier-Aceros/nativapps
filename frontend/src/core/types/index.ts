export type Channel = 'email' | 'slack' | 'sms'

export interface Message {
  id: number
  title: string
  original_content: string
  summary: string
  created_at: string
}

export interface DeliveryLog {
  id: number
  message_id: number
  channel: Channel
  status: 'success' | 'failed'
  error?: string
  created_at: string
}

export interface MessageWithLogs extends Message {
  delivery_logs: DeliveryLog[]
}

export interface SendPayload {
  title: string
  content: string
  channels: Channel[]
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
