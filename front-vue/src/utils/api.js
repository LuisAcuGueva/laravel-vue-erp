import { ofetch } from 'ofetch'
import { getCsrfCookie } from '@/utils/csrfCookie'
import { getAuthStorage } from '@/utils/sessionStorage'
import { useAuthStore } from '@/modules/auth/stores/authStore'

let isRefreshing = false
let refreshPromise = null

async function handleTokenRefresh() {
  if (!isRefreshing) {
    isRefreshing = true
    refreshPromise = (async () => {
      try {
        const authStore = useAuthStore()
        const data = await authStore.refreshSession()

        return data?.access_token || authStore.accessToken
      }
      catch (err) {
        try {
          const authStore = useAuthStore()

          await authStore.logout()
        }
        catch {
          // Ignorar error al limpiar
        }

        if (typeof window !== 'undefined' && window.location && window.location.pathname !== '/login') {
          window.location.href = '/login'
        }

        throw err
      }
      finally {
        isRefreshing = false
        refreshPromise = null
      }
    })()
  }

  return refreshPromise
}

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  credentials: 'include',
  async onRequest({ request, options }) {
    options.credentials = 'include'

    let accessToken = null
    let csrfToken = null
    let activeBranchId = null

    try {
      const authStore = useAuthStore()

      accessToken = authStore.accessToken
      csrfToken = getCsrfCookie() || authStore.csrfToken
      activeBranchId = authStore.activeBranchId
    }
    catch {
      // Pinia no inicializado
    }

    if (!activeBranchId && typeof window !== 'undefined' && window.sessionStorage) {
      activeBranchId = window.sessionStorage.getItem('active_branch_id')
    }

    if (!csrfToken) {
      csrfToken = getCsrfCookie()
    }

    if (!csrfToken) {
      const stored = getAuthStorage()

      csrfToken = stored?.csrfToken || null
    }

    options.headers = options.headers || {}

    // Inyección de X-Branch-Id si existe sede activa
    if (activeBranchId) {
      if (options.headers instanceof Headers) {
        if (!options.headers.has('X-Branch-Id'))
          options.headers.set('X-Branch-Id', activeBranchId)
      }
      else if (!options.headers['X-Branch-Id'] && !options.headers['x-branch-id']) {
        options.headers['X-Branch-Id'] = activeBranchId
      }
    }

    // Inyección de X-CSRF-TOKEN en endpoints de sesión o peticiones con CSRF
    const urlStr = typeof request === 'string' ? request : (request?.url || '')

    const isSessionEndpoint =
      urlStr.includes('/auth/refresh') ||
      urlStr.includes('/auth/logout')

    const shouldSendCsrf = (isSessionEndpoint || Boolean(accessToken)) && Boolean(csrfToken)

    if (shouldSendCsrf) {
      if (options.headers instanceof Headers) {
        if (!options.headers.has('X-CSRF-TOKEN'))
          options.headers.set('X-CSRF-TOKEN', csrfToken)
      }
      else if (!options.headers['X-CSRF-TOKEN']) {
        options.headers['X-CSRF-TOKEN'] = csrfToken
      }
    }

    // Cabecera de autenticación Bearer
    if (accessToken) {
      if (options.headers instanceof Headers) {
        options.headers.set('Authorization', `Bearer ${accessToken}`)
      }
      else {
        options.headers['Authorization'] = `Bearer ${accessToken}`
      }
    }
  },

  async onResponse({ response }) {
    if (!response || !response.headers)
      return

    let newAccessToken = null
    let newCsrfToken = null

    if (typeof response.headers.get === 'function') {
      newAccessToken = response.headers.get('x-new-access-token') || response.headers.get('X-New-Access-Token')
      newCsrfToken = response.headers.get('x-new-csrf-token') || response.headers.get('X-New-CSRF-Token')
    }
    else if (typeof response.headers === 'object') {
      newAccessToken = response.headers['x-new-access-token'] || response.headers['X-New-Access-Token']
      newCsrfToken = response.headers['x-new-csrf-token'] || response.headers['X-New-CSRF-Token']
    }

    if (newAccessToken) {
      try {
        const authStore = useAuthStore()

        authStore.updateTokens(newAccessToken, newCsrfToken)
      }
      catch {
        // Pinia no inicializado
      }
    }
  },

  async onResponseError(context) {
    const status = context.response?.status
    const url = typeof context.request === 'string' ? context.request : (context.request?.url || '')

    const isAuthEndpoint =
      url.includes('/auth/login') ||
      url.includes('/auth/refresh') ||
      url.includes('/auth/logout')

    if (status === 401 && !isAuthEndpoint) {
      try {
        const newAccessToken = await handleTokenRefresh()

        if (newAccessToken) {
          if (context.options.headers instanceof Headers) {
            context.options.headers.set('Authorization', `Bearer ${newAccessToken}`)
          }
          else if (context.options.headers) {
            context.options.headers['Authorization'] = `Bearer ${newAccessToken}`
          }
        }

        context.options.retry = 1
        context.options.retryStatusCodes = [401]
      }
      catch {
        // Dejar propagar error si el refresco falla
      }
    }
  },
})

export default $api
