import { $api } from '@/utils/api'

export const rolesApi = {
  list(params = {}) {
    return $api('/v1/roles', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/roles/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/roles', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/roles/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/roles/${id}`, {
      method: 'DELETE',
    })
  },
}

export default rolesApi
