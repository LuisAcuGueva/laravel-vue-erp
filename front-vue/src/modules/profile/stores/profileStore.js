import { defineStore } from 'pinia'
import { profileApi } from '../services/profileApi'
import { useAuthStore } from '@/modules/auth/stores/authStore'

export const useProfileStore = defineStore('profile', {
  state: () => ({
    profile: null,
    sessions: [],
    isLoadingProfile: false,
    isLoadingSessions: false,
    isSubmittingProfile: false,
    isSubmittingPassword: false,
    isRevokingSessions: false,
    error: null,
  }),

  getters: {
    activeSessionsCount: state => state.sessions.length,
    otherSessionsCount: state => state.sessions.filter(s => !s.is_current).length,
  },

  actions: {
    async fetchProfile() {
      this.isLoadingProfile = true
      this.error = null

      try {
        const res = await profileApi.getProfile()
        const data = res?.data || res

        this.profile = data

        return data
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar perfil'
        throw err
      }
      finally {
        this.isLoadingProfile = false
      }
    },

    async updateProfile(payload) {
      this.isSubmittingProfile = true
      this.error = null

      try {
        const res = await profileApi.updateProfile(payload)
        const updated = res?.data || res

        this.profile = updated

        // Sincronizar datos básicos con el store global de autenticación
        const authStore = useAuthStore()

        if (authStore.updateUser) {
          authStore.updateUser(updated)
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar perfil'
        throw err
      }
      finally {
        this.isSubmittingProfile = false
      }
    },

    async updatePassword(payload) {
      this.isSubmittingPassword = true
      this.error = null

      try {
        const res = await profileApi.updatePassword(payload)
        const data = res?.data || res

        // Actualizar tokens en memoria si vienen en la respuesta
        if (data?.access_token) {
          const authStore = useAuthStore()

          authStore.updateTokens(data.access_token, data.csrf_token || null)
        }

        return res
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar contraseña'
        throw err
      }
      finally {
        this.isSubmittingPassword = false
      }
    },

    async fetchSessions() {
      this.isLoadingSessions = true
      this.error = null

      try {
        const res = await profileApi.listSessions()
        const data = res?.data || []

        this.sessions = Array.isArray(data) ? data : []

        return this.sessions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar sesiones activas'
        throw err
      }
      finally {
        this.isLoadingSessions = false
      }
    },

    async revokeOtherSessions() {
      this.isRevokingSessions = true
      this.error = null

      try {
        const res = await profileApi.revokeOtherSessions()

        // Filtrar localmente manteniendo únicamente la sesión actual
        this.sessions = this.sessions.filter(session => session.is_current)

        return res
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al revocar otras sesiones'
        throw err
      }
      finally {
        this.isRevokingSessions = false
      }
    },
  },
})

export default useProfileStore
