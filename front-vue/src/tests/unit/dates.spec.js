import { describe, expect, it } from 'vitest'
import { formatDateDMY, formatDateTime, formatDateTime12h } from '@/utils/dates'

describe('dates utils', () => {
  describe('formatDateDMY', () => {
    it('formatea fechas YYYY-MM-DD a dd-mm-yyyy', () => {
      expect(formatDateDMY('2026-10-22')).toBe('22-10-2026')
      expect(formatDateDMY('2026-01-05')).toBe('05-01-2026')
    })

    it('formatea fechas con medianoche UTC sin desfase horario', () => {
      expect(formatDateDMY('2026-10-22T00:00:00.000000Z')).toBe('22-10-2026')
      expect(formatDateDMY('2026-05-09T00:00:00Z')).toBe('09-05-2026')
    })

    it('devuelve guión para valores nulos o vacíos', () => {
      expect(formatDateDMY(null)).toBe('—')
      expect(formatDateDMY(undefined)).toBe('—')
      expect(formatDateDMY('')).toBe('—')
    })

    it('formatea instancias de Date', () => {
      const d = new Date(2026, 9, 22)

      expect(formatDateDMY(d)).toBe('22-10-2026')
    })
  })

  describe('formatDateTime', () => {
    it('devuelve guión para valores nulos', () => {
      expect(formatDateTime(null)).toBe('—')
    })

    it('formatea fecha y hora', () => {
      const d = new Date(2026, 9, 22, 14, 30)

      expect(formatDateTime(d)).toBe('22-10-2026 14:30')
    })

    it('formatea fecha y hora en formato 12 horas', () => {
      const d = new Date(2026, 9, 22, 11, 14)

      expect(formatDateTime(d, { hour12: true })).toBe('22-10-2026 11:14 a. m.')
      expect(formatDateTime12h(d)).toBe('22-10-2026 11:14 a. m.')
    })
  })
})
