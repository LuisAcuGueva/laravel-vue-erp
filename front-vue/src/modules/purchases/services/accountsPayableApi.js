import { $api } from '@/utils/api'

export const accountsPayableApi = {
  list(params = {}) {
    return $api('/v1/purchases/accounts-payable', {
      method: 'GET',
      params,
    })
  },

  aging(params = {}) {
    return $api('/v1/purchases/accounts-payable/aging', {
      method: 'GET',
      params,
    })
  },

  statement(providerId, params) {
    return $api(`/v1/purchases/providers/${providerId}/statement`, {
      method: 'GET',
      params,
    })
  },
}

export default accountsPayableApi
