import { defineStore } from 'pinia'
import { areasApi } from '../services/areasApi'

export const useAreasStore = defineStore('areas', {
  state: () => ({
    items: [],
    options: [],
    meta: {
      page: 1,
      perPage: 15,
      total: 0,
      lastPage: 1,
    },
    isLoading: false,
    error: null,
  }),

  getters: {
    totalAreas: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchAreas(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await areasApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar las áreas'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchOptions() {
      try {
        const response = await areasApi.options()

        this.options = response?.data || []

        return this.options
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de áreas'
        throw err
      }
    },

    async createArea(payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await areasApi.create(payload)
        const newArea = response?.data || response

        this.items.unshift(newArea)
        this.meta.total += 1

        return newArea
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear el área'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateArea(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await areasApi.update(id, payload)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar el área'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deleteArea(id) {
      this.isLoading = true
      this.error = null

      try {
        await areasApi.delete(id)
        this.items = this.items.filter(item => item.id !== id)
        this.meta.total = Math.max(0, this.meta.total - 1)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar el área'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useAreasStore
