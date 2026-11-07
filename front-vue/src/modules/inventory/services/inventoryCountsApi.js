import { $api } from '@/utils/api'

export const inventoryCountsApi = {
  list(params = {}) {
    return $api('/v1/inventory/counts', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/counts/${id}`, {
      method: 'GET',
    })
  },

  create(data) {
    return $api('/v1/inventory/counts', {
      method: 'POST',
      body: data,
    })
  },

  updateItems(id, items) {
    return $api(`/v1/inventory/counts/${id}/items`, {
      method: 'PUT',
      body: { items },
    })
  },

  close(id, data = {}) {
    return $api(`/v1/inventory/counts/${id}/close`, {
      method: 'POST',
      body: data,
    })
  },

  cancel(id) {
    return $api(`/v1/inventory/counts/${id}/cancel`, {
      method: 'POST',
    })
  },
}

export default inventoryCountsApi
