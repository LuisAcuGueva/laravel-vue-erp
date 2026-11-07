import { $api } from '@/utils/api'

export const supplierInvoicesApi = {
  list(params = {}) {
    return $api('/v1/purchases/supplier-invoices', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/supplier-invoices/${id}`, {
      method: 'GET',
    })
  },

  // `payload` es un FormData (la factura puede llevar adjunto).
  create(payload) {
    return $api('/v1/purchases/supplier-invoices', {
      method: 'POST',
      body: payload,
    })
  },

  cancel(id, reason) {
    return $api(`/v1/purchases/supplier-invoices/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  prefill(receiptIds) {
    return $api('/v1/purchases/supplier-invoices/prefill', {
      method: 'GET',
      params: { 'receipt_ids[]': receiptIds },
    })
  },

  attachmentUrl(id) {
    return `/v1/purchases/supplier-invoices/${id}/attachment`
  },
}

export default supplierInvoicesApi
