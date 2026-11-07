/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import PosPage from '@/pages/sales/pos/index.vue'
import { usePosStore } from '@/modules/sales/stores/posStore'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    query: {},
  }),
}))

vi.mock('@/composables/useNotify', () => ({
  useNotify: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
  }),
}))

vi.mock('@/components/common/PageHeader.vue', () => ({
  default: {
    name: 'PageHeader',
    template: '<div data-testid="page-header" />',
  },
}))

vi.mock('@/components/common/CatalogItemPicker.vue', () => ({
  default: {
    name: 'CatalogItemPicker',
    template: '<div data-testid="catalog-item-picker" />',
  },
}))

vi.mock('@/components/common/ClientPicker.vue', () => ({
  default: {
    name: 'ClientPicker',
    template: '<div data-testid="client-picker" />',
  },
}))

vi.mock('@/modules/sales/components/pos/PosCartTable.vue', () => ({
  default: {
    name: 'PosCartTable',
    template: '<div data-testid="pos-cart-table" />',
  },
}))

vi.mock('@/modules/sales/components/pos/PosPaymentDialog.vue', () => ({
  default: {
    name: 'PosPaymentDialog',
    template: '<div data-testid="pos-payment-dialog" />',
  },
}))

vi.mock('@/modules/sales/components/pos/VoucherPreviewDialog.vue', () => ({
  default: {
    name: 'VoucherPreviewDialog',
    template: '<div data-testid="voucher-preview-dialog" />',
  },
}))

const vuetify = createVuetify()

describe('PosPage (pages/sales/pos/index.vue)', () => {
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
  })

  it('muestra loader y no muestra la UI ni el estado de caja mientras initPos está pendiente', async () => {
    const posStore = usePosStore()

    let resolveInit

    const pendingPromise = new Promise(resolve => {
      resolveInit = resolve
    })

    vi.spyOn(posStore, 'initPos').mockImplementation(() => pendingPromise)

    const wrapper = mount(PosPage, {
      global: {
        plugins: [vuetify],
      },
    })

    // Mientras la promesa no ha resuelto, el loader debe estar visible
    const loader = wrapper.findComponent({ name: 'VProgressCircular' })

    expect(loader.exists()).toBe(true)

    // No debe aparecer todavía la barra superior ni el chip de caja
    expect(wrapper.text()).not.toContain('Sin sesión de caja abierta')
    expect(wrapper.text()).not.toContain('Sesión #')

    // Resolver la inicialización
    posStore.isInitialized = true
    posStore.activeCashSession = { id: 's-1', number: 10 }
    posStore.activeBox = { id: 'b-1', name: 'Caja 1' }
    resolveInit()
    await flushPromises()

    // El loader desaparece y aparece la UI con la sesión activa
    expect(wrapper.findComponent({ name: 'VProgressCircular' }).exists()).toBe(false)
    expect(wrapper.text()).toContain('Caja 1 · Sesión #10')
  })

  it('muestra la advertencia de sin sesión solo después de que initPos resuelve sin sesión', async () => {
    const posStore = usePosStore()

    vi.spyOn(posStore, 'initPos').mockImplementation(async () => {
      posStore.isInitialized = true
      posStore.activeCashSession = null
    })

    const wrapper = mount(PosPage, {
      global: {
        plugins: [vuetify],
      },
    })

    await flushPromises()

    expect(wrapper.findComponent({ name: 'VProgressCircular' }).exists()).toBe(false)
    expect(wrapper.text()).toContain('Sin sesión de caja abierta (Solo transferencias/crédito)')
  })

  it('muestra tarjeta de error con reintento si initPos falla', async () => {
    const posStore = usePosStore()

    vi.spyOn(posStore, 'initPos').mockRejectedValue(new Error('Network error'))

    const wrapper = mount(PosPage, {
      global: {
        plugins: [vuetify],
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Error al cargar los datos del Punto de Venta')
    expect(wrapper.text()).toContain('Reintentar')
  })

  it('agrega el producto al carrito cuando CatalogItemPicker emite selected', async () => {
    const posStore = usePosStore()

    vi.spyOn(posStore, 'initPos').mockImplementation(async () => {
      posStore.isInitialized = true
      posStore.warehouses = [{ id: 'wh-1', name: 'Principal' }]
      posStore.selectedWarehouseId = 'wh-1'
    })

    const wrapper = mount(PosPage, {
      global: {
        plugins: [vuetify],
      },
    })

    await flushPromises()

    const picker = wrapper.findComponent({ name: 'CatalogItemPicker' })

    expect(picker.exists()).toBe(true)

    const mockItem = {
      presentation_id: 'pres-1',
      catalog_item_id: 'item-1',
      code: 'ITM-001',
      name: 'Aceite Sintético',
      prices: { retail: 45.0, wholesale: 38.0 },
    }

    await picker.vm.$emit('selected', mockItem)
    await flushPromises()

    expect(posStore.cart).toHaveLength(1)
    expect(posStore.cart[0].catalog_item_id).toBe('item-1')
    expect(posStore.cart[0].unit_price).toBe('45')
  })
})
