/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { salesApi } from '../services/salesApi'

export const useSalesStore = defineStore('sales', {
  state: () => ({
    items: [],
    meta: {
      page: 1,
      perPage: 15,
      total: 0,
      lastPage: 1,
    },
    totals: {
      total_amount: 0,
      paid_amount: 0,
      balance: 0,
    },
    currentSale: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchSales(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await salesApi.list(params)

        this.items = response?.data || []
        this.meta = {
          page: response?.meta?.current_page || 1,
          perPage: response?.meta?.per_page || 15,
          total: response?.meta?.total ?? this.items.length,
          lastPage: response?.meta?.last_page || 1,
        }

        if (response?.meta?.totals) {
          this.totals = response.meta.totals
        }

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar ventas'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchSale(id) {
      this.isLoading = true
      this.error = null

      try {
        const response = await salesApi.get(id)

        this.currentSale = response?.data || null

        return this.currentSale
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar venta'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useSalesStore
