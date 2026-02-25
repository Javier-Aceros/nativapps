export type Channel = 'email' | 'slack' | 'sms'

export interface Message {
  id: number
  title: string
  original_content: string
  summary: string | null
  status: 'pending' | 'processing' | 'completed' | 'failed'
  created_at: string
}

export interface DeliveryLog {
  id: number
  message_id: number
  channel: Channel
  status: 'pending' | 'success' | 'failed'
  error_code?: string
  created_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
  per_page: number
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
