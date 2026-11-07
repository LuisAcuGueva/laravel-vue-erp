import { defineStore } from 'pinia'
import { supplierInvoicesApi } from '../services/supplierInvoicesApi'

export const useSupplierInvoicesStore = defineStore('supplierInvoices', {
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
    async fetchInvoices(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await supplierInvoicesApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar las facturas de proveedor'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useSupplierInvoicesStore
