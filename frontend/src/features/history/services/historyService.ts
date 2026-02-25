import { apiClient } from '../../../core/api/client'
import type { MessageWithLogs, PaginatedResponse } from '../../../core/types'

export const historyService = {
  getAll: async (): Promise<MessageWithLogs[]> => {
    const { data } = await apiClient.get<PaginatedResponse<MessageWithLogs>>('/messages')
    return data.data
  },
}
