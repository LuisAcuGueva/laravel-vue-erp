import { $api } from '@/utils/api'

export const auditApi = {
  list(params = {}) {
    return $api('/v1/audit-logs', {
      method: 'GET',
      params,
    })
  },

  subjectTypes() {
    return $api('/v1/audit-logs/subject-types', {
      method: 'GET',
    })
  },

  get(id) {
    return $api(`/v1/audit-logs/${id}`, {
      method: 'GET',
    })
  },
}

export default auditApi
