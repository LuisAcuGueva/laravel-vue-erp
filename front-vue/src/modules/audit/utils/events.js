/* eslint-disable camelcase */
export const EVENT_LABELS = {
  created: 'Creación',
  create: 'Creación',
  updated: 'Actualización',
  update: 'Actualización',
  deleted: 'Eliminación',
  delete: 'Eliminación',
  restored: 'Restauración',
  restore: 'Restauración',
  branches_synced: 'Sincronización de sedes',
  branch_synced: 'Sincronización de sedes',
  branches_sync: 'Sincronización de sedes',
  attached: 'Asociación',
  attach: 'Asociación',
  detached: 'Desvinculación',
  detach: 'Desvinculación',
  synced: 'Sincronización',
  sync: 'Sincronización',
  closed: 'Cierre',
  close: 'Cierre',
  opened: 'Apertura',
  open: 'Apertura',
  approved: 'Aprobación',
  approve: 'Aprobación',
  rejected: 'Rechazo',
  reject: 'Rechazo',
  cancelled: 'Anulación',
  canceled: 'Anulación',
  cancel: 'Anulación',
  login: 'Inicio de sesión',
  logout: 'Cierre de sesión',
}

export const EVENT_OPTIONS = [
  { title: 'Creación', value: 'created' },
  { title: 'Actualización', value: 'updated' },
  { title: 'Eliminación', value: 'deleted' },
  { title: 'Restauración', value: 'restored' },
]

export const EVENT_COLORS = {
  created: 'success',
  create: 'success',
  updated: 'info',
  update: 'info',
  deleted: 'error',
  delete: 'error',
  restored: 'warning',
  restore: 'warning',
  branches_synced: 'primary',
  branch_synced: 'primary',
  branches_sync: 'primary',
  attached: 'primary',
  detached: 'warning',
  synced: 'primary',
  sync: 'primary',
  closed: 'secondary',
  opened: 'info',
  approved: 'success',
  rejected: 'error',
  cancelled: 'error',
  canceled: 'error',
  login: 'primary',
  logout: 'secondary',
}

export const formatEventLabel = (event, fallback = '') => {
  const raw = event || fallback
  if (!raw)
    return '—'

  const key = String(raw).toLowerCase().trim()
  if (EVENT_LABELS[key])
    return EVENT_LABELS[key]

  const str = String(raw).replace(/_/g, ' ').trim()

  return str.charAt(0).toUpperCase() + str.slice(1)
}

export const getEventColor = (event, fallback = '') => {
  const key = String(event || fallback || '').toLowerCase().trim()

  return EVENT_COLORS[key] || 'secondary'
}

/** `App\Models\Warehouse`, `warehouse` o `bank_account` → `Warehouse`, `Warehouse`, `BankAccount`. */
export const formatSubjectType = fullType => {
  if (!fullType)
    return '—'

  const lastPart = fullType.split('\\').pop() || fullType

  return lastPart
    .split('_')
    .filter(Boolean)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join('')
}
