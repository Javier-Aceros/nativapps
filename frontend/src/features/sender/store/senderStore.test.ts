import { describe, it, expect, beforeEach } from 'vitest'
import { useSenderStore } from './senderStore'

const INITIAL_STATE = { title: '', content: '', channels: [] as const }

describe('useSenderStore', () => {
  beforeEach(() => {
    // Reset store to initial state before each test to ensure isolation
    useSenderStore.setState({ title: '', content: '', channels: [] })
  })

  describe('initial state', () => {
    it('has empty title, content and no channels selected', () => {
      const { title, content, channels } = useSenderStore.getState()
      expect(title).toBe(INITIAL_STATE.title)
      expect(content).toBe(INITIAL_STATE.content)
      expect(channels).toEqual(INITIAL_STATE.channels)
    })
  })

  describe('setTitle', () => {
    it('updates the title', () => {
      useSenderStore.getState().setTitle('Nuevo Título')
      expect(useSenderStore.getState().title).toBe('Nuevo Título')
    })

    it('replaces an existing title', () => {
      useSenderStore.getState().setTitle('Primer título')
      useSenderStore.getState().setTitle('Segundo título')
      expect(useSenderStore.getState().title).toBe('Segundo título')
    })
  })

  describe('setContent', () => {
    it('updates the content', () => {
      useSenderStore.getState().setContent('Contenido del mensaje')
      expect(useSenderStore.getState().content).toBe('Contenido del mensaje')
    })
  })

  describe('toggleChannel', () => {
    it('adds a channel when it is not present', () => {
      useSenderStore.getState().toggleChannel('email')
      expect(useSenderStore.getState().channels).toContain('email')
    })

    it('removes a channel when it is already selected', () => {
      useSenderStore.setState({ channels: ['email', 'slack'] })
      useSenderStore.getState().toggleChannel('email')
      expect(useSenderStore.getState().channels).toEqual(['slack'])
      expect(useSenderStore.getState().channels).not.toContain('email')
    })

    it('allows selecting all three channels independently', () => {
      useSenderStore.getState().toggleChannel('email')
      useSenderStore.getState().toggleChannel('slack')
      useSenderStore.getState().toggleChannel('sms')
      expect(useSenderStore.getState().channels).toHaveLength(3)
      expect(useSenderStore.getState().channels).toEqual(['email', 'slack', 'sms'])
    })

    it('deselecting one channel does not affect the others', () => {
      useSenderStore.setState({ channels: ['email', 'slack', 'sms'] })
      useSenderStore.getState().toggleChannel('slack')
      const { channels } = useSenderStore.getState()
      expect(channels).toContain('email')
      expect(channels).not.toContain('slack')
      expect(channels).toContain('sms')
    })
  })

  describe('reset', () => {
    it('restores all fields to their initial values', () => {
      useSenderStore.setState({
        title: 'Título a limpiar',
        content: 'Contenido a limpiar',
        channels: ['email', 'slack'],
      })

      useSenderStore.getState().reset()

      const { title, content, channels } = useSenderStore.getState()
      expect(title).toBe('')
      expect(content).toBe('')
      expect(channels).toEqual([])
    })
  })
})
