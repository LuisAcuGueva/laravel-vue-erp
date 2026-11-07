import { createFetch } from '@vueuse/core'
import { destr } from 'destr'

export const useApi = createFetch({
  baseUrl: import.meta.env.VITE_API_BASE_URL || '/api',
  fetchOptions: {
    headers: {
      Accept: 'application/json',
    },
    credentials: 'include',
  },
  options: {
    refetch: true,
    async beforeFetch({ options }) {
      let accessToken = null
      try {
        const { useAuthStore } = await import('@/modules/auth/stores/authStore')

        accessToken = useAuthStore().accessToken
      }
      catch {
        // Pinia no disponible
      }

      const headers = { ...options.headers }

      if (accessToken) {
        headers['Authorization'] = `Bearer ${accessToken}`
      }

      options.headers = headers
      options.credentials = 'include'

      return { options }
    },
    afterFetch(ctx) {
      const { data, response } = ctx

      let parsedData = null
      try {
        parsedData = destr(data)
      }
      catch (error) {
        console.error(error)
      }

      return { data: parsedData, response }
    },
  },
})
