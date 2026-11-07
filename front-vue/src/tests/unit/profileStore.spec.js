import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useProfileStore } from '@/modules/profile/stores/profileStore'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { profileApi } from '@/modules/profile/services/profileApi'

vi.mock('@/modules/profile/services/profileApi', () => ({
  profileApi: {
    getProfile: vi.fn(),
    updateProfile: vi.fn(),
    updatePassword: vi.fn(),
    listSessions: vi.fn(),
    revokeOtherSessions: vi.fn(),
  },
}))

describe('profileStore Pinia store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    window.sessionStorage.clear()
  })

  it('inicia con estado inicial por defecto', () => {
    const store = useProfileStore()

    expect(store.profile).toBeNull()
    expect(store.sessions).toEqual([])
    expect(store.activeSessionsCount).toBe(0)
    expect(store.otherSessionsCount).toBe(0)
    expect(store.isLoadingProfile).toBe(false)
  })

  it('fetchProfile obtiene y guarda los datos del perfil', async () => {
    const mockProfile = { id: 'uuid-123', name: 'Usuario Prueba', email: 'prueba@test.com' }

    profileApi.getProfile.mockResolvedValueOnce({ data: mockProfile })

    const store = useProfileStore()
    const result = await store.fetchProfile()

    expect(result).toEqual(mockProfile)
    expect(store.profile).toEqual(mockProfile)
    expect(profileApi.getProfile).toHaveBeenCalledTimes(1)
  })

  it('updateProfile actualiza el perfil y sincroniza con authStore', async () => {
    const authStore = useAuthStore()

    authStore.user = { id: 'uuid-123', name: 'Nombre Antiguo', email: 'antiguo@test.com' }

    const updatedProfile = { id: 'uuid-123', name: 'Nombre Nuevo', email: 'nuevo@test.com' }

    profileApi.updateProfile.mockResolvedValueOnce({ data: updatedProfile })

    const profileStore = useProfileStore()
    const result = await profileStore.updateProfile({ name: 'Nombre Nuevo', email: 'nuevo@test.com' })

    expect(result).toEqual(updatedProfile)
    expect(profileStore.profile).toEqual(updatedProfile)
    expect(authStore.user.name).toBe('Nombre Nuevo')
    expect(authStore.user.email).toBe('nuevo@test.com')
  })

  it('updatePassword actualiza los tokens en authStore si vienen en la respuesta', async () => {
    const authStore = useAuthStore()

    profileApi.updatePassword.mockResolvedValueOnce({
      success: true,
      data: {
        'access_token': 'new-jwt-access-token',
        'csrf_token': 'new-csrf-token',
      },
    })

    const profileStore = useProfileStore()

    await profileStore.updatePassword({
      'current_password': 'clave',
      'password': 'nueva',
      'password_confirmation': 'nueva',
    })

    expect(authStore.accessToken).toBe('new-jwt-access-token')
    expect(authStore.csrfToken).toBe('new-csrf-token')
  })

  it('fetchSessions lista las sesiones y calcula sesiones activas y remotas', async () => {
    const mockSessions = [
      { id: '1', 'ip_address': '127.0.0.1', 'is_current': true },
      { id: '2', 'ip_address': '192.168.1.50', 'is_current': false },
      { id: '3', 'ip_address': '10.0.0.1', 'is_current': false },
    ]

    profileApi.listSessions.mockResolvedValueOnce({ data: mockSessions })

    const store = useProfileStore()

    await store.fetchSessions()

    expect(store.sessions).toHaveLength(3)
    expect(store.activeSessionsCount).toBe(3)
    expect(store.otherSessionsCount).toBe(2)
  })

  it('revokeOtherSessions revoca sesiones remotas y mantiene solo la actual', async () => {
    const store = useProfileStore()

    store.sessions = [
      { id: '1', 'ip_address': '127.0.0.1', 'is_current': true },
      { id: '2', 'ip_address': '192.168.1.50', 'is_current': false },
    ]

    profileApi.revokeOtherSessions.mockResolvedValueOnce({
      success: true,
      data: { 'revoked_sessions_count': 1 },
    })

    await store.revokeOtherSessions()

    expect(store.sessions).toHaveLength(1)
    expect(store.sessions[0]['is_current']).toBe(true)
    expect(store.otherSessionsCount).toBe(0)
  })
})
