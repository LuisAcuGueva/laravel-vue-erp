/* eslint-disable camelcase */
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import DocumentLinesTable from '@/components/common/DocumentLinesTable.vue'

vi.mock('@/modules/inventory/services/catalogApi', () => ({
  catalogApi: { lookup: vi.fn().mockResolvedValue({ data: [] }) },
}))

const vuetify = createVuetify()

const line = (overrides = {}) => ({
  key: `k-${Math.random()}`,
  item_presentation_id: 'pres-1',
  initialItem: { presentation_id: 'pres-1', code: 'ITM-000001', name: 'Cemento Sol', presentation_name: 'Bolsa 42.5 kg' },
  description: null,
  quantity: '10',
  unit_price: '100',
  discount_percent: '0',
  tax_type: 'taxable',
  ...overrides,
})

const mountTable = (props = {}) => mount(DocumentLinesTable, {
  props: { modelValue: [line()], taxRate: 18, ...props },
  global: { plugins: [vuetify] },
})

describe('DocumentLinesTable', () => {
  it('muestra la vista previa de totales con el cálculo del backend', () => {
    const wrapper = mountTable()
    const text = wrapper.text()

    expect(text).toContain('1,000.00')
    expect(text).toContain('180.00')
    expect(text).toContain('1,180.00')
    expect(text).toContain('IGV (18%)')
  })

  it('emite los totales calculados', () => {
    const wrapper = mountTable({ modelValue: [line({ discount_percent: '5' })] })
    const [totals] = wrapper.emitted('totals').at(-1)

    expect(totals.taxableBase).toBe('950.00')
    expect(totals.taxTotal).toBe('171.00')
    expect(totals.total).toBe('1121.00')
  })

  it('respeta precios con impuesto incluido y líneas exoneradas', () => {
    const wrapper = mountTable({
      pricesIncludeTax: true,
      modelValue: [line({ quantity: '1', unit_price: '118' }), line({ quantity: '1', unit_price: '50', tax_type: 'exempt' })],
    })

    const [totals] = wrapper.emitted('totals').at(-1)

    expect(totals.taxableBase).toBe('100.00')
    expect(totals.exemptBase).toBe('50.00')
    expect(totals.taxTotal).toBe('18.00')
    expect(totals.total).toBe('168.00')
  })

  it('agrega y quita líneas emitiendo el nuevo arreglo', async () => {
    const wrapper = mountTable({ allowFreeText: true })
    const buttons = wrapper.findAll('button').filter(b => b.text().includes('Agregar'))

    expect(buttons.map(b => b.text())).toEqual(['Agregar artículo', 'Agregar servicio (texto libre)'])

    await buttons[1].trigger('click')

    const added = wrapper.emitted('update:modelValue').at(-1)[0]

    expect(added).toHaveLength(2)
    expect(added[1].is_free).toBe(true)
    expect(added[1].description).toBe('')
  })

  it('no ofrece líneas libres si no se permiten', () => {
    const wrapper = mountTable()

    expect(wrapper.text()).not.toContain('texto libre')
  })

  it('en solo lectura no hay inputs ni botones de edición', () => {
    const wrapper = mountTable({ readonly: true })

    expect(wrapper.find('input').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Agregar artículo')
    expect(wrapper.text()).toContain('ITM-000001 · Cemento Sol')
    expect(wrapper.text()).toContain('1,180.00')
  })

  it('muestra el requerimiento de origen de una línea', () => {
    const wrapper = mountTable({ modelValue: [line({ request_number: 'RQ-000007' })] })

    expect(wrapper.text()).toContain('RQ-000007')
  })

  it('muestra los errores de la API por línea y campo', () => {
    const wrapper = mountTable({ errors: { 'items.0.quantity': 'La cantidad debe ser mayor a 0.' } })

    expect(wrapper.text()).toContain('La cantidad debe ser mayor a 0.')
  })

  it('usa el símbolo de moneda indicado', () => {
    const wrapper = mountTable({ currencySymbol: '$' })

    expect(wrapper.text()).toContain('$ 1,180.00')
  })
})
