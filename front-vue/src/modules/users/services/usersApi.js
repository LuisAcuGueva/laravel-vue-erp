import { $api } from '@/utils/api'

export const usersApi = {
  list(params = {}) {
    return $api('/v1/users', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/users/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/users', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/users/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/users/${id}`, {
      method: 'DELETE',
    })
  },

  syncBranches(userId, payload) {
    return $api(`/v1/users/${userId}/branches`, {
      method: 'PUT',
      body: payload,
    })
  },
}

export default usersApi
