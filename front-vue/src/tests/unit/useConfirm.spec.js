import { beforeEach, describe, expect, it } from 'vitest'
import { useConfirm } from '@/composables/useConfirm'

describe('useConfirm composable', () => {
  beforeEach(() => {
    const { handleCancel } = useConfirm()

    handleCancel()
  })

  it('inicializa con el dialogo cerrado', () => {
    const { isVisible } = useConfirm()

    expect(isVisible.value).toBe(false)
  })

  it('abre el dialogo y resuelve true cuando se confirma', async () => {
    const { confirm, isVisible, options, handleConfirm } = useConfirm()

    const confirmPromise = confirm({
      title: '¿Eliminar recurso?',
      message: 'Esta acción no se puede deshacer.',
      confirmText: 'Sí, eliminar',
      color: 'error',
    })

    expect(isVisible.value).toBe(true)
    expect(options.value.title).toBe('¿Eliminar recurso?')
    expect(options.value.message).toBe('Esta acción no se puede deshacer.')
    expect(options.value.confirmText).toBe('Sí, eliminar')
    expect(options.value.color).toBe('error')

    handleConfirm()

    const result = await confirmPromise

    expect(result).toBe(true)
    expect(isVisible.value).toBe(false)
  })

  it('resuelve false y cierra el dialogo cuando se cancela', async () => {
    const { confirm, isVisible, handleCancel } = useConfirm()

    const confirmPromise = confirm({
      title: '¿Cancelar operación?',
      message: 'Los cambios se descartarán.',
    })

    expect(isVisible.value).toBe(true)

    handleCancel()

    const result = await confirmPromise

    expect(result).toBe(false)
    expect(isVisible.value).toBe(false)
  })

  it('muestra u oculta el botón cancelar según hideCancel', () => {
    const { confirm, options } = useConfirm()

    confirm({ title: 'Aviso' })
    expect(options.value.hideCancel).toBe(false)

    confirm({ title: 'Aviso', hideCancel: true })
    expect(options.value.hideCancel).toBe(true)
  })

  it('confirmDelete arma título, mensaje, ícono y botón estándar', async () => {
    const { confirmDelete, options, handleConfirm } = useConfirm()

    const promise = confirmDelete({ entity: 'sede', name: 'Sede Central (SEDE-01)' })

    expect(options.value.title).toBe('¿Eliminar sede?')
    expect(options.value.message).toBe('Se eliminará «Sede Central (SEDE-01)». Esta acción no se puede deshacer.')
    expect(options.value.icon).toBe('tabler-trash')
    expect(options.value.color).toBe('error')
    expect(options.value.confirmText).toBe('Sí, eliminar')
    expect(options.value.hideCancel).toBe(false)

    handleConfirm()

    await expect(promise).resolves.toBe(true)
  })

  it('alert muestra un aviso warning de un solo botón y resuelve al aceptar', async () => {
    const { alert, options, isVisible, handleConfirm } = useConfirm()

    const promise = alert({ message: 'La sede tiene empleados asociados.' })

    expect(isVisible.value).toBe(true)
    expect(options.value.title).toBe('No se puede eliminar')
    expect(options.value.message).toBe('La sede tiene empleados asociados.')
    expect(options.value.color).toBe('warning')
    expect(options.value.icon).toBe('tabler-alert-circle')
    expect(options.value.confirmText).toBe('Entendido')
    expect(options.value.hideCancel).toBe(true)

    handleConfirm()

    await expect(promise).resolves.toBeUndefined()
    expect(isVisible.value).toBe(false)
  })

  it('un confirm concurrente resuelve el anterior con false', async () => {
    const { confirm, isVisible, options, handleConfirm } = useConfirm()

    const first = confirm({ title: 'Primero' })
    const second = confirm({ title: 'Segundo' })

    await expect(first).resolves.toBe(false)
    expect(isVisible.value).toBe(true)
    expect(options.value.title).toBe('Segundo')

    handleConfirm()

    await expect(second).resolves.toBe(true)
  })
})
