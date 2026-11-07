import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const salesApi = {
  list(params = {}) {
    return $api('/v1/sales/sales', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/sales/sales/${id}`, {
      method: 'GET',
    })
  },

  prefill(params = {}) {
    return $api('/v1/sales/sales/prefill', {
      method: 'GET',
      params,
    })
  },

  create(payload) {
    return $api('/v1/sales/sales', {
      method: 'POST',
      body: payload,
    })
  },

  cancel(id, payload) {
    return $api(`/v1/sales/sales/${id}/cancel`, {
      method: 'POST',
      body: payload,
    })
  },

  retryEInvoice(id) {
    return $api(`/v1/sales/sales/${id}/einvoice/retry`, {
      method: 'POST',
    })
  },

  downloadPdf(id, format = 'a4', filename = null) {
    const finalFilename = filename || `venta-${id}-${format}.pdf`

    return downloadFile(`/v1/sales/sales/${id}/pdf`, finalFilename, {
      params: { format },
    })
  },
}
