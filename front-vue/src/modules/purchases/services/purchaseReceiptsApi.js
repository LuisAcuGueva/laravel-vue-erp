import { $api } from '@/utils/api'

export const purchaseReceiptsApi = {
  list(params = {}) {
    return $api('/v1/purchases/receipts', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/receipts/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/purchases/receipts', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/purchases/receipts/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  confirm(id, payload = {}) {
    return $api(`/v1/purchases/receipts/${id}/confirm`, {
      method: 'POST',
      body: payload,
    })
  },

  cancel(id, reason) {
    return $api(`/v1/purchases/receipts/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  getPendingItems(orderId) {
    return $api(`/v1/purchases/orders/${orderId}/pending-items`, {
      method: 'GET',
    })
  },

  receiveAll(orderId, payload) {
    return $api(`/v1/purchases/orders/${orderId}/receive-all`, {
      method: 'POST',
      body: payload,
    })
  },
}

export default purchaseReceiptsApi
