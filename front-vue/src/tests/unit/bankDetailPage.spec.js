/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import BankDetailPage from '@/pages/finance/banks/[id].vue'
import { banksApi } from '@/modules/finance/services/banksApi'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { id: '01a17934-4c5d-7316-8f1e-cc8b782d793f' },
  }),
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

vi.mock('@/modules/finance/services/banksApi', () => ({
  banksApi: {
    getAccount: vi.fn().mockResolvedValue({
      data: {
        id: '01a17934-4c5d-7316-8f1e-cc8b782d793f',
        alias: 'BBVA Soles',
        account_number: '001108140200123456',
        balance: '10520.20',
        is_active: true,
        bank: { name: 'BBVA Perú' },
        currency: { code: 'PEN', symbol: 'S/', name: 'Sol Peruano' },
      },
    }),
    listPeriods: vi.fn().mockResolvedValue({
      data: [
        {
          id: 'p-1',
          number: 1,
          status: 'open',
          opened_at: '2026-10-26',
          opening_balance: 10000,
          closed_at: null,
          created_at: '2026-10-26T17:17:18.000000Z',
          updated_at: '2026-10-26T17:17:18.000000Z',
        },
      ],
      meta: { total: 1 },
    }),
    listMovements: vi.fn().mockResolvedValue({
      data: {
        opening: 10000,
        closing: 9280.20,
        movements: [
          {
            id: 'm-1',
            direction: 'out',
            amount: 719.80,
            balance_after: 9280.20,
            operation_number: '123456',
            movement_date: '2026-10-26',
            description: 'Pago a proveedor',
            counterparty_name: 'Distribuidora S.A.',
            voucher_url: null,
            is_manual: false,
            created_at: '2026-10-26T17:17:33.000000Z',
            concept: { id: 'c-1', name: 'Pago a proveedor' },
          },
        ],
      },
      meta: { total: 1 },
    }),
    listOperations: vi.fn().mockResolvedValue({
      data: [
        {
          id: 'op-1',
          number: 'BO-000001',
          type: 'deposit',
          type_label: 'Depósito en banco',
          status: 'completed',
          amount: 1000,
          currency_code: 'PEN',
          operation_number: '112233',
          operation_date: '2026-10-26',
          created_at: '2026-10-26T17:48:08.000000Z',
        },
      ],
      meta: { total: 1 },
    }),
  },
}))

vi.mock('@/composables/useAcl', () => ({
  useAcl: () => ({
    can: vi.fn().mockReturnValue(true),
  }),
}))

const vuetify = createVuetify()

describe('BankDetailPage ([id].vue)', () => {
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

  it('formatea la fecha y hora en la tabla de movimientos', async () => {
    const wrapper = mount(BankDetailPage, {
      global: {
        plugins: [vuetify],
        stubs: {
          PageHeader: { template: '<div class="page-header" />' },
          BankMovementDialog: true,
          CloseBankPeriodDialog: true,
          CancelMovementDialog: true,
          Teleport: true,
        },
      },
    })

    await flushPromises()

    // En la tabla de movimientos debe aparecer la fecha formateada (26-10-2026) y la hora (con a. m. / p. m.)
    expect(wrapper.text()).toContain('26-10-2026')
    expect(wrapper.text()).toMatch(/26-10-2026\s+\d{1,2}:\d{2}\s+(a\.\s*m\.|p\.\s*m\.)/i)
  })

  it('formatea la fecha y hora en la tabla de periodos', async () => {
    const wrapper = mount(BankDetailPage, {
      global: {
        plugins: [vuetify],
        stubs: {
          PageHeader: { template: '<div class="page-header" />' },
          BankMovementDialog: true,
          CloseBankPeriodDialog: true,
          CancelMovementDialog: true,
          Teleport: true,
        },
      },
    })

    await flushPromises()

    // Cambiar a la pestaña de periodos
    const tabs = wrapper.findAllComponents({ name: 'VTab' })
    const periodTab = tabs.find(t => t.text().includes('Periodos'))

    expect(periodTab).toBeDefined()
    await periodTab.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('26-10-2026')
    expect(wrapper.text()).toMatch(/26-10-2026\s+\d{1,2}:\d{2}\s+(a\.\s*m\.|p\.\s*m\.)/i)
  })

  it('formatea la fecha y hora en la tabla de operaciones', async () => {
    const wrapper = mount(BankDetailPage, {
      global: {
        plugins: [vuetify],
        stubs: {
          PageHeader: { template: '<div class="page-header" />' },
          BankMovementDialog: true,
          CloseBankPeriodDialog: true,
          CancelMovementDialog: true,
          Teleport: true,
        },
      },
    })

    await flushPromises()

    // Cambiar a la pestaña de operaciones
    const tabs = wrapper.findAllComponents({ name: 'VTab' })
    const operationTab = tabs.find(t => t.text().includes('Operaciones'))

    expect(operationTab).toBeDefined()
    await operationTab.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('BO-000001')
    expect(wrapper.text()).toContain('26-10-2026')
    expect(wrapper.text()).toMatch(/26-10-2026\s+\d{1,2}:\d{2}\s+(a\.\s*m\.|p\.\s*m\.)/i)
  })
})
