import { describe, expect, it } from 'vitest'
import {
  EVENT_COLORS,
  EVENT_LABELS,
  EVENT_OPTIONS,
  formatEventLabel,
  getEventColor,
} from '@/modules/audit/utils/events'

describe('audit events utils', () => {
  it('traduce los eventos comunes a español con acentos correctos', () => {
    expect(formatEventLabel('created')).toBe('Creación')
    expect(formatEventLabel('updated')).toBe('Actualización')
    expect(formatEventLabel('deleted')).toBe('Eliminación')
    expect(formatEventLabel('restored')).toBe('Restauración')
  })

  it('es insensible a mayúsculas y espacios', () => {
    expect(formatEventLabel('CREATED')).toBe('Creación')
    expect(formatEventLabel(' Updated ')).toBe('Actualización')
    expect(formatEventLabel('DELETED')).toBe('Eliminación')
  })

  it('traduce eventos complementarios de auditoría', () => {
    expect(formatEventLabel('branches_synced')).toBe('Sincronización de sedes')
    expect(formatEventLabel('Branches_synced')).toBe('Sincronización de sedes')
    expect(formatEventLabel('attached')).toBe('Asociación')
    expect(formatEventLabel('detached')).toBe('Desvinculación')
    expect(formatEventLabel('synced')).toBe('Sincronización')
    expect(formatEventLabel('closed')).toBe('Cierre')
    expect(formatEventLabel('approved')).toBe('Aprobación')
    expect(formatEventLabel('rejected')).toBe('Rechazo')
    expect(formatEventLabel('cancelled')).toBe('Anulación')
  })

  it('utiliza description como fallback si event no está presente', () => {
    expect(formatEventLabel(null, 'created')).toBe('Creación')
    expect(formatEventLabel(undefined, 'updated')).toBe('Actualización')
    expect(formatEventLabel('', 'deleted')).toBe('Eliminación')
  })

  it('capitaliza eventos no mapeados y devuelve guión para valores vacíos', () => {
    expect(formatEventLabel('personalizado')).toBe('Personalizado')
    expect(formatEventLabel(null, null)).toBe('—')
    expect(formatEventLabel('', '')).toBe('—')
  })

  it('asigna colores semánticos a los eventos', () => {
    expect(getEventColor('created')).toBe('success')
    expect(getEventColor('CREATED')).toBe('success')
    expect(getEventColor('updated')).toBe('info')
    expect(getEventColor('deleted')).toBe('error')
    expect(getEventColor('restored')).toBe('warning')
    expect(getEventColor('branches_synced')).toBe('primary')
    expect(getEventColor('Branches_synced')).toBe('primary')
    expect(getEventColor('desconocido')).toBe('secondary')
  })

  it('exporta EVENT_OPTIONS para combos de filtro con valores y títulos válidos', () => {
    expect(EVENT_OPTIONS).toEqual([
      { title: 'Creación', value: 'created' },
      { title: 'Actualización', value: 'updated' },
      { title: 'Eliminación', value: 'deleted' },
      { title: 'Restauración', value: 'restored' },
    ])
  })
})
