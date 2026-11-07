import { defineStore } from 'pinia'
import { broadcastLogout, withRefreshLock } from '@/utils/authSync'
import { getCsrfCookie } from '@/utils/csrfCookie'
import { clearAuthStorage, getAuthStorage, setAuthStorage } from '@/utils/sessionStorage'
import { authApi } from '../services/authApi'

export function resolveInitialBranchId(branches = []) {
  if (!Array.isArray(branches) || branches.length === 0)
    return null

  // 1. sessionStorage
  if (typeof window !== 'undefined' && window.sessionStorage) {
    const sessionBranchId = window.sessionStorage.getItem('active_branch_id')
    if (sessionBranchId && branches.some(b => b.id === sessionBranchId))
      return sessionBranchId
  }

  // 2. localStorage
  if (typeof window !== 'undefined' && window.localStorage) {
    const localBranchId = window.localStorage.getItem('last_branch_id')
    if (localBranchId && branches.some(b => b.id === localBranchId)) {
      if (window.sessionStorage)
        window.sessionStorage.setItem('active_branch_id', localBranchId)

      return localBranchId
    }
  }

  // 3. Default or first
  const defaultBranch = branches.find(b => b.is_default) || branches[0]
  const branchId = defaultBranch ? defaultBranch.id : null

  if (branchId && typeof window !== 'undefined') {
    if (window.sessionStorage)
      window.sessionStorage.setItem('active_branch_id', branchId)
    if (window.localStorage)
      window.localStorage.setItem('last_branch_id', branchId)
  }

  return branchId
}

export const useAuthStore = defineStore('auth', {
  state: () => {
    const persisted = getAuthStorage()
    const branches = persisted?.branches || []

    return {
      user: persisted?.user || null,
      roles: persisted?.roles || [],
      permissions: persisted?.permissions || [],
      branches,
      activeBranchId: resolveInitialBranchId(branches),
      csrfToken: persisted?.csrfToken || null,
      accessToken: null, // Estrictamente efímero en memoria RAM
      isInitializing: false,
      _initPromise: null,
    }
  },

  getters: {
    isAuthenticated: state => Boolean(state.accessToken || state.user),
    userRoles: state => state.roles || [],
    userPermissions: state => state.permissions || [],
    userBranches: state => state.branches || [],
    activeBranch: state => (state.branches || []).find(b => b.id === state.activeBranchId) || null,
    isSuperAdmin: state => {
      const targetRoles = state.roles || []

      return targetRoles.some(r => {
        const norm = String(r || '').toLowerCase().replace(/[\s_-]+/g, '')

        return norm === 'superadmin' || norm === 'admin'
      })
    },
  },

  actions: {
    async login(credentials) {
      const res = await authApi.login(credentials)
      const data = res?.data || res

      this.accessToken = data.access_token
      this.csrfToken = data.csrf_token || null
      this.user = data.user
      this.roles = data.roles || []
      this.permissions = data.permissions || []
      this.branches = data.branches || []
      this.activeBranchId = resolveInitialBranchId(this.branches)

      setAuthStorage({
        user: this.user,
        roles: this.roles,
        permissions: this.permissions,
        branches: this.branches,
        csrfToken: this.csrfToken,
      })

      try {
        const { useConfigurationStore } = await import('@/modules/configuration/stores/configurationStore')
        const configStore = useConfigurationStore()

        await configStore.fetchSummary()
      }
      catch {
        // Fallback silencioso si el resumen no está disponible
      }

      return res
    },

    async refreshSession() {
      // El CSRF se lee de la cookie dentro del lock: otra pestaña pudo haber rotado la sesión
      const res = await withRefreshLock(() => authApi.refreshToken())
      const data = res?.data || res

      if (data?.access_token) {
        this.accessToken = data.access_token
      }

      if (data?.csrf_token) {
        this.csrfToken = data.csrf_token
        setAuthStorage({ csrfToken: data.csrf_token })
      }

      return res
    },

    updateTokens(accessToken, csrfToken = null) {
      if (accessToken) {
        this.accessToken = accessToken
      }

      if (csrfToken) {
        this.csrfToken = csrfToken
        setAuthStorage({ csrfToken })
      }
    },

    updateUser(userData) {
      if (userData) {
        this.user = { ...(this.user || {}), ...userData }
        setAuthStorage({ user: this.user })
      }
    },

    async initAuth() {
      if (this.accessToken) {
        return true
      }

      const storage = getAuthStorage()
      if (!storage || !storage.user) {
        // Pestaña nueva (sin sessionStorage): la cookie csrf_token indica que hay sesión de navegador
        return getCsrfCookie() ? this.restoreSessionFromCookie() : false
      }

      if (this.isInitializing && this._initPromise) {
        return this._initPromise
      }

      this.isInitializing = true
      this._initPromise = (async () => {
        try {
          this.user = storage.user
          this.roles = storage.roles || []
          this.permissions = storage.permissions || []
          this.branches = storage.branches || []
          this.activeBranchId = resolveInitialBranchId(this.branches)
          this.csrfToken = storage.csrfToken || null

          const res = await this.refreshSession()
          const data = res?.data || res

          if (data?.access_token) {
            return true
          }

          await this.logout()

          return false
        }
        catch {
          await this.logout()

          return false
        }
        finally {
          this.isInitializing = false
          this._initPromise = null
        }
      })()

      return this._initPromise
    },

    async restoreSessionFromCookie() {
      if (this.isInitializing && this._initPromise)
        return this._initPromise

      this.isInitializing = true
      this._initPromise = (async () => {
        try {
          const res = await this.refreshSession()
          const data = res?.data || res

          if (!data?.access_token) {
            await this.logout({ broadcast: false })

            return false
          }

          await this.fetchUserProfile()

          return Boolean(this.user)
        }
        catch {
          await this.logout({ broadcast: false })

          return false
        }
        finally {
          this.isInitializing = false
          this._initPromise = null
        }
      })()

      return this._initPromise
    },

    async logout({ broadcast = true } = {}) {
      if (broadcast)
        broadcastLogout()

      try {
        await authApi.logout()
      }
      catch {
        // Ignorar fallo en logout remoto para garantizar limpieza local
      }

      this.user = null
      this.accessToken = null
      this.roles = []
      this.permissions = []
      this.branches = []
      this.activeBranchId = null
      this.csrfToken = null

      clearAuthStorage()
      if (typeof window !== 'undefined' && window.sessionStorage) {
        window.sessionStorage.removeItem('active_branch_id')
      }
    },

    setActiveBranch(branchId) {
      if (!this.branches.some(b => b.id === branchId))
        return false

      this.activeBranchId = branchId
      if (typeof window !== 'undefined') {
        if (window.sessionStorage)
          window.sessionStorage.setItem('active_branch_id', branchId)
        if (window.localStorage)
          window.localStorage.setItem('last_branch_id', branchId)
      }

      return true
    },

    async fetchUserProfile() {
      if (!this.accessToken)
        return null

      try {
        const res = await authApi.getMe()
        const data = res?.data || res

        if (data?.user) {
          this.user = { ...this.user, ...data.user }
          this.roles = data.roles || this.roles
          this.permissions = data.permissions || this.permissions
          if (Array.isArray(data.branches)) {
            this.branches = data.branches
            this.activeBranchId = resolveInitialBranchId(this.branches)
          }

          setAuthStorage({
            user: this.user,
            roles: this.roles,
            permissions: this.permissions,
            branches: this.branches,
          })

          try {
            const { useConfigurationStore } = await import('@/modules/configuration/stores/configurationStore')
            const configStore = useConfigurationStore()

            await configStore.fetchSummary()
          }
          catch {
            // Silencioso
          }
        }

        return this.user
      }
      catch (error) {
        if (error?.status === 401 || error?.status === 403) {
          await this.logout()
        }
        throw error
      }
    },
  },
})

export default useAuthStore
