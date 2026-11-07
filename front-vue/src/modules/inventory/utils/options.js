/**
 * Contenedor de la página de configuración donde las pestañas montan sus drawers.
 * Debe quedar fuera de `VWindow`: su `overflow: hidden` y sus `transform` atrapan al drawer
 * (el scrim no cubre la pantalla y la barra superior queda encima).
 */
export const SETTINGS_DRAWERS_TARGET = '#inventory-settings-drawers'

/** Tipos de motivo de almacén (`WarehouseReasonType`). */
export const WAREHOUSE_REASON_TYPE_OPTIONS = [
  { value: 'in', title: 'Ingreso' },
  { value: 'out', title: 'Salida' },
  { value: 'transfer', title: 'Traslado' },
  { value: 'adjustment', title: 'Ajuste' },
]

export const WAREHOUSE_REASON_TYPE_COLORS = {
  in: 'success',
  out: 'warning',
  transfer: 'info',
  adjustment: 'secondary',
}

/** Niveles del árbol de categorías (`depth` 0, 1 y 2). */
export const CATEGORY_LEVEL_OPTIONS = [
  { value: 0, title: 'Nivel 1' },
  { value: 1, title: 'Nivel 2' },
  { value: 2, title: 'Nivel 3' },
]
