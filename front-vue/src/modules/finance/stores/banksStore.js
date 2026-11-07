import { defineStore } from 'pinia'
import { banksApi } from '../services/banksApi'

export const useBanksStore = defineStore('banks', {
  state: () => ({
    summary: [],
    accounts: [],
    selectedAccount: null,
    periods: [],
    movementsData: {
      opening: 0,
      closing: 0,
      movements: {
        data: [],
        total: 0,
        page: 1,
        'per_page': 15,
      },
    },
    isLoadingSummary: false,
    isLoadingAccounts: false,
    isLoadingAccount: false,
    isLoadingPeriods: false,
    isLoadingMovements: false,
    isSubmitting: false,
    error: null,
  }),

  getters: {
    openPeriod(state) {
      return state.periods.find(p => p.status === 'open') || null
    },
    hasOpenPeriod() {
      return Boolean(this.openPeriod)
    },
  },

  actions: {
    async fetchSummary() {
      this.isLoadingSummary = true
      try {
        const response = await banksApi.summary()

        this.summary = response?.data || []

        return this.summary
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar resumen bancario'
        throw err
      }
      finally {
        this.isLoadingSummary = false
      }
    },

    async fetchAccounts(params = {}) {
      this.isLoadingAccounts = true
      try {
        const response = await banksApi.listAccounts(params)

        this.accounts = response?.data || []

        return response
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar cuentas bancarias'
        throw err
      }
      finally {
        this.isLoadingAccounts = false
      }
    },

    async fetchAccount(id) {
      this.isLoadingAccount = true
      try {
        const response = await banksApi.getAccount(id)

        this.selectedAccount = response?.data || null

        return this.selectedAccount
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar cuenta bancaria'
        throw err
      }
      finally {
        this.isLoadingAccount = false
      }
    },

    async fetchPeriods(accountId) {
      this.isLoadingPeriods = true
      try {
        const response = await banksApi.listPeriods(accountId)

        this.periods = response?.data || []

        return this.periods
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar periodos'
        throw err
      }
      finally {
        this.isLoadingPeriods = false
      }
    },

    async fetchMovements(accountId, params = {}) {
      this.isLoadingMovements = true
      try {
        const response = await banksApi.listMovements(accountId, params)

        this.movementsData = response?.data || {
          opening: 0,
          closing: 0,
          movements: { data: [], total: 0, page: 1, 'per_page': 15 },
        }

        return this.movementsData
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar movimientos'
        throw err
      }
      finally {
        this.isLoadingMovements = false
      }
    },

    async openInitialPeriod(accountId, payload) {
      this.isSubmitting = true
      try {
        const response = await banksApi.openInitialPeriod(accountId, payload)

        await Promise.all([
          this.fetchAccount(accountId),
          this.fetchPeriods(accountId),
        ])

        return response
      }
      finally {
        this.isSubmitting = false
      }
    },

    async closePeriod(periodId, payload) {
      this.isSubmitting = true
      try {
        const response = await banksApi.closePeriod(periodId, payload)

        if (this.selectedAccount?.id) {
          await Promise.all([
            this.fetchAccount(this.selectedAccount.id),
            this.fetchPeriods(this.selectedAccount.id),
          ])
        }

        return response
      }
      finally {
        this.isSubmitting = false
      }
    },

    async createMovement(accountId, formData) {
      this.isSubmitting = true
      try {
        const response = await banksApi.createMovement(accountId, formData)

        await Promise.all([
          this.fetchAccount(accountId),
          this.fetchMovements(accountId),
        ])

        return response
      }
      finally {
        this.isSubmitting = false
      }
    },

    async cancelMovement(movementId, reason) {
      this.isSubmitting = true
      try {
        const response = await banksApi.cancelMovement(movementId, reason)

        if (this.selectedAccount?.id) {
          await Promise.all([
            this.fetchAccount(this.selectedAccount.id),
            this.fetchMovements(this.selectedAccount.id),
          ])
        }

        return response
      }
      finally {
        this.isSubmitting = false
      }
    },

    async createOperation(formData) {
      this.isSubmitting = true
      try {
        return await banksApi.createOperation(formData)
      }
      finally {
        this.isSubmitting = false
      }
    },

    async cancelOperation(operationId, reason) {
      this.isSubmitting = true
      try {
        return await banksApi.cancelOperation(operationId, reason)
      }
      finally {
        this.isSubmitting = false
      }
    },
  },
})

export default useBanksStore
