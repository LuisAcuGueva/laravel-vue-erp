/* eslint-disable camelcase */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createVuetify } from 'vuetify'
import CashTransfersPage from '@/pages/finance/cash/transfers/index.vue'
import { cashTransfersApi } from '@/modules/finance/services/cashTransfersApi'

vi.mock('@/modules/finance/services/cashTransfersApi', () => ({
  cashTransfersApi: {
    list: vi.fn().mockResolvedValue({
      data: [
        {
          id: 'ct-1',
          number: 'TRF-000001',
          amount: '150.00',
          status: 'pending',
          status_label: 'Pendiente',
          status_color: 'warning',
          reason: 'Fondo de cambio',
          created_at: '2026-10-20T10:00:00Z',
          source_box: { name: 'Caja Principal' },
          target_box: { name: 'Caja Secundaria' },
        },
      ],
      meta: { total: 1 },
    }),
    pendingCount: vi.fn().mockResolvedValue({ data: { count: 3 } }),
    get: vi.fn().mockResolvedValue({ data: {} }),
    approve: vi.fn().mockResolvedValue({}),
    reject: vi.fn().mockResolvedValue({}),
    cancel: vi.fn().mockResolvedValue({}),
  },
}))

vi.mock('@/composables/useAcl', () => ({
  useAcl: () => ({
    can: vi.fn().mockReturnValue(true),
  }),
}))

const vuetify = createVuetify()

describe('CashTransfersPage (index.vue)', () => {
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

  it('se monta sin errores y carga el listado mediante useServerTable', async () => {
    const wrapper = mount(CashTransfersPage, {
      global: {
        plugins: [vuetify],
        stubs: {
          PageHeader: true,
          ServerDataTable: {
            props: ['options', 'search', 'items', 'totalItems', 'loading', 'headers'],
            template: '<div class="stubbed-server-data-table"><slot name="filters" /><slot name="item.number" :item="{ number: \'TRF-000001\' }" /></div>',
          },
          CashTransferDetailDialog: true,
          CashTransferActionDialog: true,
          RowActionButton: true,
        },
      },
    })

    await flushPromises()

    expect(cashTransfersApi.list).toHaveBeenCalledWith(
      expect.objectContaining({
        page: 1,
        per_page: 10,
        status: 'pending',
      }),
    )
    expect(wrapper.exists()).toBe(true)
  })
})

