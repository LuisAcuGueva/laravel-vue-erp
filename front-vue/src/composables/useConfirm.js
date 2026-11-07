import { ref } from 'vue'

const DEFAULT_OPTIONS = {
  title: '¿Confirmar acción?',
  message: '¿Estás seguro de que deseas continuar?',
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  color: 'error',
  icon: 'tabler-alert-triangle',
  hideCancel: false,
}

const isVisible = ref(false)
const options = ref({ ...DEFAULT_OPTIONS })

let resolvePromise = null

const settle = value => {
  isVisible.value = false
  if (resolvePromise) {
    const resolve = resolvePromise

    resolvePromise = null
    resolve(value)
  }
}

/**
 * Diálogo global imperativo de confirmación (spec 008, ampliado en spec 031 §8.7).
 * Un solo `GlobalConfirmDialog` montado en `App.vue` renderiza el estado compartido.
 */
export function useConfirm() {
  /**
   * Abre el diálogo y resuelve `true` al confirmar o `false` al cancelar.
   * Si ya hay un diálogo abierto, el anterior se resuelve con `false`.
   * @param {{ title?, message?, icon?, color?, confirmText?, cancelText?, hideCancel? }} customOptions
   * @returns {Promise<boolean>}
   */
  const confirm = (customOptions = {}) => {
    if (resolvePromise)
      settle(false)

    options.value = { ...DEFAULT_OPTIONS, ...customOptions }
    isVisible.value = true

    return new Promise(resolve => {
      resolvePromise = resolve
    })
  }

  /**
   * Confirmación estándar de eliminación.
   * @param {{ entity: string, name?: string }} params ej. `{ entity: 'sede', name: 'Sede Central (SEDE-01)' }`
   * @returns {Promise<boolean>}
   */
  const confirmDelete = ({ entity, name } = {}) => confirm({
    title: `¿Eliminar ${entity}?`,
    message: name
      ? `Se eliminará «${name}». Esta acción no se puede deshacer.`
      : 'Esta acción no se puede deshacer.',
    icon: 'tabler-trash',
    color: 'error',
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
  })

  /**
   * Aviso informativo con un solo botón (ej. la API rechazó una eliminación).
   * @returns {Promise<void>}
   */
  const alert = ({
    title = 'No se puede eliminar',
    message = '',
    color = 'warning',
    icon = 'tabler-alert-circle',
    confirmText = 'Entendido',
  } = {}) => confirm({ title, message, color, icon, confirmText, hideCancel: true }).then(() => undefined)

  const handleConfirm = () => settle(true)

  const handleCancel = () => settle(false)

  return {
    isVisible,
    options,
    confirm,
    confirmDelete,
    alert,
    handleConfirm,
    handleCancel,
  }
}
