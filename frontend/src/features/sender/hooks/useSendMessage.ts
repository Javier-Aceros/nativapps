import { useMutation } from '@tanstack/react-query'
import { senderService } from '../services/senderService'
import type { SendPayload } from '../../../core/types'

export function useSendMessage() {
  return useMutation({
    mutationFn: (payload: SendPayload) => senderService.send(payload),
  })
}
