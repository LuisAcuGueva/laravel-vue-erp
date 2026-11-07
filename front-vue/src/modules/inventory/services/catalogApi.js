import { $api } from '@/utils/api'

export const catalogApi = {
  list(params = {}) {
    return $api('/v1/inventory/catalog-items', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/inventory/catalog-items/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/inventory/catalog-items', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/inventory/catalog-items/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/inventory/catalog-items/${id}`, {
      method: 'DELETE',
    })
  },

  lookup(params = {}) {
    return $api('/v1/inventory/catalog-items/lookup', {
      method: 'GET',
      params,
    })
  },

  listPresentations(id) {
    return $api(`/v1/inventory/catalog-items/${id}/presentations`, {
      method: 'GET',
    })
  },

  createPresentation(id, payload) {
    return $api(`/v1/inventory/catalog-items/${id}/presentations`, {
      method: 'POST',
      body: payload,
    })
  },

  updatePresentation(id, presentationId, payload) {
    return $api(`/v1/inventory/catalog-items/${id}/presentations/${presentationId}`, {
      method: 'PUT',
      body: payload,
    })
  },

  deletePresentation(id, presentationId) {
    return $api(`/v1/inventory/catalog-items/${id}/presentations/${presentationId}`, {
      method: 'DELETE',
    })
  },

  syncPrices(id, prices) {
    return $api(`/v1/inventory/catalog-items/${id}/prices`, {
      method: 'PUT',
      body: { prices },
    })
  },
}
