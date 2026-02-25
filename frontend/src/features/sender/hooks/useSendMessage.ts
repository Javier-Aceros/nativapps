import { useMutation, useQueryClient } from '@tanstack/react-query'
import { senderService } from '../services/senderService'
import type { SendPayload } from '../../../core/types'

export function useSendMessage() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: SendPayload) => senderService.send(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['messages'] })
    },
    onError: () => {
      queryClient.invalidateQueries({ queryKey: ['messages'] })
    },
  })
}
