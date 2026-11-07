/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import BankMovementDialog from '@/modules/finance/components/banks/BankMovementDialog.vue'
import { conceptsApi } from '@/modules/finance/services/financeSettingsApi'

vi.mock('@/modules/finance/services/financeSettingsApi', () => ({
  conceptsApi: {
    options: vi.fn(),
  },
}))

const vuetify = createVuetify()

describe('BankMovementDialog.vue', () => {
  beforeAll(() => {
    globalThis.ResizeObserver ??= class {
      observe() {}
      unobserve() {}
      disconnect() {}
    }
    window.visualViewport ??= {
      width: 1024,
      height: 768,
      offsetLeft: 0,
      offsetTop: 0,
      pageLeft: 0,
      pageTop: 0,
      scale: 1,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    }
  })

  beforeEach(() => {
    vi.clearAllMocks()
    conceptsApi.options.mockImplementation(params => {
      if (params?.direction === 'in') {
        return Promise.resolve({
          data: [
            { id: 'c-in-1', name: 'Intereses ganados', direction: 'income', is_system: false },
            { id: 'c-in-sys', name: 'Cobro de venta', direction: 'income', is_system: true },
          ],
        })
      }

      return Promise.resolve({
        data: [
          { id: 'c-out-1', name: 'Comisiones bancarias', direction: 'expense', is_system: false },
          { id: 'c-out-sys', name: 'Pago a proveedor', direction: 'expense', is_system: true },
        ],
      })
    })
  })

  it('carga los conceptos de egreso al abrir el diálogo', async () => {
    const wrapper = mount(BankMovementDialog, {
      props: {
        isDialogOpen: true,
        account: {
          id: 'acc-1',
          alias: 'BCP Soles',
          account_number: '19412345678012',
          currency: { symbol: 'S/' },
        },
      },
      global: {
        plugins: [vuetify],
      },
    })

    await flushPromises()

    expect(conceptsApi.options).toHaveBeenCalledWith({ direction: 'out' })

    const autocomplete = wrapper.findComponent({ name: 'VAutocomplete' })

    expect(autocomplete.exists()).toBe(true)

    // Debe excluir el concepto de sistema
    expect(autocomplete.props('items')).toEqual([
      { id: 'c-out-1', name: 'Comisiones bancarias', direction: 'expense', is_system: false },
    ])
  })

  it('actualiza los conceptos al cambiar la dirección a ingreso', async () => {
    const wrapper = mount(BankMovementDialog, {
      props: {
        isDialogOpen: true,
        account: {
          id: 'acc-1',
          alias: 'BCP Soles',
          account_number: '19412345678012',
          currency: { symbol: 'S/' },
        },
      },
      global: {
        plugins: [vuetify],
      },
    })

    await flushPromises()

    // Cambiar dirección a 'in'
    const radioGroup = wrapper.findComponent({ name: 'VRadioGroup' })

    await radioGroup.vm.$emit('update:modelValue', 'in')
    await flushPromises()

    expect(conceptsApi.options).toHaveBeenCalledWith({ direction: 'in' })

    const autocomplete = wrapper.findComponent({ name: 'VAutocomplete' })

    expect(autocomplete.props('items')).toEqual([
      { id: 'c-in-1', name: 'Intereses ganados', direction: 'income', is_system: false },
    ])
  })
})
