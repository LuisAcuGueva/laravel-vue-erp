/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { inventoryCountsApi } from '../services/inventoryCountsApi'

export const useInventoryCountsStore = defineStore('inventoryCounts', {
  state: () => ({
    currentCount: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchCount(id) {
      this.isLoading = true
      this.error = null
      try {
        const response = await inventoryCountsApi.get(id)

        this.currentCount = response?.data || null

        return this.currentCount
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar toma de inventario'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createCount(payload) {
      this.isLoading = true
      this.error = null
      try {
        return await inventoryCountsApi.create(payload)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al iniciar toma de inventario'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateItems(id, items) {
      this.isLoading = true
      this.error = null
      try {
        const response = await inventoryCountsApi.updateItems(id, items)
        if (response?.data) {
          this.currentCount = response.data
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar líneas de conteo'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async closeCount(id, payload = {}) {
      this.isLoading = true
      this.error = null
      try {
        const response = await inventoryCountsApi.close(id, payload)
        if (response?.data) {
          this.currentCount = response.data
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cerrar toma de inventario'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async cancelCount(id) {
      this.isLoading = true
      this.error = null
      try {
        const response = await inventoryCountsApi.cancel(id)
        if (response?.data) {
          this.currentCount = response.data
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al anular toma de inventario'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useInventoryCountsStore
