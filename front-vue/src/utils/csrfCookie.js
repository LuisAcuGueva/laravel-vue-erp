export const CSRF_COOKIE_NAME = 'csrf_token'

/**
 * Lee el CSRF vigente de la cookie `csrf_token` (double-submit).
 * El refresh token es compartido por todas las pestañas, así que su CSRF también:
 * la cookie siempre corresponde al último refresh token emitido, sin importar qué pestaña lo rotó.
 * @returns {string|null}
 */
export function getCsrfCookie() {
  if (typeof document === 'undefined' || !document.cookie)
    return null

  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${CSRF_COOKIE_NAME}=([^;]*)`))

  return match ? decodeURIComponent(match[1]) : null
}
