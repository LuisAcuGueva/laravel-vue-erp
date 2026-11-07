/* eslint-disable camelcase */
import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const creditNotesApi = {
  list(params = {}) {
    return $api('/v1/sales/credit-notes', {
      method: 'GET',
      params,
    })
  },

  summary() {
    return $api('/v1/sales/credit-notes/summary', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/sales/credit-notes/${id}`, {
      method: 'GET',
    })
  },

  prefill(saleId) {
    return $api('/v1/sales/credit-notes/prefill', {
      method: 'GET',
      params: { sale_id: saleId },
    })
  },

  create(payload) {
    return $api('/v1/sales/credit-notes', {
      method: 'POST',
      body: payload,
    })
  },

  cancel(id, payload) {
    return $api(`/v1/sales/credit-notes/${id}/cancel`, {
      method: 'POST',
      body: payload,
    })
  },

  retryEInvoice(id) {
    return $api(`/v1/sales/credit-notes/${id}/einvoice/retry`, {
      method: 'POST',
    })
  },

  downloadPdf(id, format = 'a4', filename = null) {
    const finalFilename = filename || `nota-credito-${id}-${format}.pdf`

    return downloadFile(`/v1/sales/credit-notes/${id}/pdf`, finalFilename, {
      params: { format },
    })
  },
}
