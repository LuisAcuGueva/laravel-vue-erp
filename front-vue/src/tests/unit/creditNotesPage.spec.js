/* eslint-disable camelcase */
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import CreditNoteLinesTable from '@/modules/sales/components/credit-notes/CreditNoteLinesTable.vue'
import CreditNoteDetailItems from '@/modules/sales/components/credit-notes/CreditNoteDetailItems.vue'
import CreditNoteInstallmentAdjustments from '@/modules/sales/components/credit-notes/CreditNoteInstallmentAdjustments.vue'
import { creditNotesApi } from '@/modules/sales/services/creditNotesApi'
import * as downloadModule from '@/utils/download'

const vuetify = createVuetify()

vi.mock('@/utils/api', () => ({
  $api: vi.fn((url, options) => Promise.resolve({ data: [], url, options })),
}))

vi.mock('@/utils/download', () => ({
  downloadFile: vi.fn(() => Promise.resolve('mock-file.pdf')),
}))

describe('Credit Notes Module', () => {
  describe('creditNotesApi', () => {
    it('llama a la ruta correcta para listar notas de crédito', async () => {
      const res = await creditNotesApi.list({ client_id: 'client-1' })

      expect(res.url).toBe('/v1/sales/credit-notes')
      expect(res.options.params).toEqual({ client_id: 'client-1' })
    })

    it('llama a la ruta correcta para summary', async () => {
      const res = await creditNotesApi.summary()

      expect(res.url).toBe('/v1/sales/credit-notes/summary')
    })

    it('llama a la ruta correcta para prefill', async () => {
      const res = await creditNotesApi.prefill('sale-123')

      expect(res.url).toBe('/v1/sales/credit-notes/prefill')
      expect(res.options.params).toEqual({ sale_id: 'sale-123' })
    })

    it('llama a la ruta correcta para obtener detalle de una nota', async () => {
      const res = await creditNotesApi.get('cn-123')

      expect(res.url).toBe('/v1/sales/credit-notes/cn-123')
    })

    it('llama a la ruta correcta para crear nota de crédito', async () => {
      const payload = { sale_id: 'sale-1', reason_code: '07' }
      const res = await creditNotesApi.create(payload)

      expect(res.url).toBe('/v1/sales/credit-notes')
      expect(res.options.method).toBe('POST')
      expect(res.options.body).toEqual(payload)
    })

    it('llama a la ruta correcta para anular nota de crédito', async () => {
      const payload = { cancel_reason: 'Error en emisión' }
      const res = await creditNotesApi.cancel('cn-123', payload)

      expect(res.url).toBe('/v1/sales/credit-notes/cn-123/cancel')
      expect(res.options.method).toBe('POST')
      expect(res.options.body).toEqual(payload)
    })

    it('llama a la ruta correcta para reintentar envío a SUNAT', async () => {
      const res = await creditNotesApi.retryEInvoice('cn-123')

      expect(res.url).toBe('/v1/sales/credit-notes/cn-123/einvoice/retry')
      expect(res.options.method).toBe('POST')
    })

    it('llama a downloadFile para descargar PDF', async () => {
      await creditNotesApi.downloadPdf('cn-123', 'ticket', 'nota-123.pdf')

      expect(downloadModule.downloadFile).toHaveBeenCalledWith(
        '/v1/sales/credit-notes/cn-123/pdf',
        'nota-123.pdf',
        { params: { format: 'ticket' } },
      )
    })
  })

  describe('CreditNoteLinesTable', () => {
    const mockLines = [
      {
        id: 'line-1',
        description: 'Producto A',
        quantity: '5.0000',
        pending_quantity: '5.0000',
        unit_price: '100.00',
        discount_percent: '0.00',
        tax_type: 'taxable',
        track_stock: true,
        unit_symbol: 'UND',
      },
    ]

    it('renderiza líneas y permite configurar cantidades', () => {
      const wrapper = mount(CreditNoteLinesTable, {
        props: {
          lines: mockLines,
          modelValue: [
            {
              sale_item_id: 'line-1',
              quantity: 2,
              amount: 0,
              return_to_stock: true,
            },
          ],
          reasonCode: '07',
          taxRate: 18,
          currencySymbol: 'S/',
        },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.text()).toContain('Producto A')
      expect(wrapper.text()).toContain('5.00 UND')
      expect(wrapper.text()).toContain('S/ 200.00')
    })
  })

  describe('CreditNoteDetailItems', () => {
    it('renderiza ítems en detalle correctamente', () => {
      const items = [
        {
          id: 'item-1',
          line: 1,
          description: 'Servicio de mantenimiento',
          quantity: '1.0000',
          unit_price: '150.00',
          discount_percent: '0.00',
          return_to_stock: false,
          line_total: '150.00',
        },
      ]

      const wrapper = mount(CreditNoteDetailItems, {
        props: {
          items,
          currencySymbol: 'S/',
        },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.text()).toContain('Servicio de mantenimiento')
      expect(wrapper.text()).toContain('S/ 150.00')
      expect(wrapper.text()).toContain('No')
    })
  })

  describe('CreditNoteInstallmentAdjustments', () => {
    it('renderiza las cuotas ajustadas con montos deducidos', () => {
      const adjustments = [
        {
          id: 'adj-1',
          installment_number: 2,
          amount: '120.00',
        },
      ]

      const wrapper = mount(CreditNoteInstallmentAdjustments, {
        props: {
          adjustments,
          currencySymbol: 'S/',
        },
        global: { plugins: [vuetify] },
      })

      expect(wrapper.text()).toContain('Cuota #2')
      expect(wrapper.text()).toContain('- S/ 120.00')
    })
  })
})
