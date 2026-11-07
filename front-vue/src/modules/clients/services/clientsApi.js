import { $api } from '@/utils/api'

export const clientsApi = {
  list(params = {}) {
    return $api('/v1/clients', {
      method: 'GET',
      params,
    })
  },

  options(params = {}) {
    return $api('/v1/clients/options', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/clients/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/clients', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/clients/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/clients/${id}`, {
      method: 'DELETE',
    })
  },

  updateCredit(id, payload) {
    return $api(`/v1/clients/${id}/credit`, {
      method: 'PUT',
      body: payload,
    })
  },

  syncContacts(id, payload) {
    return $api(`/v1/clients/${id}/contacts`, {
      method: 'PUT',
      body: payload,
    })
  },
}

export default clientsApi
