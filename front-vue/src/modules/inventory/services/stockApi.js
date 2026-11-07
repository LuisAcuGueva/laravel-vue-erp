import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const stockApi = {
  list(params = {}) {
    return $api('/v1/inventory/stock', {
      method: 'GET',
      params,
    })
  },

  byItem(id) {
    return $api(`/v1/inventory/stock/by-item/${id}`, {
      method: 'GET',
    })
  },

  updateLimits(payload) {
    return $api('/v1/inventory/stock/limits', {
      method: 'PUT',
      body: payload,
    })
  },

  kardex(params = {}) {
    return $api('/v1/inventory/kardex', {
      method: 'GET',
      params,
    })
  },

  exportKardex(params = {}) {
    return downloadFile('/v1/inventory/kardex/export', params, 'kardex.csv')
  },
}

export default stockApi
