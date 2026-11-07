/* eslint-disable camelcase */
import { defineStore } from 'pinia'
import { warehouseNotesApi } from '../services/warehouseNotesApi'

export const useWarehouseNotesStore = defineStore('warehouseNotes', {
  state: () => ({
    currentNote: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchNote(id) {
      this.isLoading = true
      this.error = null
      try {
        const response = await warehouseNotesApi.get(id)

        this.currentNote = response?.data || null

        return this.currentNote
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar nota de almacén'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async createNote(payload) {
      this.isLoading = true
      this.error = null
      try {
        return await warehouseNotesApi.create(payload)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al crear nota de almacén'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async updateNote(id, payload) {
      this.isLoading = true
      this.error = null
      try {
        return await warehouseNotesApi.update(id, payload)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al actualizar nota de almacén'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async approveNote(id) {
      this.isLoading = true
      this.error = null
      try {
        return await warehouseNotesApi.approve(id)
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al aprobar nota de almacén'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },

    async cancelNote(id, reason) {
      this.isLoading = true
      this.error = null
      try {
        return await warehouseNotesApi.cancel(id, { reason })
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al anular nota de almacén'
        throw err
      }
      finally {
        this.isLoading = false
      }
    },
  },
})

export default useWarehouseNotesStore
