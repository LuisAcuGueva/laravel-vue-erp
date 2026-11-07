import { defineStore } from 'pinia'
import { providersApi } from '../services/providersApi'

export const useProvidersStore = defineStore('providers', {
  state: () => ({
    items: [],
    meta: {
      page: 1,
      perPage: 15,
      total: 0,
      lastPage: 1,
    },
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchProviders(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await providersApi.list(params)

        this.items = response?.data || []
        this.meta = {
          page: response?.meta?.current_page || 1,
          perPage: response?.meta?.per_page || 15,
          total: response?.meta?.total ?? this.items.length,
          lastPage: response?.meta?.last_page || 1,
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar los proveedores'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createProvider(payload) {
      const response = await providersApi.create(payload)

      return response?.data || response
    },

    async updateProvider(id, payload) {
      const response = await providersApi.update(id, payload)

      return response?.data || response
    },

    async deleteProvider(id) {
      await providersApi.delete(id)
    },
  },
})

export default useProvidersStore
