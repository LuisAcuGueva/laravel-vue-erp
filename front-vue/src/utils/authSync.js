const LOCK_NAME = 'auth-refresh'
const CHANNEL_NAME = 'auth'

/**
 * Serializa el refresh de sesión entre pestañas (Web Locks API) para que dos pestañas
 * no roten el refresh token a la vez. Sin soporte, ejecuta la función directamente.
 */
export function withRefreshLock(fn) {
  if (typeof navigator !== 'undefined' && navigator.locks?.request)
    return navigator.locks.request(LOCK_NAME, fn)

  return fn()
}

function openChannel() {
  if (typeof BroadcastChannel === 'undefined')
    return null

  return new BroadcastChannel(CHANNEL_NAME)
}

/** Avisa a las demás pestañas que esta cerró sesión. */
export function broadcastLogout() {
  const channel = openChannel()
  if (!channel)
    return

  channel.postMessage({ type: 'logout' })
  channel.close()
}

/**
 * Escucha cierres de sesión de otras pestañas.
 * @returns {() => void} función para dejar de escuchar
 */
export function onRemoteLogout(handler) {
  const channel = openChannel()
  if (!channel)
    return () => {}

  channel.onmessage = event => {
    if (event.data?.type === 'logout')
      handler()
  }

  return () => channel.close()
}
