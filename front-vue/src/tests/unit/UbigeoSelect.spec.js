/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import UbigeoSelect from '@/components/common/UbigeoSelect.vue'
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
  { code: '070101', department: 'CALLAO', province: 'CALLAO', district: 'CALLAO', is_active: true },
]

// Los componentes de Vuetify los registra vite-plugin-vuetify (también en Vitest).
const vuetify = createVuetify()

const mountSelect = async (props = {}) => {
  const wrapper = mount(UbigeoSelect, {
    props,
    global: { plugins: [vuetify] },
  })

  await flushPromises()

  return { wrapper, field: wrapper.findComponent({ name: 'VAutocomplete' }) }
}

describe('UbigeoSelect', () => {
  beforeAll(() => {
    globalThis.ResizeObserver ??= class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
  })

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    ubigeoApi.catalog.mockResolvedValue({ data: catalog })
  })

  it('es un solo campo que muestra departamento, provincia, distrito y código del valor inicial', async () => {
    const { wrapper, field } = await mountSelect({ modelValue: '150122' })

    expect(wrapper.findAllComponents({ name: 'VAutocomplete' })).toHaveLength(1)
    expect(field.props('modelValue')).toBe('150122')
    expect(field.props('items')[0]).toMatchObject({ code: '150122', title: 'Lima, Lima, Miraflores · 150122' })
    expect(wrapper.vm.selected).toEqual({ code: '150122', department: 'LIMA', province: 'LIMA', district: 'MIRAFLORES' })
  })

  it('marca como no válido un valor inicial que no existe', async () => {
    const { field } = await mountSelect({ modelValue: '999999' })

    expect(field.props('errorMessages')).toContain('Ubigeo no válido')
  })

  it('busca por nombre y al elegir emite el código y el detalle', async () => {
    const { wrapper, field } = await mountSelect()

    field.vm.$emit('update:search', 'canete')
    await flushPromises()

    expect(field.props('items').map(i => i.code)).toEqual(['150501'])

    field.vm.$emit('update:modelValue', '150501')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['150501'])
    expect(wrapper.emitted('change').at(-1)).toEqual([
      { code: '150501', department: 'LIMA', province: 'CAÑETE', district: 'SAN VICENTE DE CAÑETE' },
    ])
  })

  it('un código exacto de 6 dígitos se selecciona directo', async () => {
    const { wrapper, field } = await mountSelect()

    field.vm.$emit('update:search', '070101')
    await flushPromises()

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['070101'])
  })

  it('limpiar el campo emite null', async () => {
    const { wrapper, field } = await mountSelect({ modelValue: '150122' })

    field.vm.$emit('update:modelValue', null)
    await flushPromises()

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.emitted('change').at(-1)).toEqual([null])
  })

  it('una sola petición aunque haya varios componentes', async () => {
    await Promise.all([mountSelect(), mountSelect()])

    expect(ubigeoApi.catalog).toHaveBeenCalledTimes(1)
  })
})
