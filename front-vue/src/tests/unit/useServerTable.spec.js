/* eslint-disable camelcase */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { SEARCH_DEBOUNCE_MS, useServerTable } from '@/composables/useServerTable'
import { useNotify } from '@/composables/useNotify'
import { downloadFile } from '@/utils/download'

vi.mock('@/utils/download', () => ({
  downloadFile: vi.fn(),
}))

const setup = (config = {}) => {
  const fetch = vi.fn().mockResolvedValue({})
  const table = useServerTable({ fetch, filters: { is_active: null, area_id: null }, immediate: false, ...config })

  return { fetch, table }
}

describe('useServerTable', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    useNotify().clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reload envía página, tamaño y omite parámetros vacíos', async () => {
    const { fetch, table } = setup()

    await table.reload()

    expect(fetch).toHaveBeenCalledWith({ page: 1, per_page: 10 })
  })

  it('expone items, total y loading de la respuesta paginada', async () => {
    const { fetch, table } = setup()

    fetch.mockResolvedValue({ data: [{ id: 'a' }, { id: 'b' }], meta: { total: 42 } })

    const pending = table.reload()

    expect(table.loading).toBe(true)
    await pending

    expect(table.items).toEqual([{ id: 'a' }, { id: 'b' }])
    expect(table.total).toBe(42)
    expect(table.loading).toBe(false)
  })

  it('conserva items si la respuesta no es un listado paginado', async () => {
    const { table } = setup()

    await table.reload()

    expect(table.items).toEqual([])
    expect(table.total).toBe(0)
    expect(table.loading).toBe(false)
  })

  it('aplica debounce de 350 ms a la búsqueda', async () => {
    const { fetch, table } = setup()

    table.search = 'li'
    await nextTick()
    table.search = 'lima'
    await nextTick()

    vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS - 1)
    expect(fetch).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1)
    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch).toHaveBeenCalledWith({ page: 1, per_page: 10, search: 'lima' })
  })

  it('vuelve a la página 1 al filtrar y recarga una sola vez', async () => {
    const { fetch, table } = setup()

    table.options = { page: 3, itemsPerPage: 25, sortBy: [] }
    await nextTick()
    fetch.mockClear()

    table.filters.is_active = false
    await nextTick()
    vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
    await nextTick()

    expect(table.options.page).toBe(1)
    expect(fetch).toHaveBeenCalledTimes(1)
    expect(fetch).toHaveBeenCalledWith({ page: 1, per_page: 25, is_active: '0' })
  })

  it('convierte booleanos de STATUS_OPTIONS a 1/0', async () => {
    const { fetch, table } = setup()

    table.filters.is_active = true
    await table.reload()

    expect(fetch).toHaveBeenLastCalledWith({ page: 1, per_page: 10, is_active: '1' })
  })

  it('options recarga de inmediato', async () => {
    const { fetch, table } = setup()

    table.options = { page: 2, itemsPerPage: 10, sortBy: [] }
    await nextTick()

    expect(fetch).toHaveBeenCalledWith({ page: 2, per_page: 10 })
  })

  it('hasFilters refleja si hay algún filtro aplicado', () => {
    const { table } = setup()

    expect(table.hasFilters).toBe(false)
    table.filters.area_id = 'abc'
    expect(table.hasFilters).toBe(true)
  })

  it('reset limpia búsqueda y filtros', () => {
    const { table } = setup()

    table.search = 'x'
    table.filters.is_active = true
    table.reset()

    expect(table.search).toBe('')
    expect(table.filters.is_active).toBeNull()
  })

  it('export descarga con los filtros y sin page/per_page', async () => {
    const { table } = setup()

    table.search = 'lima'
    table.filters.is_active = true
    table.options = { page: 4, itemsPerPage: 50, sortBy: [] }

    const promise = table.export('/v1/branches/export', 'sedes')

    expect(table.exporting).toBe(true)
    await promise

    expect(downloadFile).toHaveBeenCalledWith('/v1/branches/export', { search: 'lima', is_active: '1' }, 'sedes.csv')
    expect(table.exporting).toBe(false)
  })

  it('export notifica el error y libera exporting', async () => {
    downloadFile.mockRejectedValueOnce(new Error('fallo'))

    const { table } = setup()

    await table.export('/v1/branches/export', 'sedes')

    expect(table.exporting).toBe(false)
    expect(useNotify().current.value).toMatchObject({ type: 'error', message: 'No se pudo exportar el listado.' })
  })

  it('un error al cargar se notifica sin lanzar', async () => {
    const fetch = vi.fn().mockRejectedValue({ data: { message: 'Sin conexión' } })
    const table = useServerTable({ fetch, immediate: false })

    await expect(table.reload()).resolves.toBeUndefined()
    expect(useNotify().current.value.message).toBe('Sin conexión')
  })

  it('mapParams adapta los parámetros del listado y de la exportación', async () => {
    const fetch = vi.fn().mockResolvedValue({})

    const table = useServerTable({
      fetch,
      filters: { status: null },
      immediate: false,
      mapParams: ({ status, ...rest }) => ({
        ...rest,
        status: status === '1' ? 'active' : status === '0' ? 'inactive' : undefined,
      }),
    })

    table.filters.status = false
    await table.reload()
    await table.export('/v1/users/export', 'usuarios')

    expect(fetch).toHaveBeenLastCalledWith({ page: 1, per_page: 10, status: 'inactive' })
    expect(downloadFile).toHaveBeenLastCalledWith('/v1/users/export', { status: 'inactive' }, 'usuarios.csv')
  })
})
