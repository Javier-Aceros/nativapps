import { apiClient } from '../../../core/api/client'
import type { Channel, DeliveryLog, MessageWithLogs, PaginatedResponse } from '../../../core/types'

export const historyService = {
  getAll: async (page = 1, perPage = 15): Promise<PaginatedResponse<MessageWithLogs>> => {
    const { data } = await apiClient.get<PaginatedResponse<MessageWithLogs>>('/messages', {
      params: { page, per_page: perPage },
    })
    return data
  },

  retryMessage: async (id: number): Promise<MessageWithLogs> => {
    const { data } = await apiClient.post<MessageWithLogs>(`/messages/${id}/retry`)
    return data
  },

  retryChannel: async (messageId: number, channel: Channel): Promise<DeliveryLog> => {
    const { data } = await apiClient.post<DeliveryLog>(
      `/messages/${messageId}/channels/${channel}/retry`,
    )
    return data
  },
}
