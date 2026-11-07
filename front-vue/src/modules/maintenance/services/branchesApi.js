import { $api } from '@/utils/api'

export const branchesApi = {
  list(params = {}) {
    return $api('/v1/branches', {
      method: 'GET',
      params,
    })
  },

  options() {
    return $api('/v1/branches/options', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/branches/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/branches', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/branches/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/branches/${id}`, {
      method: 'DELETE',
    })
  },
}

export default branchesApi
