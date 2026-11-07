import { $api } from '@/utils/api'

export const cashTransfersApi = {
  list(params = {}) {
    return $api('/v1/finance/cash-transfers', {
      method: 'GET',
      params,
    })
  },

  pendingCount() {
    return $api('/v1/finance/cash-transfers/pending-count', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/finance/cash-transfers/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/cash-transfers', {
      method: 'POST',
      body: payload,
    })
  },

  approve(id) {
    return $api(`/v1/finance/cash-transfers/${id}/approve`, {
      method: 'POST',
    })
  },

  reject(id, reason) {
    return $api(`/v1/finance/cash-transfers/${id}/reject`, {
      method: 'POST',
      body: { reason },
    })
  },

  cancel(id, reason) {
    return $api(`/v1/finance/cash-transfers/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },
}
