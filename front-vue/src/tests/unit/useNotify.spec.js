import { beforeEach, describe, expect, it } from 'vitest'
import { useNotify } from '@/composables/useNotify'

describe('useNotify', () => {
  beforeEach(() => {
    useNotify().clear()
  })

  it('encola los toasts en orden sin pisarlos', () => {
    const notify = useNotify()

    notify.success('Sede creada correctamente')
    notify.error('No se pudo guardar')

    expect(notify.queue.value.map(t => t.message)).toEqual(['Sede creada correctamente', 'No se pudo guardar'])
    expect(notify.current.value.message).toBe('Sede creada correctamente')
  })

  it.each([
    ['success', 'success', 'tabler-circle-check'],
    ['error', 'error', 'tabler-alert-circle'],
    ['info', 'info', 'tabler-info-circle'],
    ['warning', 'warning', 'tabler-alert-triangle'],
  ])('%s asigna color e ícono', (type, color, icon) => {
    const notify = useNotify()

    notify[type]('Mensaje')

    expect(notify.current.value).toMatchObject({ type, color, icon, message: 'Mensaje' })
  })

  it('desencola al cerrar y muestra el siguiente', () => {
    const notify = useNotify()

    notify.success('Primero')
    notify.info('Segundo')
    notify.dismiss()

    expect(notify.current.value.message).toBe('Segundo')

    notify.dismiss()

    expect(notify.current.value).toBeNull()
  })

  it('ignora mensajes vacíos', () => {
    const notify = useNotify()

    notify.error('')

    expect(notify.queue.value).toHaveLength(0)
  })

  it('comparte la cola entre instancias', () => {
    useNotify().success('Compartido')

    expect(useNotify().current.value.message).toBe('Compartido')
  })
})
