import { defineStore } from 'pinia'
import { cashTransfersApi } from '../services/cashTransfersApi'

export const useCashTransfersStore = defineStore('cashTransfers', {
  state: () => ({
    pendingCount: 0,
    isFetchingPending: false,
  }),

  actions: {
    async fetchPendingCount() {
      this.isFetchingPending = true
      try {
        const response = await cashTransfersApi.pendingCount()

        this.pendingCount = Number(response?.data?.count || 0)

        return this.pendingCount
      }
      catch {
        // Silently catch to not disrupt navigation or background polling
        return 0
      }
      finally {
        this.isFetchingPending = false
      }
    },
  },
})
