/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { creditNotesApi } from '../services/creditNotesApi'

export const useCreditNotesStore = defineStore('creditNotes', {
  state: () => ({
    items: [],
    meta: {
      page: 1,
      perPage: 15,
      total: 0,
      lastPage: 1,
    },
    summary: {
      total_count: 0,
      issued_count: 0,
      cancelled_count: 0,
      totals_by_currency: [],
    },
    currentNote: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchCreditNotes(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await creditNotesApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar notas de crédito'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchSummary() {
      try {
        const response = await creditNotesApi.summary()

        this.summary = response?.data || {
          total_count: 0,
          issued_count: 0,
          cancelled_count: 0,
          totals_by_currency: [],
        }

        return this.summary
      }
      catch (err) {
        console.error('Error al cargar resumen de notas de crédito:', err)
      }
    },

    async fetchCreditNote(id) {
      this.isLoading = true
      this.error = null

      try {
        const response = await creditNotesApi.get(id)

        this.currentNote = response?.data || null

        return this.currentNote
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar nota de crédito'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})
