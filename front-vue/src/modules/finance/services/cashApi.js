import { $api } from '@/utils/api'

export const boxesApi = {
  list(params = {}) {
    return $api('/v1/finance/boxes', {
      method: 'GET',
      params,
    })
  },

  available() {
    return $api('/v1/finance/boxes/available', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/finance/boxes/${id}`, {
      method: 'GET',
    })
  },

  suggestedOpening(id) {
    return $api(`/v1/finance/boxes/${id}/suggested-opening`, {
      method: 'GET',
    })
  },

  create(payload) {
    return $api('/v1/finance/boxes', {
      method: 'POST',
      body: payload,
    })
  },

  update(id, payload) {
    return $api(`/v1/finance/boxes/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },

  delete(id) {
    return $api(`/v1/finance/boxes/${id}`, {
      method: 'DELETE',
    })
  },

  syncRoles(id, roleIds) {
    return $api(`/v1/finance/boxes/${id}/roles`, {
      method: 'PUT',
      body: { 'role_ids': roleIds },
    })
  },
}

export const cashSessionsApi = {
  list(params = {}) {
    return $api('/v1/finance/cash-sessions', {
      method: 'GET',
      params,
    })
  },

  get(id) {
    return $api(`/v1/finance/cash-sessions/${id}`, {
      method: 'GET',
    })
  },

  open(payload) {
    return $api('/v1/finance/cash-sessions', {
      method: 'POST',
      body: payload,
    })
  },

  close(id, payload) {
    return $api(`/v1/finance/cash-sessions/${id}/close`, {
      method: 'POST',
      body: payload,
    })
  },

  review(id, payload) {
    return $api(`/v1/finance/cash-sessions/${id}/review`, {
      method: 'POST',
      body: payload,
    })
  },

  movements(id, params = {}) {
    return $api(`/v1/finance/cash-sessions/${id}/movements`, {
      method: 'GET',
      params,
    })
  },

  addMovement(id, payload) {
    return $api(`/v1/finance/cash-sessions/${id}/movements`, {
      method: 'POST',
      body: payload,
    })
  },

  cancelMovement(movementId, reason) {
    return $api(`/v1/finance/cash-movements/${movementId}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },
}
