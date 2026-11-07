/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { stockApi } from '../services/stockApi'
import { warehousesApi } from '../services/inventorySettingsApi'

export const useStockStore = defineStore('stock', {
  state: () => ({
    totalValue: 0,
    warehouses: [],
    kardexData: {
      opening_balance: { quantity: 0, unit_cost: 0, total: 0 },
      movements: [],
      closing_balance: { quantity: 0, unit_cost: 0, total: 0 },
    },
    isLoading: false,
    error: null,
  }),

  actions: {
    async loadWarehouses() {
      try {
        const response = await warehousesApi.options()

        this.warehouses = response?.data || []

        return this.warehouses
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar almacenes'
        throw err
      }
    },

    async updateLimits(payload) {
      this.isLoading = true
      this.error = null
      try {
        const response = await stockApi.updateLimits(payload)

        return response?.data
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar límites de stock'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchKardex(params) {
      this.isLoading = true
      this.error = null
      try {
        const response = await stockApi.kardex(params)

        this.kardexData = response?.data || {
          opening_balance: { quantity: 0, unit_cost: 0, total: 0 },
          movements: [],
          closing_balance: { quantity: 0, unit_cost: 0, total: 0 },
        }

        return this.kardexData
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al consultar kardex'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async exportKardex(params) {
      return stockApi.exportKardex(params)
    },
  },
})

export default useStockStore
