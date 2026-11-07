/* eslint-disable camelcase */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import InstallmentPlanEditor from '@/modules/sales/components/receivables/InstallmentPlanEditor.vue'
import RescheduleDialog from '@/modules/sales/components/receivables/RescheduleDialog.vue'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import * as downloadModule from '@/utils/download'

const vuetify = createVuetify()

beforeEach(() => {
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

vi.mock('@/utils/api', () => ({
  $api: vi.fn((url, options) => Promise.resolve({ data: [], url, options })),
}))

vi.mock('@/utils/download', () => ({
  downloadFile: vi.fn(() => Promise.resolve('mock-file.csv')),
}))

describe('Receivables Module', () => {
  describe('receivablesApi', () => {
    it('llama a la ruta correcta para listar cuotas', async () => {
      const res = await receivablesApi.list({ client_id: 'client-1' })

      expect(res.url).toBe('/v1/sales/receivables')
      expect(res.options.params).toEqual({ client_id: 'client-1' })
    })

    it('llama a la ruta correcta para aging', async () => {
      const res = await receivablesApi.aging({ as_of: '2026-10-20' })

      expect(res.url).toBe('/v1/sales/receivables/aging')
      expect(res.options.params).toEqual({ as_of: '2026-10-20' })
    })

    it('llama a downloadFile para exportar cuotas', async () => {
      await receivablesApi.export({ status: 'pending' })

      expect(downloadModule.downloadFile).toHaveBeenCalledWith(
        '/v1/sales/receivables/export',
        { status: 'pending' },
        'cuotas-por-cobrar.csv',
      )
    })

    it('llama a la ruta correcta para registrar cobranza', async () => {
      const payload = { amount: 150.0, client_id: 'c-1' }
      const res = await receivablesApi.createCollection(payload)

      expect(res.url).toBe('/v1/sales/collections')
      expect(res.options.method).toBe('POST')
      expect(res.options.body).toEqual(payload)
    })

    it('llama a la ruta correcta para anular cobranza', async () => {
      const res = await receivablesApi.cancelCollection('col-123', { cancellation_reason: 'Error en monto' })

      expect(res.url).toBe('/v1/sales/collections/col-123/cancel')
      expect(res.options.method).toBe('POST')
      expect(res.options.body).toEqual({ cancellation_reason: 'Error en monto' })
    })

    it('llama a la ruta correcta para reprogramar cuotas de una venta', async () => {
      const payload = { installments: [] }
      const res = await receivablesApi.rescheduleInstallments('sale-123', payload)

      expect(res.url).toBe('/v1/sales/sales/sale-123/reschedule')
      expect(res.options.method).toBe('POST')
      expect(res.options.body).toEqual(payload)
    })

    it('llama a la ruta correcta para el estado de cuenta del cliente', async () => {
      const res = await receivablesApi.clientStatement('client-123', { from: '2026-10-01', to: '2026-10-20' })

      expect(res.url).toBe('/v1/clients/client-123/statement')
      expect(res.options.params).toEqual({ from: '2026-10-01', to: '2026-10-20' })
    })
  })

  describe('InstallmentPlanEditor', () => {
    it('divide el saldo equitativamente emitiendo el plan actualizado', async () => {
      const wrapper = mount(InstallmentPlanEditor, {
        props: {
          balance: 300,
          creditDays: 30,
          currencySymbol: 'S/',
        },
        global: { plugins: [vuetify] },
      })

      const emitted = wrapper.emitted('update:plan')

      expect(emitted).toBeTruthy()

      const latestPlan = emitted[emitted.length - 1][0]

      expect(latestPlan.installments_count).toBe(1)
      expect(latestPlan.installment_interval_days).toBe(30)
      expect(latestPlan.installments.length).toBe(1)
      expect(latestPlan.installments[0].amount).toBe(300)
    })
  })

  describe('RescheduleDialog', () => {
    it('calcula correctamente el saldo pendiente y divide en cuotas automáticas sin mostrar 0.00', async () => {
      const mockSale = {
        id: 'sale-1',
        full_number: 'F001-000001',
        balance: '150.00',
        total: '150.00',
        currency_symbol: 'S/',
        installments: [
          {
            id: 'inst-1',
            number: 1,
            due_date: '2026-11-20',
            amount: '150.00',
            paid_amount: '0.00',
            balance: '150.00',
            status: 'pending',
          },
        ],
      }

      const wrapper = mount(RescheduleDialog, {
        props: {
          modelValue: true,
          sale: mockSale,
        },
        global: { plugins: [vuetify] },
      })

      // En Vuetify los VDialog se teletransportan al body
      expect(document.body.textContent).toContain('150.00')
      expect(document.body.textContent).not.toContain('Diferencia:')

      // Al hacer clic en "Generar" con 2 cuotas, debe recalcular a 75.00 cada una (no 0.00)
      const generateBtn = Array.from(document.body.querySelectorAll('button')).find(b => b.textContent.includes('Generar'))

      expect(generateBtn).toBeTruthy()
      generateBtn.click()
      await wrapper.vm.$nextTick()

      // Verificamos que se dividió en 2 cuotas de 75.00 en los inputs correspondientes
      const numberInputs = Array.from(document.body.querySelectorAll('input[type="number"]'))
      const amountInputs = numberInputs.filter(i => i.value === '75.00')

      expect(amountInputs.length).toBe(2)
      expect(document.body.textContent).not.toContain('Diferencia:')
    })
  })
})
