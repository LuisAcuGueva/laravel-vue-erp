import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const purchaseOrdersApi = {
  list(params = {}) {
    return $api('/v1/purchases/orders', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/orders/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/purchases/orders', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/purchases/orders/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  submit(id) {
    return $api(`/v1/purchases/orders/${id}/submit`, {
      method: 'POST',
    })
  },

  approve(id) {
    return $api(`/v1/purchases/orders/${id}/approve`, {
      method: 'POST',
    })
  },

  reject(id, reason) {
    return $api(`/v1/purchases/orders/${id}/reject`, {
      method: 'POST',
      body: { reason },
    })
  },

  close(id, reason) {
    return $api(`/v1/purchases/orders/${id}/close`, {
      method: 'POST',
      body: reason ? { reason } : {},
    })
  },

  cancel(id, reason) {
    return $api(`/v1/purchases/orders/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  /** Descarga el PDF de la OC con `utils/download.js` (incluye Authorization y X-Branch-Id). */
  downloadPdf(id, number) {
    return downloadFile(`/v1/purchases/orders/${id}/pdf`, {}, `${number}.pdf`)
  },
}

export default purchaseOrdersApi
