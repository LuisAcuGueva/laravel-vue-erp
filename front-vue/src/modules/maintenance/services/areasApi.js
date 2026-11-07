import { $api } from '@/utils/api'

export const areasApi = {
  list(params = {}) {
    return $api('/v1/areas', {
      method: 'GET',
      params,
    })
  },

  options() {
    return $api('/v1/areas/options', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/areas/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/areas', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/areas/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/areas/${id}`, {
      method: 'DELETE',
    })
  },
}

export default areasApi
