import { useQuery } from '@tanstack/react-query'
import { historyService } from '../services/historyService'
import type { MessageWithLogs } from '../../../core/types'

export function useHistory() {
  return useQuery({
    queryKey: ['messages'],
    queryFn: historyService.getAll,
    // Poll every 3 s while any delivery log is still pending (job in queue)
    refetchInterval: (query) => {
      const data = query.state.data as MessageWithLogs[] | undefined
      const hasPending = data?.some((msg) =>
        msg.delivery_logs.some((log) => log.status === 'pending'),
      )
      return hasPending ? 3000 : false
    },
  })
}
