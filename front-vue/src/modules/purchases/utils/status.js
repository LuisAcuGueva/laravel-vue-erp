/* eslint-disable camelcase */
/**
 * Estados y prioridades de requerimientos y órdenes de compra (espejo de los enums del backend).
 */

export const REQUEST_STATUS = {
  draft: { title: 'Borrador', color: 'secondary' },
  submitted: { title: 'Enviado', color: 'info' },
  approved: { title: 'Aprobado', color: 'success' },
  rejected: { title: 'Rechazado', color: 'error' },
  partially_ordered: { title: 'Parcialmente ordenado', color: 'warning' },
  ordered: { title: 'Ordenado', color: 'primary' },
  cancelled: { title: 'Anulado', color: 'error' },
}

export const ORDER_STATUS = {
  draft: { title: 'Borrador', color: 'secondary' },
  pending_approval: { title: 'Pendiente de aprobación', color: 'warning' },
  approved: { title: 'Aprobada', color: 'success' },
  rejected: { title: 'Rechazada', color: 'error' },
  partially_received: { title: 'Parcialmente recibida', color: 'info' },
  received: { title: 'Recibida', color: 'primary' },
  closed: { title: 'Cerrada', color: 'secondary' },
  cancelled: { title: 'Anulada', color: 'error' },
}

const toOptions = map => Object.entries(map).map(([value, { title }]) => ({ title, value }))

export const REQUEST_STATUS_OPTIONS = toOptions(REQUEST_STATUS)
export const ORDER_STATUS_OPTIONS = toOptions(ORDER_STATUS)

export const RECEIPT_STATUS = {
  draft: { title: 'Borrador', color: 'secondary' },
  confirmed: { title: 'Confirmada', color: 'success' },
  cancelled: { title: 'Anulada', color: 'error' },
}

export const RECEIPT_STATUS_OPTIONS = toOptions(RECEIPT_STATUS)

export const PROVIDER_DOCUMENT_TYPES = {
  guide: { title: 'Guía de remisión', value: 'guide' },
  invoice: { title: 'Factura', value: 'invoice' },
  other: { title: 'Otro', value: 'other' },
}

export const PROVIDER_DOCUMENT_TYPE_OPTIONS = Object.values(PROVIDER_DOCUMENT_TYPES)

export const PRIORITY_OPTIONS = [
  { title: 'Normal', value: 'normal' },
  { title: 'Urgente', value: 'urgent' },
]

export const PAYMENT_CONDITION_OPTIONS = [
  { title: 'Contado', value: 'cash' },
  { title: 'Crédito', value: 'credit' },
]

export const requestStatusColor = status => REQUEST_STATUS[status]?.color || 'secondary'
export const requestStatusTitle = status => REQUEST_STATUS[status]?.title || status
export const orderStatusColor = status => ORDER_STATUS[status]?.color || 'secondary'
export const orderStatusTitle = status => ORDER_STATUS[status]?.title || status
export const receiptStatusColor = status => RECEIPT_STATUS[status]?.color || 'secondary'
export const receiptStatusTitle = status => RECEIPT_STATUS[status]?.title || status
export const providerDocumentTypeTitle = type => PROVIDER_DOCUMENT_TYPES[type]?.title || type

export const SUPPLIER_INVOICE_STATUS = {
  pending: { title: 'Pendiente', color: 'warning' },
  partially_paid: { title: 'Pago parcial', color: 'info' },
  paid: { title: 'Pagada', color: 'success' },
  cancelled: { title: 'Anulada', color: 'error' },
}

export const SUPPLIER_INVOICE_STATUS_OPTIONS = toOptions(SUPPLIER_INVOICE_STATUS)

export const SUPPLIER_INVOICE_KIND_OPTIONS = [
  { title: 'Compra', value: 'purchase' },
  { title: 'Gasto', value: 'expense' },
]

export const SUPPLIER_DOCUMENT_TYPE_OPTIONS = [
  { title: 'Factura', value: 'invoice' },
  { title: 'Boleta de venta', value: 'receipt' },
  { title: 'Recibo por honorarios', value: 'fee_receipt' },
  { title: 'Nota de venta', value: 'sales_note' },
  { title: 'Otro', value: 'other' },
]

export const SUPPLIER_PAYMENT_STATUS = {
  completed: { title: 'Registrado', color: 'success' },
  cancelled: { title: 'Anulado', color: 'error' },
}

export const SUPPLIER_PAYMENT_STATUS_OPTIONS = toOptions(SUPPLIER_PAYMENT_STATUS)

export const supplierInvoiceStatusColor = status => SUPPLIER_INVOICE_STATUS[status]?.color || 'secondary'
export const supplierInvoiceStatusTitle = status => SUPPLIER_INVOICE_STATUS[status]?.title || status
export const supplierInvoiceKindTitle = kind => SUPPLIER_INVOICE_KIND_OPTIONS.find(o => o.value === kind)?.title || kind
export const supplierDocumentTypeTitle = type => SUPPLIER_DOCUMENT_TYPE_OPTIONS.find(o => o.value === type)?.title || type
export const supplierPaymentStatusColor = status => SUPPLIER_PAYMENT_STATUS[status]?.color || 'secondary'
export const supplierPaymentStatusTitle = status => SUPPLIER_PAYMENT_STATUS[status]?.title || status
