import { apiClient } from '../../../core/api/client'
import type { MessageWithLogs } from '../../../core/types'

export const historyService = {
  getAll: async (): Promise<MessageWithLogs[]> => {
    const { data } = await apiClient.get<MessageWithLogs[]>('/messages')
    return data
  },
}
