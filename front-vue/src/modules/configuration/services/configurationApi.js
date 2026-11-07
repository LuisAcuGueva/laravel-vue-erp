import { $api } from '@/utils/api'

export const configurationApi = {
  getCompany() {
    return $api('/v1/configuration/company', {
      method: 'GET',
    })
  },

  updateCompany(payload) {
    return $api('/v1/configuration/company', {
      method: 'PUT',
      body: payload,
    })
  },

  uploadLogo(formData) {
    return $api('/v1/configuration/company/logo', {
      method: 'POST',
      body: formData,
    })
  },

  getSummary() {
    return $api('/v1/configuration/company/summary', {
      method: 'GET',
    })
  },

  listParameters(params = {}) {
    return $api('/v1/configuration/parameters', {
      method: 'GET',
      params,
    })
  },

  updateParameter(id, payload) {
    return $api(`/v1/configuration/parameters/${id}`, {
      method: 'PUT',
      body: payload,
    })
  },
}

export default configurationApi
