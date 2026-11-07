/**
 * Estados y utilidades de cotizaciones (Spec 026).
 */

export const QUOTATION_STATUS = {
  DRAFT: 'draft',
  SENT: 'sent',
  ACCEPTED: 'accepted',
  REJECTED: 'rejected',
  EXPIRED: 'expired',
  CONVERTED: 'converted',
  CANCELLED: 'cancelled',
}

export const QUOTATION_STATUS_OPTIONS = [
  { title: 'Borrador', value: QUOTATION_STATUS.DRAFT },
  { title: 'Enviada', value: QUOTATION_STATUS.SENT },
  { title: 'Aceptada', value: QUOTATION_STATUS.ACCEPTED },
  { title: 'Rechazada', value: QUOTATION_STATUS.REJECTED },
  { title: 'Vencida', value: QUOTATION_STATUS.EXPIRED },
  { title: 'Convertida', value: QUOTATION_STATUS.CONVERTED },
  { title: 'Anulada', value: QUOTATION_STATUS.CANCELLED },
]

export const PRICE_TYPE_OPTIONS = [
  { title: 'Minorista', value: 'retail' },
  { title: 'Mayorista', value: 'wholesale' },
]

export function quotationStatusColor(status) {
  switch (status) {
  case QUOTATION_STATUS.DRAFT:
    return 'secondary'
  case QUOTATION_STATUS.SENT:
    return 'info'
  case QUOTATION_STATUS.ACCEPTED:
    return 'success'
  case QUOTATION_STATUS.REJECTED:
    return 'error'
  case QUOTATION_STATUS.EXPIRED:
    return 'warning'
  case QUOTATION_STATUS.CONVERTED:
    return 'primary'
  case QUOTATION_STATUS.CANCELLED:
    return 'default'
  default:
    return 'default'
  }
}

export function quotationStatusTitle(status) {
  const match = QUOTATION_STATUS_OPTIONS.find(opt => opt.value === status)
  
  return match?.title || status || '—'
}

export function quotationStatusIcon(status) {
  switch (status) {
  case QUOTATION_STATUS.DRAFT:
    return 'tabler-file-pencil'
  case QUOTATION_STATUS.SENT:
    return 'tabler-send'
  case QUOTATION_STATUS.ACCEPTED:
    return 'tabler-check'
  case QUOTATION_STATUS.REJECTED:
    return 'tabler-x'
  case QUOTATION_STATUS.EXPIRED:
    return 'tabler-clock-x'
  case QUOTATION_STATUS.CONVERTED:
    return 'tabler-shopping-cart-check'
  case QUOTATION_STATUS.CANCELLED:
    return 'tabler-ban'
  default:
    return 'tabler-file'
  }
}
