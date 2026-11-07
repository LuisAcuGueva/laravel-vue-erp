import { defineStore } from 'pinia'
import { employeesApi } from '../services/employeesApi'

export const useEmployeesStore = defineStore('employees', {
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
    totalEmployees: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchEmployees(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar los empleados'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchOptions(params = {}) {
      try {
        const response = await employeesApi.options(params)

        this.options = response?.data || []

        return this.options
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar opciones de empleados'
        throw err
      }
    },

    async createEmployee(payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.create(payload)
        const newEmployee = response?.data || response

        this.items.unshift(newEmployee)
        this.meta.total += 1

        return newEmployee
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear el empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateEmployee(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.update(id, payload)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar el empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deleteEmployee(id) {
      this.isLoading = true
      this.error = null

      try {
        await employeesApi.delete(id)
        this.items = this.items.filter(item => item.id !== id)
        this.meta.total = Math.max(0, this.meta.total - 1)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar el empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createUser(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.createUser(id, payload)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear usuario para el empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async linkUser(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.linkUser(id, payload)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al vincular usuario al empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async unlinkUser(id) {
      this.isLoading = true
      this.error = null

      try {
        const response = await employeesApi.unlinkUser(id)
        const updated = response?.data || response
        const index = this.items.findIndex(item => item.id === id)

        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al desvincular usuario del empleado'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useEmployeesStore
