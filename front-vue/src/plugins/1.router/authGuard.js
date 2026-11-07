import { getCsrfCookie } from '@/utils/csrfCookie'
import { getAuthStorage } from '@/utils/sessionStorage'
import { useAuthStore } from '@/modules/auth/stores/authStore'

/**
 * Factory del Navigation Guard para autenticación y autorización.
 * Valida sesión activa, silent refresh ante recarga (F5) y control de acceso.
 * Permite inyección de dependencias para tests.
 */
export const createAuthGuard = (dependencies = {}) => {
  const getStorage = dependencies.getStorage || getAuthStorage
  const readCsrfCookie = dependencies.getCsrfCookie || getCsrfCookie

  return async (to, from, next) => {
    let authStore = null
    try {
      if (dependencies.authStore) {
        authStore = dependencies.authStore
      }
      else if (dependencies.getAuthStore) {
        authStore = dependencies.getAuthStore()
      }
      else {
        authStore = useAuthStore()
      }
    }
    catch {
      // Pinia no disponible en este ciclo
    }

    const storage = getStorage ? getStorage() : null
    let accessToken = authStore?.accessToken || null
    const isPublicRoute = Boolean(to.meta?.public)
    const isLoginPage = to.path === '/login'

    // 1. Si el usuario ya cuenta con token de acceso en memoria
    if (accessToken) {
      if (isLoginPage) {
        return next({ path: '/' })
      }

      // Validación de permisos requeridos por la ruta (to.meta.action: un permiso o una lista, basta uno)
      const requiredActions = [].concat(to.meta?.action || [])
      if (requiredActions.length) {
        const userRoles = authStore?.roles || []
        const userPerms = authStore?.permissions || []

        const isSuperAdmin = userRoles.some(r => {
          const norm = String(r || '').toLowerCase().replace(/[\s_-]+/g, '')
          
          return norm === 'superadmin' || norm === 'admin'
        })

        const hasAction = requiredAction => userPerms.includes(requiredAction)
          || (requiredAction === 'clients.view' && userPerms.includes('clients.read'))
          || (requiredAction === 'clients.read' && userPerms.includes('clients.view'))
          || (requiredAction === 'users.view' && userPerms.includes('users.read'))
          || (requiredAction === 'users.read' && userPerms.includes('users.view'))

        const hasPermission = isSuperAdmin
          || userPerms.includes('all')
          || userPerms.includes('*')
          || requiredActions.some(hasAction)

        if (!hasPermission) {
          return next({ path: '/' })
        }
      }

      return next()
    }

    // 2. Si no hay accessToken en memoria pero existe sesión persistida (ej: recarga de página F5)
    // o pestaña nueva sin sessionStorage pero con la cookie de sesión del navegador
    const hasPersistedSession = Boolean(storage && storage.user) || Boolean(readCsrfCookie())
    if (hasPersistedSession) {
      try {
        let refreshSuccess = false
        if (authStore?.initAuth) {
          refreshSuccess = await authStore.initAuth()
        }
        else if (authStore?.refreshSession) {
          const res = await authStore.refreshSession()
          const data = res?.data || res

          refreshSuccess = Boolean(data?.access_token || authStore.accessToken)
        }

        if (refreshSuccess) {
          if (isLoginPage) {
            return next({ path: '/' })
          }

          return next()
        }
      }
      catch {
        // Falló la renovación silenciosa
      }
    }

    // 3. Si no hay sesión válida o fue rechazada
    if (isPublicRoute) {
      return next()
    }

    return next({ path: '/login' })
  }
}

export const authGuard = createAuthGuard()

export default authGuard
