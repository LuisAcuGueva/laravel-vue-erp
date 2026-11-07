import { $api } from '@/utils/api'

export const profileApi = {
  getProfile() {
    return $api('/v1/profile', {
      method: 'GET',
    })
  },

  updateProfile(payload) {
    return $api('/v1/profile', {
      method: 'PUT',
      body: payload,
    })
  },

  updatePassword(payload) {
    return $api('/v1/profile/password', {
      method: 'PUT',
      body: payload,
    })
  },

  listSessions() {
    return $api('/v1/profile/sessions', {
      method: 'GET',
    })
  },

  revokeOtherSessions() {
    return $api('/v1/profile/sessions/others', {
      method: 'DELETE',
    })
  },
}

export default profileApi
