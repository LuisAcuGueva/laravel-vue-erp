import { defineStore } from 'pinia'
import { permissionsApi } from '../services/permissionsApi'

export const usePermissionsStore = defineStore('permissions', {
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

  getters: {
    totalPermissions: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchPermissions(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await permissionsApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar los permisos'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createPermission(payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await permissionsApi.create(payload)
        const newPermission = response?.data || response

        this.items.unshift(newPermission)
        this.meta.total += 1

        return newPermission
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al registrar el permiso'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updatePermission(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await permissionsApi.update(id, payload)
        const updated = response?.data || response

        const index = this.items.findIndex(item => item.id === id)
        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar el permiso'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deletePermission(id) {
      this.isLoading = true
      this.error = null

      try {
        await permissionsApi.delete(id)

        this.items = this.items.filter(item => item.id !== id)
        this.meta.total = Math.max(0, this.meta.total - 1)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar el permiso'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    $reset() {
      this.items = []
      this.meta = {
        page: 1,
        perPage: 15,
        total: 0,
        lastPage: 1,
      }
      this.isLoading = false
      this.error = null
    },
  },
})

export default usePermissionsStore
