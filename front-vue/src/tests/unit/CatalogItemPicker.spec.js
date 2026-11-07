/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import CatalogItemPicker from '@/components/common/CatalogItemPicker.vue'
import { catalogApi } from '@/modules/inventory/services/catalogApi'

vi.mock('@/modules/inventory/services/catalogApi', () => ({
  catalogApi: {
    lookup: vi.fn(),
  },
}))

const mockPresentations = [
  {
    presentation_id: 'pres-1',
    catalog_item_id: 'item-1',
    code: 'ITM-000001',
    name: 'Aceite Sintético 5W-30',
    presentation_name: 'Unidad (1 L)',
    factor: 1.0,
    barcode: '7751234567890',
    unit: { id: 'u-1', code: 'NIU', name: 'Unidad', symbol: 'UND' },
    tax_type: 'taxable',
    type: 'product',
    track_stock: true,
    prices: { retail: 45.0, wholesale: 38.0, wholesale_min_quantity: 12 },
  },
  {
    presentation_id: 'pres-2',
    catalog_item_id: 'item-1',
    code: 'ITM-000001',
    name: 'Aceite Sintético 5W-30',
    presentation_name: 'Caja x 12',
    factor: 12.0,
    barcode: '7751234567891',
    unit: { id: 'u-2', code: 'BX', name: 'Caja', symbol: 'CJ' },
    tax_type: 'taxable',
    type: 'product',
    track_stock: true,
    prices: { retail: 450.0, wholesale: 420.0, wholesale_min_quantity: 2 },
  },
]

const vuetify = createVuetify()

const mountPicker = async (props = {}) => {
  const wrapper = mount(CatalogItemPicker, {
    props,
    global: { plugins: [vuetify] },
  })

  await flushPromises()

  return { wrapper, autocomplete: wrapper.findComponent({ name: 'VAutocomplete' }) }
}

describe('CatalogItemPicker', () => {
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
    catalogApi.lookup.mockResolvedValue({ data: mockPresentations })
  })

  it('renderiza VAutocomplete con label y placeholder', async () => {
    const { wrapper, autocomplete } = await mountPicker({
      label: 'Producto',
      placeholder: 'Buscar producto...',
      required: true,
    })

    expect(wrapper.findAllComponents({ name: 'VAutocomplete' })).toHaveLength(1)
    expect(autocomplete.props('label')).toBe('Producto *')
    expect(autocomplete.props('placeholder')).toBe('Buscar producto...')
  })

  it('permite valor inicial con initialItem', async () => {
    const initial = mockPresentations[0]

    const { wrapper, autocomplete } = await mountPicker({
      modelValue: initial.presentation_id,
      initialItem: initial,
    })

    expect(autocomplete.props('modelValue')).toBe(initial.presentation_id)
    expect(wrapper.vm.selectedItem).toEqual(initial)
  })

  it('realiza búsqueda con debounce de 250ms al tipear', async () => {
    vi.useFakeTimers()

    const { wrapper } = await mountPicker({ context: 'sale' })

    const searchField = wrapper.findComponent({ name: 'VAutocomplete' })

    await searchField.vm.$emit('update:search', 'Aceite')

    // Antes de 250ms no debe llamar a lookup
    expect(catalogApi.lookup).not.toHaveBeenCalled()

    // Avanzamos el temporizador 250ms
    vi.advanceTimersByTime(250)
    await flushPromises()

    expect(catalogApi.lookup).toHaveBeenCalledWith({
      q: 'Aceite',
      context: 'sale',
      limit: 20,
    })

    vi.useRealTimers()
  })

  it('selecciona automáticamente en Enter si hay coincidencia exacta de código de barras', async () => {
    const { wrapper } = await mountPicker({ context: 'sale' })

    catalogApi.lookup.mockResolvedValueOnce({ data: mockPresentations })

    // Simulamos búsqueda por lector
    const autocomplete = wrapper.findComponent({ name: 'VAutocomplete' })

    await autocomplete.vm.$emit('update:search', '7751234567890')
    await autocomplete.trigger('keydown.enter')
    await flushPromises()

    expect(catalogApi.lookup).toHaveBeenCalled()
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['pres-1'])
    expect(wrapper.emitted('selected')[0][0]).toMatchObject({ presentation_id: 'pres-1' })
  })

  it('emite null al limpiar la selección', async () => {
    const { wrapper } = await mountPicker({
      modelValue: 'pres-1',
      initialItem: mockPresentations[0],
    })

    const autocomplete = wrapper.findComponent({ name: 'VAutocomplete' })

    await autocomplete.vm.$emit('update:model-value', null)
    await flushPromises()

    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue').slice(-1)[0]).toEqual([null])
    expect(wrapper.emitted('selected').slice(-1)[0]).toEqual([null])
  })

  it('emite selected cuando se selecciona una presentación por su ID', async () => {
    const { wrapper, autocomplete } = await mountPicker({ context: 'sale' })

    await wrapper.vm.fetchLookup('Aceite')
    await autocomplete.vm.$emit('update:model-value', 'pres-1')
    await flushPromises()

    expect(wrapper.emitted('selected')).toBeTruthy()
    expect(wrapper.emitted('selected')[0][0]).toMatchObject({ presentation_id: 'pres-1' })
  })

  it('emite selected cuando se selecciona pasando directamente el objeto', async () => {
    const { wrapper, autocomplete } = await mountPicker({ context: 'sale' })

    await autocomplete.vm.$emit('update:model-value', mockPresentations[1])
    await flushPromises()

    expect(wrapper.emitted('selected')).toBeTruthy()
    expect(wrapper.emitted('selected')[0][0]).toMatchObject({ presentation_id: 'pres-2' })
  })
})
