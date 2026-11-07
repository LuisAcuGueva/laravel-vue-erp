import { $api } from '@/utils/api'

export const currenciesApi = {
  options() {
    return $api('/v1/finance/currencies/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/finance/currencies', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/currencies/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/currencies', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/currencies/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/currencies/${id}`, {
      method: 'DELETE',
    })
  },
}

export const exchangeRatesApi = {
  current(currencyId, date = null) {
    return $api('/v1/finance/exchange-rates/current', {
      method: 'GET',
      params: {
        'currency_id': currencyId,
        ...(date ? { date } : {}),
      },
    })
  },

  list(params = {}) {
    return $api('/v1/finance/exchange-rates', {
      method: 'GET',
      params,
    })
  },

  upsert(payload) {
    return $api('/v1/finance/exchange-rates', {
      method: 'PUT',
      body: payload,
    })
  },
}

export const banksApi = {
  options() {
    return $api('/v1/finance/banks/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/finance/banks', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/banks/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/banks', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/banks/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/banks/${id}`, {
      method: 'DELETE',
    })
  },
}

export const bankAccountsApi = {
  options(params = {}) {
    return $api('/v1/finance/bank-accounts/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/finance/bank-accounts', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/bank-accounts/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/bank-accounts', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/bank-accounts/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/bank-accounts/${id}`, {
      method: 'DELETE',
    })
  },
}

export const paymentMethodsApi = {
  options() {
    return $api('/v1/finance/payment-methods/options', {
      method: 'GET',
    })
  },

  list(params = {}) {
    return $api('/v1/finance/payment-methods', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/payment-methods/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/payment-methods', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/payment-methods/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/payment-methods/${id}`, {
      method: 'DELETE',
    })
  },
}

export const financialAccountsApi = {
  tree() {
    return $api('/v1/finance/financial-accounts/tree', {
      method: 'GET',
    })
  },

  options(params = {}) {
    return $api('/v1/finance/financial-accounts/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/finance/financial-accounts', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/financial-accounts/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/financial-accounts', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/financial-accounts/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/financial-accounts/${id}`, {
      method: 'DELETE',
    })
  },
}

export const conceptsApi = {
  options(params = {}) {
    return $api('/v1/finance/concepts/options', {
      method: 'GET',
      params,
    })
  },

  list(params = {}) {
    return $api('/v1/finance/concepts', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/concepts/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/concepts', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/concepts/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/concepts/${id}`, {
      method: 'DELETE',
    })
  },
}

export const healthApi = {
  get() {
    return $api('/v1/finance/settings/health', {
      method: 'GET',
    })
  },
}

export default {
  currencies: currenciesApi,
  exchangeRates: exchangeRatesApi,
  banks: banksApi,
  bankAccounts: bankAccountsApi,
  paymentMethods: paymentMethodsApi,
  financialAccounts: financialAccountsApi,
  concepts: conceptsApi,
  health: healthApi,
}
