import { describe, expect, it } from 'vitest'
import { calculateDocumentTotals, formatAmount } from '@/utils/documentTotals'

/**
 * Casos espejo de `DocumentTotalsCalculatorTest` (backend): ambos cálculos deben coincidir.
 */
const calc = (lines, options) => calculateDocumentTotals(lines, { taxRate: '18', pricesIncludeTax: false, ...options })

describe('calculateDocumentTotals', () => {
  it('precios sin impuesto suman el impuesto encima', () => {
    const t = calc([{ quantity: '10', unitPrice: '100' }])

    expect(t.taxableBase).toBe('1000.00')
    expect(t.taxTotal).toBe('180.00')
    expect(t.total).toBe('1180.00')
    expect(t.discountTotal).toBe('0.00')
    expect(t.lines[0].total).toBe('1180.00')
  })

  it('precios con impuesto extraen la base', () => {
    const t = calc([{ quantity: '1', unitPrice: '118' }], { pricesIncludeTax: true })

    expect(t.taxableBase).toBe('100.00')
    expect(t.taxTotal).toBe('18.00')
    expect(t.total).toBe('118.00')
  })

  it('líneas exoneradas e inafectas no llevan impuesto', () => {
    const t = calc([
      { quantity: '1', unitPrice: '100', taxType: 'taxable' },
      { quantity: '1', unitPrice: '50', taxType: 'exempt' },
      { quantity: '2', unitPrice: '25', taxType: 'unaffected' },
    ])

    expect(t.taxableBase).toBe('100.00')
    expect(t.exemptBase).toBe('50.00')
    expect(t.unaffectedBase).toBe('50.00')
    expect(t.taxTotal).toBe('18.00')
    expect(t.total).toBe('218.00')
    expect(t.lines[1].tax).toBe('0.00')
  })

  it('una línea exonerada con precios con impuesto no se divide', () => {
    const t = calc([{ quantity: '1', unitPrice: '118', taxType: 'exempt' }], { pricesIncludeTax: true })

    expect(t.exemptBase).toBe('118.00')
    expect(t.taxTotal).toBe('0.00')
    expect(t.total).toBe('118.00')
  })

  it('descuento por línea en porcentaje y en monto', () => {
    const percent = calc([{ quantity: '10', unitPrice: '100', discountPercent: '5' }])

    expect(percent.discountTotal).toBe('50.00')
    expect(percent.taxableBase).toBe('950.00')
    expect(percent.taxTotal).toBe('171.00')
    expect(percent.total).toBe('1121.00')

    const amount = calc([{ quantity: '10', unitPrice: '100', discountAmount: '100' }])

    expect(amount.discountTotal).toBe('100.00')
    expect(amount.taxableBase).toBe('900.00')
  })

  it('el descuento global se prorratea por líneas antes del impuesto', () => {
    const t = calc([
      { quantity: '1', unitPrice: '100' },
      { quantity: '1', unitPrice: '300', taxType: 'exempt' },
    ], { globalDiscount: '40' })

    expect(t.taxableBase).toBe('90.00')
    expect(t.exemptBase).toBe('270.00')
    expect(t.taxTotal).toBe('16.20')
    expect(t.discountTotal).toBe('40.00')
    expect(t.total).toBe('376.20')
  })

  it('descuento global en porcentaje', () => {
    const t = calc([{ quantity: '1', unitPrice: '200' }], { globalDiscount: '10', globalDiscountType: 'percent' })

    expect(t.taxableBase).toBe('180.00')
    expect(t.discountTotal).toBe('20.00')
    expect(t.total).toBe('212.40')
  })

  it('el redondeo se cuadra en la última línea (3 líneas de 0.333333)', () => {
    const lines = Array.from({ length: 3 }, () => ({ quantity: '1', unitPrice: '0.333333' }))
    const t = calc(lines)

    expect(t.taxableBase).toBe('1.00')
    expect(t.taxTotal).toBe('0.18')
    expect(t.total).toBe('1.18')
    expect(t.lines.map(l => l.subtotal)).toEqual(['0.33', '0.33', '0.34'])
  })

  it('las líneas siempre suman el total del documento', () => {
    const lines = [
      { quantity: '3', unitPrice: '1.115', discountPercent: '7.5' },
      { quantity: '7', unitPrice: '0.995', taxType: 'exempt' },
      { quantity: '2.5', unitPrice: '19.99' },
      { quantity: '11', unitPrice: '0.0333' },
    ]

    for (const pricesIncludeTax of [false, true]) {
      const t = calc(lines, { pricesIncludeTax, globalDiscount: '1.37' })
      const sum = t.lines.reduce((acc, l) => acc + Math.round(Number(l.total) * 100), 0)

      expect(sum).toBe(Math.round(Number(t.total) * 100))
    }
  })

  it('redondea half-up', () => {
    expect(calc([{ quantity: '1', unitPrice: '0.125' }], { taxRate: '0' }).taxableBase).toBe('0.13')
    expect(calc([{ quantity: '1', unitPrice: '0.124' }], { taxRate: '0' }).taxableBase).toBe('0.12')
  })

  it('un documento vacío tiene totales en cero', () => {
    const t = calc([])

    expect(t.total).toBe('0.00')
    expect(t.lines).toEqual([])
  })

  it('el descuento no puede superar el importe bruto', () => {
    const t = calc([{ quantity: '1', unitPrice: '50', discountPercent: '150' }])

    expect(t.total).toBe('0.00')
    expect(t.discountTotal).toBe('50.00')
  })

  it('tolera valores vacíos o no numéricos como cero', () => {
    const t = calc([{ quantity: '', unitPrice: 'abc' }])

    expect(t.total).toBe('0.00')
  })
})

describe('formatAmount', () => {
  it('formatea con dos decimales por defecto y símbolo opcional', () => {
    expect(formatAmount('1180')).toMatch(/1\D?180\.00/)
    expect(formatAmount('5', 'S/')).toBe('S/ 5.00')
  })

  it('usa la cantidad de decimales de la moneda cuando se indica', () => {
    expect(formatAmount('5', '$', 0)).toBe('$ 5')
    expect(formatAmount('5.1234', '$', 4)).toBe('$ 5.1234')
    expect(formatAmount('1180.5', 'S/', 3)).toMatch(/1\D?180\.500/)
  })
})
