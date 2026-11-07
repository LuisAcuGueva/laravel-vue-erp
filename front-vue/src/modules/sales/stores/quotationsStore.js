import { defineStore } from 'pinia'
import { quotationsApi } from '../services/quotationsApi'

export const useQuotationsStore = defineStore('quotations', {
  state: () => ({
    items: [],
    meta: {
      page: 1,
      perPage: 15,
      total: 0,
      lastPage: 1,
    },
    totalsByStatus: {
      draft: { count: 0, total: 0 },
      sent: { count: 0, total: 0 },
      accepted: { count: 0, total: 0 },
      rejected: { count: 0, total: 0 },
      expired: { count: 0, total: 0 },
      converted: { count: 0, total: 0 },
      cancelled: { count: 0, total: 0 },
    },
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchQuotations(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await quotationsApi.list(params)

        this.items = response?.data || []
        this.meta = {
          page: response?.meta?.current_page || 1,
          perPage: response?.meta?.per_page || 15,
          total: response?.meta?.total ?? this.items.length,
          lastPage: response?.meta?.last_page || 1,
        }

        if (response?.meta?.totals_by_status) {
          this.totalsByStatus = response.meta.totals_by_status
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar cotizaciones'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useQuotationsStore
