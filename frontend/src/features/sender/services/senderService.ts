import { apiClient } from '../../../core/api/client'
import type { SendPayload, MessageWithLogs } from '../../../core/types'

export const senderService = {
  send: async (payload: SendPayload): Promise<MessageWithLogs> => {
    const { data } = await apiClient.post<MessageWithLogs>('/messages', payload)
    return data
  },
}
