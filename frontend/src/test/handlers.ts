import { http, HttpResponse } from 'msw'

export const mockMessage = {
  id: 1,
  title: 'Mi Título de Prueba',
  original_content: 'Contenido de prueba extenso para cumplir el mínimo.',
  summary: 'Resumen generado por IA',
  created_at: '2026-02-24T00:00:00.000Z',
  delivery_logs: [],
}

export const handlers = [
  http.post('/api/messages', () =>
    HttpResponse.json(mockMessage, { status: 201 }),
  ),
]
