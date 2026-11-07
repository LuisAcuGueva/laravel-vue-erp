/**
 * Opciones compartidas para combos de la UI (spec 031).
 * El filtro «todos» se expresa limpiando el combo (`clearable`), nunca con una opción `value: null`.
 */
export const STATUS_OPTIONS = [
  { title: 'Activo', value: true },
  { title: 'Inactivo', value: false },
]

/** Afectación tributaria de una línea de documento (valores de `App\Modules\Inventory\Enums\TaxType`). */
export const TAX_TYPE_OPTIONS = [
  { title: 'Gravado', value: 'taxable' },
  { title: 'Exonerado', value: 'exempt' },
  { title: 'Inafecto', value: 'unaffected' },
]
