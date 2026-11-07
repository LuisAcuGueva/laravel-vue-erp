import { defineStore } from 'pinia'
import { clientsApi } from '../services/clientsApi'

export const useClientsStore = defineStore('clients', {
  state: () => ({
    items: [],
    currentClient: null,
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
    totalClients: state => state.meta.total,
    isEmpty: state => state.items.length === 0,
  },

  actions: {
    async fetchClients(params = {}) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.list(params)

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
        this.error = err?.data?.message || err?.message || 'Error al cargar los clientes'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async fetchClient(id) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.get(id)
        const client = response?.data || response

        this.currentClient = client

        return client
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar el cliente'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createClient(payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.create(payload)
        const newClient = response?.data || response

        this.items.unshift(newClient)
        this.meta.total += 1

        return newClient
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al registrar el cliente'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateClient(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.update(id, payload)
        const updated = response?.data || response

        const index = this.items.findIndex(item => item.id === id)
        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        if (this.currentClient?.id === id) {
          this.currentClient = { ...this.currentClient, ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar el cliente'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateCredit(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.updateCredit(id, payload)
        const updated = response?.data || response

        const index = this.items.findIndex(item => item.id === id)
        if (index !== -1) {
          this.items[index] = { ...this.items[index], ...updated }
        }

        if (this.currentClient?.id === id) {
          this.currentClient = { ...this.currentClient, ...updated }
        }

        return updated
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar las condiciones de crédito'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async syncContacts(id, payload) {
      this.isLoading = true
      this.error = null

      try {
        const response = await clientsApi.syncContacts(id, payload)
        const contacts = response?.data || []

        if (this.currentClient?.id === id) {
          this.currentClient.contacts = contacts
        }

        return contacts
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al sincronizar los contactos'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async deleteClient(id) {
      this.isLoading = true
      this.error = null

      try {
        await clientsApi.delete(id)

        this.items = this.items.filter(item => item.id !== id)
        this.meta.total = Math.max(0, this.meta.total - 1)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al eliminar el cliente'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useClientsStore
