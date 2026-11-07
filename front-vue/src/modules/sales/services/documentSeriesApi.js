import { $api } from '@/utils/api'

export const documentSeriesApi = {
  list(params = {}) {
    return $api('/v1/sales/document-series', {
      method: 'GET',
      params,
    })
  },

  available(params = {}) {
    return $api('/v1/sales/document-series/available', {
      method: 'GET',
      params,
    })
  },

  create(payload) {
    return $api('/v1/sales/document-series', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/sales/document-series/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/sales/document-series/${id}`, {
      method: 'DELETE',
    })
  },
}
