import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { historyService } from '../services/historyService'
import type { MessageWithLogs, PaginatedResponse } from '../../../core/types'

export function useHistory(page: number, perPage: number) {
  return useQuery<PaginatedResponse<MessageWithLogs>>({
    queryKey: ['messages', page, perPage],
    queryFn: () => historyService.getAll(page, perPage),
    placeholderData: keepPreviousData,
    refetchInterval: (query) => {
      const data = query.state.data as PaginatedResponse<MessageWithLogs> | undefined
      const hasPending = data?.data.some((msg) =>
        msg.delivery_logs.some((log) => log.status === 'pending'),
      )
      return hasPending ? 3000 : false
    },
  })
}
