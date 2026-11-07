import { $api } from '@/utils/api'

export const permissionsApi = {
  list(params = {}) {
    return $api('/v1/permissions', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/permissions/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/permissions', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/permissions/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/permissions/${id}`, {
      method: 'DELETE',
    })
  },
}

export default permissionsApi
