import { computed, ref } from 'vue'

const TYPES = {
  success: { color: 'success', icon: 'tabler-circle-check' },
  error: { color: 'error', icon: 'tabler-alert-circle' },
  info: { color: 'info', icon: 'tabler-info-circle' },
  warning: { color: 'warning', icon: 'tabler-alert-triangle' },
}

// Cola global de toasts: un solo `GlobalSnackbar` (App.vue) los muestra de a uno.
const queue = ref([])

let nextId = 1

/**
 * Notificaciones breves (toasts) de resultado (spec 031, design §8.8).
 * Los errores 422 de formulario no se notifican: ya se muestran en los campos.
 */
export function useNotify() {
  const push = (type, message) => {
    if (!message)
      return

    queue.value.push({ id: nextId++, type, message, ...TYPES[type] })
  }

  const current = computed(() => queue.value[0] || null)

  // Quita el toast visible para dar paso al siguiente de la cola.
  const dismiss = () => {
    queue.value.shift()
  }

  const clear = () => {
    queue.value = []
  }

  return {
    queue,
    current,
    dismiss,
    clear,
    success: message => push('success', message),
    error: message => push('error', message),
    info: message => push('info', message),
    warning: message => push('warning', message),
  }
}
