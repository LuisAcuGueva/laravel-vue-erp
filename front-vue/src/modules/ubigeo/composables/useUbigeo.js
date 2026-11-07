import { computed } from 'vue'
import { useUbigeoStore } from '../stores/ubigeoStore'
import { formatUbigeo } from '../utils/ubigeoSearch'

/**
 * Única forma de convertir un código de ubigeo en texto legible en el frontend.
 */
export function useUbigeo() {
  const store = useUbigeoStore()

  // El error queda en store.error; aquí solo se evita el rechazo no manejado.
  store.ensureLoaded().catch(() => {})

  const describe = (code, { withCode = false } = {}) => {
    const ubigeo = store.find(code)
    if (!ubigeo)
      return '—'

    const label = formatUbigeo(ubigeo)

    return withCode ? `${label} (${ubigeo.code})` : label
  }

  return {
    isReady: computed(() => store.items.length > 0),
    find: store.find,
    describe,
  }
}
