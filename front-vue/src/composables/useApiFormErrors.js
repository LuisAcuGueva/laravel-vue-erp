import { ref } from 'vue'

export function useApiFormErrors() {
  const errors = ref({})
  const generalError = ref(null)

  const setErrors = errorResponse => {
    errors.value = {}
    generalError.value = null

    const data = errorResponse?.data || errorResponse?.response?._data || errorResponse?.response?.data || errorResponse

    if (data?.errors && typeof data.errors === 'object') {
      for (const [key, messages] of Object.entries(data.errors)) {
        errors.value[key] = Array.isArray(messages) ? messages[0] : messages
      }
    }

    if (data?.message) {
      generalError.value = data.message
    }
  }

  const clearErrors = () => {
    errors.value = {}
    generalError.value = null
  }

  const clearFieldError = field => {
    if (errors.value && errors.value[field]) {
      delete errors.value[field]
    }
  }

  const hasErrors = () => Object.keys(errors.value).length > 0 || Boolean(generalError.value)

  return {
    errors,
    generalError,
    setErrors,
    clearErrors,
    clearFieldError,
    hasErrors,
  }
}
