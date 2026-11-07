/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import BanksPage from '@/pages/finance/banks/index.vue'
import { banksApi } from '@/modules/finance/services/banksApi'

vi.mock('@/modules/finance/services/banksApi', () => ({
  banksApi: {
    summary: vi.fn().mockResolvedValue({
      data: {
        by_currency: [
          {
            currency: { code: 'PEN', name: 'Sol Peruano', symbol: 'S/' },
            total: 1000,
            accounts: [{ id: 'acc-1' }],
          },
        ],
      },
    }),
    listAccounts: vi.fn().mockResolvedValue({
      data: [
        {
          id: 'acc-1',
          alias: 'BCP Soles',
          account_number: '19412345678012',
          balance: '1000.00',
          is_active: true,
          open_period: { id: 'p-1', number: 1, status: 'open' },
          bank: { name: 'BCP' },
          currency: { code: 'PEN', symbol: 'S/' },
        },
      ],
      meta: { total: 1 },
    }),
    createOperation: vi.fn().mockResolvedValue({}),
  },
}))

vi.mock('@/composables/useAcl', () => ({
  useAcl: () => ({
    can: vi.fn().mockReturnValue(true),
  }),
}))

const vuetify = createVuetify()

describe('BanksPage (index.vue)', () => {
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

  it('se monta sin errores y carga el listado de cuentas bancarias', async () => {
    const wrapper = mount(BanksPage, {
      global: {
        plugins: [vuetify],
        stubs: {
          PageHeader: { template: '<div class="page-header" />' },
          BankOperationDialog: true,
          Teleport: true,
        },
      },
    })

    await flushPromises()

    expect(banksApi.listAccounts).toHaveBeenCalled()
    expect(banksApi.summary).toHaveBeenCalled()
    expect(wrapper.text()).toContain('BCP Soles')
    expect(wrapper.text()).toContain('Periodo N° 1')
  })
})
