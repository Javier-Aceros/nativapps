import { create } from 'zustand'
import type { Channel } from '../../../core/types'

interface SenderFormState {
  title: string
  content: string
  channels: Channel[]
}

interface SenderActions {
  setTitle: (title: string) => void
  setContent: (content: string) => void
  toggleChannel: (channel: Channel) => void
  reset: () => void
}

type SenderStore = SenderFormState & SenderActions

const initialState: SenderFormState = {
  title: '',
  content: '',
  channels: [],
}

export const useSenderStore = create<SenderStore>((set) => ({
  ...initialState,
  setTitle: (title) => set({ title }),
  setContent: (content) => set({ content }),
  toggleChannel: (channel) =>
    set((state) => ({
      channels: state.channels.includes(channel)
        ? state.channels.filter((c) => c !== channel)
        : [...state.channels, channel],
    })),
  reset: () => set(initialState),
}))
