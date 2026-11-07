/**
 * Utilidades para formateo de fechas.
 *
 * Formato estándar: dd-mm-yyyy.
 */

/**
 * Formatea una fecha como dd-mm-yyyy.
 * Si el valor es nulo o vacío, devuelve '—'.
 *
 * Evita el desfase de zona horaria parseando directamente cadenas con formato de fecha pura (YYYY-MM-DD o medianoche UTC).
 *
 * @param {string | Date | null | undefined} value
 * @returns {string}
 */
export const formatDateDMY = value => {
  if (!value)
    return '—'

  if (typeof value === 'string') {
    // YYYY-MM-DD o YYYY-MM-DDT00:00:00...
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s]00:00:00(?:\.0+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/)
    if (match)
      return `${match[3]}-${match[2]}-${match[1]}`
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return String(value)

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()

  return `${day}-${month}-${year}`
}

/**
 * Formatea una fecha y hora como dd-mm-yyyy HH:mm (por defecto 24h) o 12h con hour12: true.
 *
 * @param {string | Date | null | undefined} value
 * @param {{ hour12?: boolean }} [options]
 * @returns {string}
 */
export const formatDateTime = (value, options = {}) => {
  if (!value)
    return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return String(value)

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()

  if (options.hour12) {
    const time = date.toLocaleTimeString('es-PE', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    })

    return `${day}-${month}-${year} ${time}`
  }

  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  return `${day}-${month}-${year} ${hours}:${minutes}`
}

/**
 * Formatea una fecha y hora en formato 12 horas (dd-mm-yyyy hh:mm a. m./p. m.).
 *
 * @param {string | Date | null | undefined} value
 * @returns {string}
 */
export const formatDateTime12h = value => formatDateTime(value, { hour12: true })
