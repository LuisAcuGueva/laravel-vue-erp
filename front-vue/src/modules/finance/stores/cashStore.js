import { defineStore } from 'pinia'
import { boxesApi, cashSessionsApi } from '../services/cashApi'

export const useCashStore = defineStore('cash', {
  state: () => ({
    boxes: [],
    availableBoxes: [],
    selectedBoxId: localStorage.getItem('cash.selected_box_id') || null,
    currentSession: null,
    sessionSummary: null,
    isLoadingBoxes: false,
    isLoadingSession: false,
    error: null,
  }),

  getters: {
    selectedBox(state) {
      return state.availableBoxes.find(b => b.id === state.selectedBoxId) || null
    },
    hasOpenSession(state) {
      return state.currentSession !== null && state.currentSession.status === 'open'
    },
  },

  actions: {
    async fetchAvailableBoxes() {
      this.isLoadingBoxes = true
      try {
        const response = await boxesApi.available()

        this.availableBoxes = response?.data || []

        if (this.availableBoxes.length > 0) {
          const exists = this.availableBoxes.some(b => b.id === this.selectedBoxId)
          if (!exists) {
            await this.selectBox(this.availableBoxes[0].id)
          }
          else {
            await this.fetchCurrentSession()
          }
        }
        else {
          this.selectedBoxId = null
          this.currentSession = null
          this.sessionSummary = null
          localStorage.removeItem('cash.selected_box_id')
        }

        return this.availableBoxes
      }
      catch (err) {
        this.error = err?.data?.message || err?.message || 'Error al cargar cajas disponibles'
        throw err
      }
      finally {
        this.isLoadingBoxes = false
      }
    },

    async selectBox(boxId) {
      this.selectedBoxId = boxId
      if (boxId) {
        localStorage.setItem('cash.selected_box_id', boxId)

        return await this.fetchCurrentSession()
      }
      else {
        localStorage.removeItem('cash.selected_box_id')
        this.currentSession = null
        this.sessionSummary = null

        return null
      }
    },

    async fetchCurrentSession() {
      if (!this.selectedBoxId) {
        this.currentSession = null
        this.sessionSummary = null

        return null
      }

      const box = this.availableBoxes.find(b => b.id === this.selectedBoxId)
      const openSessionId = box?.open_session?.id || box?.current_session?.id

      if (!openSessionId) {
        this.currentSession = null
        this.sessionSummary = null

        return null
      }

      this.isLoadingSession = true
      try {
        const response = await cashSessionsApi.get(openSessionId)

        this.currentSession = response?.data || null
        this.sessionSummary = response?.data?.summary || null

        return this.currentSession
      }
      catch {
        this.currentSession = null
        this.sessionSummary = null

        return null
      }
      finally {
        this.isLoadingSession = false
      }
    },

    async openSession(payload) {
      this.isLoadingSession = true
      try {
        const response = await cashSessionsApi.open({
          'box_id': this.selectedBoxId,
          ...payload,
        })

        await this.fetchAvailableBoxes()

        return response?.data
      }
      finally {
        this.isLoadingSession = false
      }
    },

    async closeSession(payload) {
      if (!this.currentSession?.id)
        throw new Error('No hay una sesión activa para cerrar')

      this.isLoadingSession = true
      try {
        const response = await cashSessionsApi.close(this.currentSession.id, payload)

        await this.fetchAvailableBoxes()

        return response?.data
      }
      finally {
        this.isLoadingSession = false
      }
    },

    async addMovement(payload) {
      if (!this.currentSession?.id)
        throw new Error('No hay una sesión activa para registrar movimientos')

      const response = await cashSessionsApi.addMovement(this.currentSession.id, payload)

      await this.fetchCurrentSession()

      return response?.data
    },

    async cancelMovement(movementId, reason) {
      const response = await cashSessionsApi.cancelMovement(movementId, reason)

      await this.fetchCurrentSession()

      return response?.data
    },
  },
})

export default useCashStore
