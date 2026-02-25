import { useQuery } from '@tanstack/react-query'
import { historyService } from '../services/historyService'

export function useHistory() {
  return useQuery({
    queryKey: ['messages'],
    queryFn: historyService.getAll,
  })
}
