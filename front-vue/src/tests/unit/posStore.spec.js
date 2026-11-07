/* eslint-disable camelcase */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { usePosStore } from '@/modules/sales/stores/posStore'
import { salesApi } from '@/modules/sales/services/salesApi'
import { documentSeriesApi } from '@/modules/sales/services/documentSeriesApi'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { currenciesApi } from '@/modules/finance/services/financeSettingsApi'
import { useCashStore } from '@/modules/finance/stores/cashStore'

vi.mock('@/modules/sales/services/salesApi', () => ({
  salesApi: {
    prefill: vi.fn(),
  },
}))

vi.mock('@/modules/sales/services/documentSeriesApi', () => ({
  documentSeriesApi: {
    available: vi.fn(),
  },
}))

vi.mock('@/modules/inventory/services/inventorySettingsApi', () => ({
  warehousesApi: {
    options: vi.fn(),
  },
}))

vi.mock('@/modules/finance/services/financeSettingsApi', () => ({
  currenciesApi: {
    options: vi.fn(),
  },
}))

vi.mock('@/modules/finance/stores/cashStore', () => ({
  useCashStore: vi.fn(),
}))

describe('posStore Pinia store', () => {
  const cashStoreStub = {
    availableBoxes: [{ id: 'box-1' }],
    selectedBox: { id: 'box-1', name: 'Caja Principal' },
    currentSession: { id: 'session-1', status: 'open', number: 1 },
    hasOpenSession: true,
    fetchAvailableBoxes: vi.fn(),
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    sessionStorage.clear()
    useCashStore.mockReturnValue(cashStoreStub)

    documentSeriesApi.available.mockResolvedValue({
      data: [
        { id: 'series-boleta', series: 'B001', document_type: 'receipt' },
        { id: 'series-factura', series: 'F001', document_type: 'invoice' },
      ],
    })
    warehousesApi.options.mockResolvedValue({
      data: [
        { id: 'wh-1', name: 'Secundario', is_default: false },
        { id: 'wh-2', name: 'Principal', is_default: true },
      ],
    })
    currenciesApi.options.mockResolvedValue({
      data: [
        { id: 'usd', code: 'USD', is_base: false },
        { id: 'pen', code: 'PEN', is_base: true },
      ],
    })
  })

  it('initPos sin cotización carga series, almacén por defecto, moneda base y la caja/sesión activas', async () => {
    const store = usePosStore()

    await store.initPos()

    expect(documentSeriesApi.available).toHaveBeenCalledTimes(1)
    expect(warehousesApi.options).toHaveBeenCalledTimes(1)
    expect(currenciesApi.options).toHaveBeenCalledTimes(1)
    expect(salesApi.prefill).not.toHaveBeenCalled()

    expect(store.availableSeries).toHaveLength(2)
    expect(store.selectedWarehouseId).toBe('wh-2')
    expect(store.currency).toEqual({ id: 'pen', code: 'PEN', is_base: true })
    expect(store.activeBox).toEqual(cashStoreStub.selectedBox)
    expect(store.activeCashSession).toEqual(cashStoreStub.currentSession)
    expect(store.isInitialized).toBe(true)
  })

  it('no vuelve a pedir las cajas disponibles si cashStore ya las tiene cargadas', async () => {
    const store = usePosStore()

    await store.initPos()

    expect(cashStoreStub.fetchAvailableBoxes).not.toHaveBeenCalled()
  })

  it('initPos con quotationId llama a salesApi.prefill y precarga el carrito', async () => {
    salesApi.prefill.mockResolvedValue({
      data: {
        quotation_id: 'quo-1',
        client: { id: 'client-1', name: 'Cliente RUC', document_type: 'RUC', document_number: '20123456789' },
        seller_id: 'seller-1',
        price_type: 'wholesale',
        items: [
          {
            catalog_item_id: 'item-1',
            item_presentation_id: 'pres-1',
            quantity: 2,
            unit_price: '50.00',
            list_price: '50.00',
            discount_percent: 0,
            tax_type: 'taxable',
            item: { name: 'Producto', code: 'ITM-001', unit_symbol: 'UND' },
            presentation: { name: 'Caja x10' },
          },
        ],
      },
    })

    const store = usePosStore()

    await store.initPos('quo-1')

    expect(salesApi.prefill).toHaveBeenCalledWith({ quotation_id: 'quo-1' })
    expect(store.quotationId).toBe('quo-1')
    expect(store.client?.id).toBe('client-1')
    expect(store.documentType).toBe('invoice')
    expect(store.cart).toHaveLength(1)
    expect(store.cart[0].catalog_item_id).toBe('item-1')
  })

  it('addLookupItem agrega un producto con prices como objeto (respuesta de lookup)', () => {
    const store = usePosStore()

    const lookupItem = {
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
    }

    store.addLookupItem(lookupItem, 1)

    expect(store.cart).toHaveLength(1)
    expect(store.cart[0].catalog_item_id).toBe('item-1')
    expect(store.cart[0].item_presentation_id).toBe('pres-1')
    expect(store.cart[0].unit_price).toBe('45')
    expect(store.cart[0].quantity).toBe(1)
  })
})
