/* eslint-disable camelcase */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { getAuthStorage, setAuthStorage } from '@/utils/sessionStorage'
import { authApi } from '@/modules/auth/services/authApi'

vi.mock('@/modules/auth/services/authApi', () => ({
  authApi: {
    login: vi.fn(),
    logout: vi.fn(),
    refreshToken: vi.fn(),
    getMe: vi.fn(),
  },
}))

describe('authStore Pinia store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    window.sessionStorage.clear()
  })

  it('inicia con estado vacío por defecto cuando no hay almacenamiento previo', () => {
    const store = useAuthStore()

    expect(store.user).toBeNull()
    expect(store.accessToken).toBeNull()
    expect(store.csrfToken).toBeNull()
    expect(store.roles).toEqual([])
    expect(store.permissions).toEqual([])
    expect(store.isAuthenticated).toBe(false)
  })

  it('recupera datos persistidos desde sessionStorage al instanciar', () => {
    setAuthStorage({
      user: { id: 'uuid-1', name: 'Admin Test' },
      roles: ['admin'],
      permissions: ['users.view'],
      csrfToken: 'csrf-123',
    })

    const store = useAuthStore()

    expect(store.user?.name).toBe('Admin Test')
    expect(store.roles).toEqual(['admin'])
    expect(store.csrfToken).toBe('csrf-123')
    expect(store.isSuperAdmin).toBe(true)
    expect(store.isAuthenticated).toBe(true)
  })

  it('updateTokens actualiza el accessToken en memoria y persiste el csrfToken', () => {
    const store = useAuthStore()

    store.updateTokens('jwt-access-token-xyz', 'csrf-abc-456')

    expect(store.accessToken).toBe('jwt-access-token-xyz')
    expect(store.csrfToken).toBe('csrf-abc-456')

    const persisted = getAuthStorage()

    expect(persisted?.csrfToken).toBe('csrf-abc-456')
  })

  it('login almacena tokens, usuario, roles y persiste en sessionStorage', async () => {
    const mockResponse = {
      data: {
        'access_token': 'fake-jwt-token',
        'csrf_token': 'fake-csrf-token',
        'user': { id: 'u-1', email: 'test@example.com', name: 'Tester' },
        'roles': ['editor'],
        'permissions': ['posts.create'],
      },
    }

    vi.mocked(authApi.login).mockResolvedValue(mockResponse)

    const store = useAuthStore()

    await store.login({ email: 'test@example.com', password: 'password' })

    expect(store.accessToken).toBe('fake-jwt-token')
    expect(store.csrfToken).toBe('fake-csrf-token')
    expect(store.user?.email).toBe('test@example.com')
    expect(store.roles).toEqual(['editor'])
    expect(store.permissions).toEqual(['posts.create'])
    expect(store.isAuthenticated).toBe(true)

    const persisted = getAuthStorage()

    expect(persisted?.user?.email).toBe('test@example.com')
    expect(persisted?.csrfToken).toBe('fake-csrf-token')
  })

  it('logout limpia el estado reactivo y borra sessionStorage', async () => {
    const store = useAuthStore()

    store.updateTokens('token-1', 'csrf-1')
    store.user = { id: 'u-1', name: 'User' }
    store.roles = ['user']

    vi.mocked(authApi.logout).mockResolvedValue({})

    await store.logout()

    expect(store.user).toBeNull()
    expect(store.accessToken).toBeNull()
    expect(store.csrfToken).toBeNull()
    expect(store.roles).toEqual([])
    expect(store.permissions).toEqual([])
    expect(store.isAuthenticated).toBe(false)
    expect(getAuthStorage()).toBeNull()
  })

  describe('restauración en pestaña nueva (sin sessionStorage)', () => {
    afterEach(() => {
      document.cookie = 'csrf_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
    })

    it('sin sessionStorage ni cookie no intenta refrescar', async () => {
      const store = useAuthStore()

      expect(await store.initAuth()).toBe(false)
      expect(authApi.refreshToken).not.toHaveBeenCalled()
    })

    it('con cookie csrf_token refresca y carga el perfil', async () => {
      document.cookie = 'csrf_token=csrf-cookie; path=/'
      authApi.refreshToken.mockResolvedValue({ access_token: 'jwt', csrf_token: 'csrf-new' })
      authApi.getMe.mockResolvedValue({
        user: { id: 'u1', name: 'Admin' },
        roles: ['admin'],
        permissions: ['users.view'],
        branches: [{ id: 'b1', is_default: true }],
      })

      const store = useAuthStore()

      expect(await store.initAuth()).toBe(true)
      expect(store.accessToken).toBe('jwt')
      expect(store.user?.name).toBe('Admin')
      expect(store.activeBranchId).toBe('b1')
      expect(getAuthStorage()?.user?.id).toBe('u1')
    })

    it('si el refresh falla limpia la sesión y devuelve false', async () => {
      document.cookie = 'csrf_token=csrf-cookie; path=/'
      authApi.refreshToken.mockRejectedValue(new Error('401'))
      authApi.logout.mockResolvedValue({})

      const store = useAuthStore()

      expect(await store.initAuth()).toBe(false)
      expect(store.accessToken).toBeNull()
    })
  })
})
