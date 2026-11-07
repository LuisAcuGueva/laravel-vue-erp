import { defineStore } from 'pinia'
import { catalogApi } from '../services/catalogApi'

export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    currentItem: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchItem(id) {
      this.isLoading = true
      this.error = null
      try {
        const response = await catalogApi.get(id)

        this.currentItem = response?.data || null

        return this.currentItem
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar el ítem de catálogo'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createItem(payload) {
      this.isLoading = true
      this.error = null
      try {
        const response = await catalogApi.create(payload)

        return response?.data
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear el ítem de catálogo'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateItem(id, payload) {
      this.isLoading = true
      this.error = null
      try {
        const response = await catalogApi.update(id, payload)

        if (this.currentItem?.id === id) {
          this.currentItem = response?.data
        }

        return response?.data
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar el ítem de catálogo'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deleteItem(id) {
      this.isLoading = true
      this.error = null
      try {
        await catalogApi.delete(id)
        if (this.currentItem?.id === id) {
          this.currentItem = null
        }
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar el ítem de catálogo'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async syncPrices(id, prices) {
      this.isLoading = true
      this.error = null
      try {
        const response = await catalogApi.syncPrices(id, prices)

        // Refrescar el item actual si coincide
        if (this.currentItem?.id === id) {
          await this.fetchItem(id)
        }

        return response?.data
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al sincronizar precios'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})
