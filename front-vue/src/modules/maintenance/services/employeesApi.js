import { $api } from '@/utils/api'

export const employeesApi = {
  list(params = {}) {
    return $api('/v1/employees', {
      method: 'GET',
      params,
    })
  },

  options(params = {}) {
    return $api('/v1/employees/options', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/employees/${id}`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/employees', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/employees/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/employees/${id}`, {
      method: 'DELETE',
    })
  },

  createUser(id, payload) {
    return $api(`/v1/employees/${id}/user`, {
      method: 'POST',
      body: payload,
    })
  },

  linkUser(id, payload) {
    return $api(`/v1/employees/${id}/user`, {
      method: 'PUT',
      body: payload,
    })
  },

  unlinkUser(id) {
    return $api(`/v1/employees/${id}/user`, {
      method: 'DELETE',
    })
  },
}

export default employeesApi
