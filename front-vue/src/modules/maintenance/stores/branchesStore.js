import { defineStore } from 'pinia'
import { branchesApi } from '../services/branchesApi'

export const useBranchesStore = defineStore('branches', {
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
    totalBranches: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchBranches(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await branchesApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar las sedes'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchOptions() {
      try {
        const response = await branchesApi.options()

        this.options = response?.data || []

        return this.options
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de sedes'
        throw err
      }
    },

    async createBranch(payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await branchesApi.create(payload)
        const newBranch = response?.data || response

        this.items.unshift(newBranch)
        this.meta.total += 1

        return newBranch
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear la sede'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateBranch(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await branchesApi.update(id, payload)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar la sede'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deleteBranch(id) {
      this.isLoading = true
      this.error = null

      try {
        await branchesApi.delete(id)
        this.items = this.items.filter(item => item.id !== id)
        this.meta.total = Math.max(0, this.meta.total - 1)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar la sede'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useBranchesStore
