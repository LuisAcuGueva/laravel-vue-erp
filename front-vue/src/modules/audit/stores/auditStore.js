import { defineStore } from 'pinia'
import { auditApi } from '../services/auditApi'

export const useAuditStore = defineStore('audit', {
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
    activeLog: null,
    subjectTypes: [],
  }),

  getters: {
    totalLogs: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchAuditLogs(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await auditApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar registros de auditoría'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchSubjectTypes() {
      const response = await auditApi.subjectTypes()

      this.subjectTypes = response?.data || []

      return this.subjectTypes
    },

    async fetchAuditLog(id) {
      this.isLoading = true
      this.error = null

      try {
        const response = await auditApi.get(id)

        this.activeLog = response?.data || response

        return this.activeLog
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al obtener detalle del registro de auditoría'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useAuditStore
