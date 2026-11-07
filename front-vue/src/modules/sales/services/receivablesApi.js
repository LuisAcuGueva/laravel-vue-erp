import { $api } from '@/utils/api'
import { downloadFile } from '@/utils/download'

export const receivablesApi = {
  /**
   * Listado paginado de cuotas por cobrar con filtros
   */
  list(params = {}) {
    return $api('/v1/sales/receivables', {
      method: 'GET',
      params,
    })
  },

  /**
   * Exporta las cuotas por cobrar en CSV
   */
  export(params = {}) {
    return downloadFile('/v1/sales/receivables/export', params, 'cuotas-por-cobrar.csv')
  },

  /**
   * Reporte de antigüedad de deuda por cliente y moneda
   */
  aging(params = {}) {
    return $api('/v1/sales/receivables/aging', {
      method: 'GET',
      params,
    })
  },

  /**
   * Listado paginado del historial de cobranzas
   */
  listCollections(params = {}) {
    return $api('/v1/sales/collections', {
      method: 'GET',
      params,
    })
  },

  /**
   * Exporta el historial de cobranzas en CSV
   */
  exportCollections(params = {}) {
    return downloadFile('/v1/sales/collections/export', params, 'cobranzas.csv')
  },

  /**
   * Registra una nueva cobranza con asignación manual o automática FIFO
   */
  createCollection(data) {
    return $api('/v1/sales/collections', {
      method: 'POST',
      body: data,
    })
  },

  /**
   * Anula una cobranza y revierte saldos y liquidación financiera
   */
  cancelCollection(id, data) {
    return $api(`/v1/sales/collections/${id}/cancel`, {
      method: 'POST',
      body: data,
    })
  },

  /**
   * Descarga el recibo de cobranza en formato PDF
   */
  downloadReceiptPdf(id) {
    return downloadFile(`/v1/sales/collections/${id}/pdf`, {}, `recibo-cobranza-${id}.pdf`)
  },

  /**
   * Reprograma el cronograma de cuotas de una venta a crédito
   */
  rescheduleInstallments(saleId, data) {
    return $api(`/v1/sales/sales/${saleId}/reschedule`, {
      method: 'POST',
      body: data,
    })
  },

  /**
   * Obtiene el Estado de Cuenta de un cliente con saldo inicial, movimientos y saldo acumulado
   */
  clientStatement(clientId, params = {}) {
    return $api(`/v1/clients/${clientId}/statement`, {
      method: 'GET',
      params,
    })
  },

  /**
   * Descarga el PDF del Estado de Cuenta de un cliente
   */
  downloadClientStatementPdf(clientId, params = {}) {
    return downloadFile(`/v1/clients/${clientId}/statement/pdf`, params, `estado-cuenta-${clientId}.pdf`)
  },
}

export default receivablesApi
