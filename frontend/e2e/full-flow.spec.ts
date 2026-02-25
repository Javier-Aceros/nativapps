/**
 * Full-flow E2E — backend real requerido
 *
 * Este test NO intercepta la red: las peticiones llegan al backend Laravel
 * (http://localhost:8000 vía el proxy de Vite) y a la IA configurada en .env.
 *
 * Pre-requisitos para ejecutar:
 *   1. Backend corriendo:  cd backend && php artisan serve
 *   2. Base de datos lista: php artisan migrate
 *   3. .env con AI_PROVIDER y la API key correspondiente
 *
 * Lo que verifica que ninguna capa inferior puede confirmar por sí sola:
 *   - El formulario comunica correctamente con la API
 *   - La IA genera un resumen real y el backend lo persiste
 *   - El historial refleja el mensaje recién enviado sin recargar la página
 *     (gracias a la invalidación del caché de TanStack Query)
 */
import { test, expect } from '@playwright/test'

test('enviar formulario y ver el mensaje en el historial', async ({ page }) => {
  // Título único por ejecución para identificarlo inequívocamente en la tabla
  const title = `E2E ${Date.now()}`
  const content =
    'Contenido de prueba de integración completa. Este texto supera los diez caracteres mínimos requeridos por el backend.'

  await page.goto('/')

  // ── Rellenar el formulario ─────────────────────────────────────────────────
  await page.getByLabel('Título').fill(title)
  await page.getByLabel('Contenido').fill(content)

  // El checkbox de canal está visualmente oculto; se activa clicando la <label>
  await page.locator('label').filter({ hasText: 'Email' }).click()

  // ── Enviar ─────────────────────────────────────────────────────────────────
  await page.getByRole('button', { name: 'Enviar y Procesar' }).click()

  // El botón debe quedar deshabilitado mientras la IA procesa
  await expect(page.getByRole('button', { name: /Procesando con IA/ })).toBeVisible()

  // ── Esperar confirmación (IA real puede tardar varios segundos) ────────────
  await expect(
    page.getByText('Mensaje procesado exitosamente'),
  ).toBeVisible({ timeout: 60_000 })

  // ── Verificar que el mensaje aparece en el historial ──────────────────────
  // useSendMessage invalida ['messages'] al completar → HistoryDashboard refetch
  await expect(page.getByRole('cell', { name: title })).toBeVisible({
    timeout: 10_000,
  })
})
