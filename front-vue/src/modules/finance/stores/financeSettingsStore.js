import { defineStore } from 'pinia'
import {
  bankAccountsApi,
  banksApi,
  conceptsApi,
  currenciesApi,
  financialAccountsApi,
  healthApi,
  paymentMethodsApi,
} from '../services/financeSettingsApi'

export const useFinanceSettingsStore = defineStore('financeSettings', {
  state: () => ({
    currenciesOptions: [],
    banksOptions: [],
    bankAccountsOptions: [],
    paymentMethodsOptions: [],
    financialAccountsTree: [],
    financialAccountsOptions: [],
    conceptsOptions: [],
    health: null,
    isLoading: false,
    error: null,
  }),

  getters: {
    requireConceptAccount: state => state.health?.require_concept_account ?? false,
  },

  actions: {
    async fetchHealth(force = false) {
      if (!force && this.health)
        return this.health

      try {
        const response = await healthApi.get()

        this.health = response?.data || null

        return this.health
      }
      catch (err) {
        this.health = null
        this.error = err?.data?.message || err?.message || 'Error al cargar el diagnóstico contable'
        throw err
      }
    },

    async fetchCurrenciesOptions(force = false) {
      if (!force && this.currenciesOptions.length > 0)
        return this.currenciesOptions

      try {
        const response = await currenciesApi.options()

        this.currenciesOptions = response?.data || []

        return this.currenciesOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de monedas'
        throw err
      }
    },

    async fetchBanksOptions(force = false) {
      if (!force && this.banksOptions.length > 0)
        return this.banksOptions

      try {
        const response = await banksApi.options()

        this.banksOptions = response?.data || []

        return this.banksOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de bancos'
        throw err
      }
    },

    async fetchBankAccountsOptions(params = {}, force = false) {
      if (!force && this.bankAccountsOptions.length > 0 && Object.keys(params).length === 0)
        return this.bankAccountsOptions

      try {
        const response = await bankAccountsApi.options(params)

        if (Object.keys(params).length === 0)
          this.bankAccountsOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de cuentas bancarias'
        throw err
      }
    },

    async fetchPaymentMethodsOptions(force = false) {
      if (!force && this.paymentMethodsOptions.length > 0)
        return this.paymentMethodsOptions

      try {
        const response = await paymentMethodsApi.options()

        this.paymentMethodsOptions = response?.data || []

        return this.paymentMethodsOptions
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de métodos de pago'
        throw err
      }
    },

    async fetchFinancialAccountsTree(force = false) {
      if (!force && this.financialAccountsTree.length > 0)
        return this.financialAccountsTree

      this.isLoading = true
      try {
        const response = await financialAccountsApi.tree()

        this.financialAccountsTree = response?.data || []

        return this.financialAccountsTree
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar el plan de cuentas'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchFinancialAccountsOptions(params = {}, force = false) {
      if (!force && this.financialAccountsOptions.length > 0 && Object.keys(params).length === 0)
        return this.financialAccountsOptions

      try {
        const response = await financialAccountsApi.options(params)

        if (Object.keys(params).length === 0)
          this.financialAccountsOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de cuentas financieras'
        throw err
      }
    },

    async fetchConceptsOptions(params = {}, force = false) {
      if (!force && this.conceptsOptions.length > 0 && Object.keys(params).length === 0)
        return this.conceptsOptions

      try {
        const response = await conceptsApi.options(params)

        if (Object.keys(params).length === 0)
          this.conceptsOptions = response?.data || []

        return response?.data || []
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de conceptos'
        throw err
      }
    },

    invalidateCurrencies() {
      this.currenciesOptions = []
    },

    invalidateBanks() {
      this.banksOptions = []
    },

    invalidateBankAccounts() {
      this.bankAccountsOptions = []
    },

    invalidatePaymentMethods() {
      this.paymentMethodsOptions = []
    },

    invalidateFinancialAccounts() {
      this.financialAccountsTree = []
      this.financialAccountsOptions = []
    },

    invalidateConcepts() {
      this.conceptsOptions = []
    },
  },
})

export default useFinanceSettingsStore
