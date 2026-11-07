import { $api } from '@/utils/api'

export const supplierPaymentsApi = {
  list(params = {}) {
    return $api('/v1/purchases/supplier-payments', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/supplier-payments/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/purchases/supplier-payments', {
      method: 'POST',
      body: payload,
    })
  },

  cancel(id, reason) {
    return $api(`/v1/purchases/supplier-payments/${id}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  openInstallments(params) {
    return $api('/v1/purchases/supplier-payments/open-installments', {
      method: 'GET',
      params,
    })
  },
}

export default supplierPaymentsApi
