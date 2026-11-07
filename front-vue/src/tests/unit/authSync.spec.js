import { afterEach, describe, expect, it, vi } from 'vitest'
import { getCsrfCookie } from '@/utils/csrfCookie'
import { withRefreshLock } from '@/utils/authSync'

function clearCookie() {
  document.cookie = 'csrf_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
}

describe('csrfCookie', () => {
  afterEach(clearCookie)

  it('lee el CSRF de la cookie csrf_token', () => {
    document.cookie = 'other=1; path=/'
    document.cookie = 'csrf_token=abc123; path=/'

    expect(getCsrfCookie()).toBe('abc123')
  })

  it('devuelve null si la cookie no existe', () => {
    expect(getCsrfCookie()).toBeNull()
  })
})

describe('withRefreshLock', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('usa navigator.locks cuando está disponible', async () => {
    const request = vi.fn((name, fn) => fn())

    vi.stubGlobal('navigator', { locks: { request } })

    const result = await withRefreshLock(async () => 'ok')

    expect(request).toHaveBeenCalledWith('auth-refresh', expect.any(Function))
    expect(result).toBe('ok')
  })

  it('ejecuta la función directamente sin Web Locks', async () => {
    vi.stubGlobal('navigator', {})

    expect(await withRefreshLock(async () => 'directo')).toBe('directo')
  })
})
