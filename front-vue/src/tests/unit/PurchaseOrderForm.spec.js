/* eslint-disable camelcase */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import PurchaseOrderForm from '@/modules/purchases/components/orders/PurchaseOrderForm.vue'

vi.mock('@/modules/inventory/services/catalogApi', () => ({
  catalogApi: { lookup: vi.fn().mockResolvedValue({ data: [] }) },
}))

vi.mock('@/modules/purchases/services/providersApi', () => ({
  providersApi: { options: vi.fn().mockResolvedValue({ data: [] }) },
}))

vi.mock('@/modules/purchases/services/purchaseRequestsApi', () => ({
  purchaseRequestsApi: { pendingItems: vi.fn().mockResolvedValue({ data: [] }) },
}))

vi.mock('@/modules/configuration/services/configurationApi', () => ({
  configurationApi: { getSummary: vi.fn().mockResolvedValue({ data: { default_tax_rate: 18, prices_include_tax: false } }) },
}))

vi.mock('@/modules/inventory/services/inventorySettingsApi', () => ({
  warehousesApi: { options: vi.fn().mockResolvedValue({ data: [{ id: 'w-1', code: 'ALM-01', name: 'Central' }] }) },
}))

vi.mock('@/modules/finance/services/financeSettingsApi', () => ({
  currenciesApi: {
    options: vi.fn().mockResolvedValue({
      data: [
        { id: 'c-pen', code: 'PEN', symbol: 'S/', is_base: true },
        { id: 'c-usd', code: 'USD', symbol: '$', is_base: false },
      ],
    }),
  },
  exchangeRatesApi: { current: vi.fn().mockResolvedValue({ data: { rate: { sell_rate: '3.8000' } } }) },
}))

const vuetify = createVuetify()

const order = {
  id: 'po-1',
  provider_id: 'prov-1',
  provider: { id: 'prov-1', document_type: 'RUC', document_number: '20123456789', business_name: 'Andina S.A.C.' },
  warehouse_id: 'w-1',
  order_date: '2026-10-19',
  expected_date: null,
  currency_id: 'c-usd',
  exchange_rate: '3.8000',
  payment_condition: 'credit',
  credit_days: 30,
  prices_include_tax: false,
  tax_rate: '18.00',
  notes: 'Nota',
  terms: null,
  items: [{
    item_presentation_id: 'pres-1',
    description: null,
    quantity: '10.0000',
    unit_price: '100.0000',
    discount_percent: '5.00',
    tax_type: 'taxable',
    purchase_request_item_id: 'ri-1',
    request_number: 'RQ-000001',
    catalog_item: { code: 'ITM-1', name: 'Cemento' },
    presentation: { name: 'Bolsa' },
  }],
}

const mountForm = async (props = {}) => {
  const wrapper = mount(PurchaseOrderForm, { props, global: { plugins: [vuetify, createPinia()] } })

  await flushPromises()

  return wrapper
}

describe('PurchaseOrderForm', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('en una OC nueva usa la tasa y la moneda base de la empresa', async () => {
    const wrapper = await mountForm()
    const text = wrapper.text()

    expect(text).toContain('Líneas de la orden')
    expect(text).toContain('IGV (18%)')
    expect(text).toContain('Agregar desde requerimientos')
    expect(text).not.toContain('Tipo de cambio')
  })

  it('al editar carga la OC, conserva su tasa y emite el payload con las líneas y el requerimiento vinculado', async () => {
    const wrapper = await mountForm({ order })

    expect(wrapper.text()).toContain('Tipo de cambio')
    expect(wrapper.text()).toContain('RQ-000001')

    const send = wrapper.findAll('button').find(b => b.text().includes('Guardar y enviar'))

    await send.trigger('click')

    const [{ action, payload }] = wrapper.emitted('submit').at(-1)

    expect(action).toBe('send')
    expect(payload).toMatchObject({
      provider_id: 'prov-1',
      warehouse_id: 'w-1',
      currency_id: 'c-usd',
      exchange_rate: '3.8000',
      payment_condition: 'credit',
      credit_days: 30,
      prices_include_tax: false,
    })
    expect(payload.items).toEqual([{
      item_presentation_id: 'pres-1',
      description: null,
      quantity: '10.0000',
      unit_price: '100.0000',
      discount_percent: '5.00',
      tax_type: 'taxable',
      purchase_request_item_id: 'ri-1',
    }])
  })

  it('una OC en moneda base no envía tipo de cambio ni días de crédito si es de contado', async () => {
    const wrapper = await mountForm({ order: { ...order, currency_id: 'c-pen', payment_condition: 'cash', credit_days: 0 } })

    await wrapper.find('form').trigger('submit')

    const [{ action, payload }] = wrapper.emitted('submit').at(-1)

    expect(action).toBe('save')
    expect(payload.exchange_rate).toBeNull()
    expect(payload.credit_days).toBeNull()
  })

  it('precarga las líneas de un requerimiento con el saldo pendiente', async () => {
    const wrapper = await mountForm({
      prefill: {
        warehouse_id: 'w-1',
        lines: [{
          id: 'ri-9',
          item_presentation_id: 'pres-9',
          pending_quantity: '4.0000',
          description: null,
          catalog_item: { code: 'ITM-9', name: 'Fierro 1/2' },
          presentation: { name: 'Varilla' },
          request: { number: 'RQ-000009' },
        }],
      },
    })

    expect(wrapper.text()).toContain('RQ-000009')

    await wrapper.find('form').trigger('submit')

    const [{ payload }] = wrapper.emitted('submit').at(-1)

    expect(payload.items[0]).toMatchObject({ item_presentation_id: 'pres-9', quantity: '4.0000', purchase_request_item_id: 'ri-9' })
  })
})
