export const AUTH_STORAGE_KEY = 'auth-storage'

/**
 * Recupera de forma segura el estado de autenticación consolidado desde sessionStorage.
 * @returns {{ user: any, roles: string[], permissions: string[], csrfToken: string|null } | null}
 */
export function getAuthStorage() {
  if (typeof window === 'undefined' || !window.sessionStorage)
    return null

  try {
    const raw = window.sessionStorage.getItem(AUTH_STORAGE_KEY)
    if (!raw)
      return null

    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object')
      return null

    return {
      user: parsed.user || null,
      roles: Array.isArray(parsed.roles) ? parsed.roles : [],
      permissions: Array.isArray(parsed.permissions) ? parsed.permissions : [],
      branches: Array.isArray(parsed.branches) ? parsed.branches : [],
      csrfToken: parsed.csrfToken || null,
    }
  }
  catch (error) {
    console.warn('[sessionStorage] Error al leer auth-storage:', error)

    return null
  }
}

/**
 * Persiste de forma segura el estado consolidado de autenticación en sessionStorage.
 * @param {Object} payload
 * @returns {Object|null} Objeto persistido o null en caso de falla.
 */
export function setAuthStorage(payload = {}) {
  if (typeof window === 'undefined' || !window.sessionStorage)
    return null

  try {
    const current = getAuthStorage() || {}

    const consolidated = {
      user: payload.user !== undefined ? payload.user : (current.user || null),
      roles: payload.roles !== undefined
        ? (Array.isArray(payload.roles) ? payload.roles : [])
        : (current.roles || []),
      permissions: payload.permissions !== undefined
        ? (Array.isArray(payload.permissions) ? payload.permissions : [])
        : (current.permissions || []),
      branches: payload.branches !== undefined
        ? (Array.isArray(payload.branches) ? payload.branches : [])
        : (current.branches || []),
      csrfToken: payload.csrfToken !== undefined ? payload.csrfToken : (current.csrfToken || null),
    }

    window.sessionStorage.setItem(AUTH_STORAGE_KEY, JSON.stringify(consolidated))

    return consolidated
  }
  catch (error) {
    console.error('[sessionStorage] Error al guardar auth-storage:', error)

    return null
  }
}

/**
 * Elimina la clave de almacenamiento consolidado en sessionStorage.
 */
export function clearAuthStorage() {
  if (typeof window === 'undefined' || !window.sessionStorage)
    return

  try {
    window.sessionStorage.removeItem(AUTH_STORAGE_KEY)
  }
  catch (error) {
    console.error('[sessionStorage] Error al limpiar auth-storage:', error)
  }
}
