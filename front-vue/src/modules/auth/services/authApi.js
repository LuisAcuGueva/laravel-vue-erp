import { $api } from '@/utils/api'
import { getCsrfCookie } from '@/utils/csrfCookie'
import { getAuthStorage } from '@/utils/sessionStorage'

function getCsrfToken() {
  // La cookie es la fuente de verdad (compartida entre pestañas); sessionStorage es respaldo
  return getCsrfCookie() || getAuthStorage()?.csrfToken || null
}

export const authApi = {
  login(credentials) {
    return $api('/auth/login', {
      method: 'POST',
      body: credentials,
      credentials: 'include',
    })
  },

  refreshToken() {
    const csrfToken = getCsrfToken()
    const headers = {}

    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken
    }

    return $api('/auth/refresh', {
      method: 'POST',
      credentials: 'include',
      ...(Object.keys(headers).length ? { headers } : {}),
    })
  },

  logout() {
    const csrfToken = getCsrfToken()
    const headers = {}

    if (csrfToken) {
      headers['X-CSRF-TOKEN'] = csrfToken
    }

    return $api('/auth/logout', {
      method: 'POST',
      credentials: 'include',
      ...(Object.keys(headers).length ? { headers } : {}),
    })
  },

  getMe() {
    return $api('/auth/me', {
      method: 'GET',
    })
  },
}

export default authApi
