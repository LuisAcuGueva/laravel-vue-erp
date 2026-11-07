import { $api } from '@/utils/api'

export const providersApi = {
  list(params = {}) {
    return $api('/v1/purchases/providers', {
      method: 'GET',
      params,
    })
  },

  options(params = {}) {
    return $api('/v1/purchases/providers/options', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/purchases/providers/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/purchases/providers', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/purchases/providers/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/purchases/providers/${id}`, {
      method: 'DELETE',
    })
  },

  syncContacts(id, contacts) {
    return $api(`/v1/purchases/providers/${id}/contacts`, {
      method: 'PUT',
      body: { contacts },
    })
  },

  syncBankAccounts(id, accounts) {
    return $api(`/v1/purchases/providers/${id}/bank-accounts`, {
      method: 'PUT',
      body: { accounts },
    })
  },

  listPrices(id) {
    return $api(`/v1/purchases/providers/${id}/prices`, {
      method: 'GET',
    })
  },

  createPrice(id, payload) {
    return $api(`/v1/purchases/providers/${id}/prices`, {
      method: 'POST',
      body: payload,
    })
  },

  updatePrice(priceId, payload) {
    return $api(`/v1/purchases/provider-prices/${priceId}`, {
      method: 'PUT',
      body: payload,
    })
  },

  deletePrice(priceId) {
    return $api(`/v1/purchases/provider-prices/${priceId}`, {
      method: 'DELETE',
    })
  },

  comparePrices(itemPresentationId) {
    return $api('/v1/purchases/provider-prices/compare', {
      method: 'GET',
      params: { 'item_presentation_id': itemPresentationId },
    })
  },
}

export default providersApi
