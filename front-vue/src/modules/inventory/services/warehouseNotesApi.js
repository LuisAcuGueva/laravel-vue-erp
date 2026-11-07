import { $api } from '@/utils/api'

export const warehouseNotesApi = {
  list(params = {}) {
    return $api('/v1/inventory/warehouse-notes', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/warehouse-notes/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/warehouse-notes', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/warehouse-notes/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  approve(id) {
    return $api(`/v1/inventory/warehouse-notes/${id}/approve`, {
      method: 'POST',
    })
  },

  cancel(id, payload) {
    return $api(`/v1/inventory/warehouse-notes/${id}/cancel`, {
      method: 'POST',
      body: payload,
    })
  },
}

export default warehouseNotesApi
