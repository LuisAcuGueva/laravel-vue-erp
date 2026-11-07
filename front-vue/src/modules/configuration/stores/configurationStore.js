import { defineStore } from 'pinia'
import { configurationApi } from '../services/configurationApi'

export const useConfigurationStore = defineStore('configuration', {
  state: () => ({
    summary: null,
    company: null,
    parameters: [],
    isLoading: false,
    isSaving: false,
    error: null,
  }),

  getters: {
    parametersByGroup: state => {
      const groups = {}

      for (const param of state.parameters) {
        const group = param.group_key || 'general'
        if (!groups[group]) {
          groups[group] = []
        }
        groups[group].push(param)
      }

      return groups
    },
  },

  actions: {
    async fetchSummary() {
      try {
        const response = await configurationApi.getSummary()

        this.summary = response?.data || response

        return this.summary
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al obtener resumen de empresa'
        throw err
      }
    },

    async fetchCompany() {
      this.isLoading = true
      this.error = null

      try {
        const response = await configurationApi.getCompany()

        this.company = response?.data || response

        return this.company
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar datos de empresa'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateCompany(payload) {
      this.isSaving = true
      this.error = null

      try {
        const response = await configurationApi.updateCompany(payload)
        const updated = response?.data || response

        this.company = updated

        if (this.summary) {
          this.summary = {
            ...this.summary,
            ...updated,
          }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar empresa'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async uploadLogo(file) {
      this.isSaving = true
      this.error = null

      try {
        const formData = new FormData()

        formData.append('logo', file)

        const response = await configurationApi.uploadLogo(formData)
        const updated = response?.data || response

        this.company = updated

        if (this.summary) {
          this.summary = {
            ...this.summary,
            ...updated,
          }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al subir el logo'
        throw err
      }
      finally {
        this.isSaving = false
      }
    },

    async fetchParameters(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await configurationApi.listParameters(params)

        this.parameters = response?.data || []

        return this.parameters
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar parámetros del sistema'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateParameter(id, value) {
      this.error = null

      try {
        const response = await configurationApi.updateParameter(id, { value })
        const updated = response?.data || response

        const index = this.parameters.findIndex(p => p.id === id)
        if (index !== -1) {
          this.parameters[index] = { ...this.parameters[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar parámetro'
        throw err
      }
    },
  },
})

export default useConfigurationStore
