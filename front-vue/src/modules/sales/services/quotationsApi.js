import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const quotationsApi = {
  list(params = {}) {
    return $api('/v1/sales/quotations', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/sales/quotations/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/sales/quotations', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/sales/quotations/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  duplicate(id) {
    return $api(`/v1/sales/quotations/${id}/duplicate`, {
      method: 'POST',
    })
  },

  availability(id) {
    return $api(`/v1/sales/quotations/${id}/availability`, {
      method: 'GET',
    })
  },

  send(id) {
    return $api(`/v1/sales/quotations/${id}/send`, {
      method: 'POST',
    })
  },

  accept(id) {
    return $api(`/v1/sales/quotations/${id}/accept`, {
      method: 'POST',
    })
  },

  reject(id, reason) {
    return $api(`/v1/sales/quotations/${id}/reject`, {
      method: 'POST',
      body: { reason },
    })
  },

  cancel(id, reason) {
    return $api(`/v1/sales/quotations/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  async downloadPdf(id, number) {
    return downloadFile(
      `/v1/sales/quotations/${id}/pdf`,
      {},
      `cotizacion-${number || id}.pdf`,
    )
  },
}

export default quotationsApi
