import { $api } from '@/utils/api'

export const purchaseRequestsApi = {
  list(params = {}) {
    return $api('/v1/purchases/requests', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/requests/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/purchases/requests', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/purchases/requests/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  submit(id) {
    return $api(`/v1/purchases/requests/${id}/submit`, {
      method: 'POST',
    })
  },

  approve(id) {
    return $api(`/v1/purchases/requests/${id}/approve`, {
      method: 'POST',
    })
  },

  reject(id, reason) {
    return $api(`/v1/purchases/requests/${id}/reject`, {
      method: 'POST',
      body: { reason },
    })
  },

  cancel(id, reason) {
    return $api(`/v1/purchases/requests/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  fromLowStock(payload) {
    return $api('/v1/purchases/requests/from-low-stock', {
      method: 'POST',
      body: payload,
    })
  },

  pendingItems(warehouseId) {
    return $api('/v1/purchases/requests/pending-items', {
      method: 'GET',
      params: { 'warehouse_id': warehouseId },
    })
  },
}

export default purchaseRequestsApi
