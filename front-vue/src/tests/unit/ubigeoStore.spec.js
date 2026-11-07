/* eslint-disable camelcase */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useUbigeoStore } from '@/modules/ubigeo/stores/ubigeoStore'
import { useUbigeo } from '@/modules/ubigeo/composables/useUbigeo'
import { ubigeoApi } from '@/modules/ubigeo/services/ubigeoApi'

vi.mock('@/modules/ubigeo/services/ubigeoApi', () => ({
  ubigeoApi: {
    catalog: vi.fn(),
  },
}))

const catalog = [
  { code: '150101', department: 'LIMA', province: 'LIMA', district: 'LIMA', is_active: true },
  { code: '150122', department: 'LIMA', province: 'LIMA', district: 'MIRAFLORES', is_active: true },
  { code: '150501', department: 'LIMA', province: 'CAÑETE', district: 'SAN VICENTE DE CAÑETE', is_active: true },
  { code: '070101', department: 'CALLAO', province: 'CALLAO', district: 'CALLAO', is_active: false },
]

describe('ubigeoStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('comparte una sola petición entre llamadas simultáneas', async () => {
    ubigeoApi.catalog.mockResolvedValue({ data: catalog, meta: { version: 'abc' } })

    const store = useUbigeoStore()

    await Promise.all([store.ensureLoaded(), store.ensureLoaded()])
    await store.ensureLoaded()

    expect(ubigeoApi.catalog).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(4)
    expect(store.version).toBe('abc')
    expect(store.isLoading).toBe(false)
  })

  it('deja el error y permite reintentar', async () => {
    ubigeoApi.catalog.mockRejectedValueOnce(new Error('network'))
    ubigeoApi.catalog.mockResolvedValueOnce({ data: catalog })

    const store = useUbigeoStore()

    await expect(store.ensureLoaded()).rejects.toThrow('network')
    expect(store.error).toBe('No se pudo cargar el catálogo de ubigeo')

    await store.ensureLoaded()
    expect(ubigeoApi.catalog).toHaveBeenCalledTimes(2)
    expect(store.error).toBeNull()
  })

  it('indexa el catálogo por código', async () => {
    ubigeoApi.catalog.mockResolvedValue({ data: catalog })

    const store = useUbigeoStore()

    await store.ensureLoaded()

    expect(store.find('150122')).toEqual({ code: '150122', department: 'LIMA', province: 'LIMA', district: 'MIRAFLORES', isActive: true })
    expect(store.find('070101')).toMatchObject({ district: 'CALLAO', isActive: false })
    expect(store.find('999999')).toBeNull()
    expect(store.find(null)).toBeNull()
    expect(store.searchIndex.map(e => e.code)).toEqual(['150101', '150122', '150501'])
  })

  it('describe convierte un código en texto legible', async () => {
    ubigeoApi.catalog.mockResolvedValue({ data: catalog })

    const { describe: describeUbigeo } = useUbigeo()

    await useUbigeoStore().ensureLoaded()

    expect(describeUbigeo('150122')).toBe('Lima, Lima, Miraflores')
    expect(describeUbigeo('150122', { withCode: true })).toBe('Lima, Lima, Miraflores (150122)')
    expect(describeUbigeo(null)).toBe('—')
    expect(describeUbigeo('999999')).toBe('—')
    expect(ubigeoApi.catalog).toHaveBeenCalledTimes(1)
  })
})
