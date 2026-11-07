import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { $api } from '@/utils/api'
import { useAuthStore } from '@/modules/auth/stores/authStore'

describe('$api HTTP client utility', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      headers: new Headers({ 'content-type': 'application/json' }),
      text: async () => JSON.stringify({ success: true, data: [] }),
      json: async () => ({ success: true, data: [] }),
    })
  })

  it('inyecta Authorization Bearer cuando existe accessToken en authStore', async () => {
    const authStore = useAuthStore()

    authStore.accessToken = 'test-bearer-token'

    await $api('/users')

    expect(global.fetch).toHaveBeenCalled()

    const callArgs = vi.mocked(global.fetch).mock.calls[0]
    const requestOptions = callArgs[1]
    const headers = requestOptions.headers

    const authHeader = headers instanceof Headers ? headers.get('Authorization') : headers['Authorization']

    expect(authHeader).toBe('Bearer test-bearer-token')
  })

  it('inyecta X-CSRF-TOKEN en endpoints de sesión o cuando hay token activo', async () => {
    const authStore = useAuthStore()

    authStore.csrfToken = 'csrf-token-12345'
    authStore.accessToken = 'test-bearer-token'

    await $api('/auth/refresh')

    expect(global.fetch).toHaveBeenCalled()

    const callArgs = vi.mocked(global.fetch).mock.calls[0]
    const requestOptions = callArgs[1]
    const headers = requestOptions.headers

    const csrfHeader = headers instanceof Headers ? headers.get('X-CSRF-TOKEN') : headers['X-CSRF-TOKEN']

    expect(csrfHeader).toBe('csrf-token-12345')
  })

  it('no inyecta cabecera Authorization si no hay sesión activa', async () => {
    const authStore = useAuthStore()

    authStore.accessToken = null

    await $api('/public-data')

    expect(global.fetch).toHaveBeenCalled()

    const callArgs = vi.mocked(global.fetch).mock.calls[0]
    const requestOptions = callArgs[1]
    const headers = requestOptions?.headers || {}

    const authHeader = headers instanceof Headers ? headers.get('Authorization') : headers['Authorization']

    expect(authHeader).toBeFalsy()
  })
})
