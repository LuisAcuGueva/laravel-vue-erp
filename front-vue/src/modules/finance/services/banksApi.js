import { $api } from '@/utils/api'
import { openFile } from '@/utils/download'

export const banksApi = {
  // Resumen global por moneda
  summary() {
    return $api('/v1/finance/bank-accounts/summary', {
      method: 'GET',
    })
  },

  // Cuentas bancarias
  listAccounts(params = {}) {
    return $api('/v1/finance/bank-accounts', {
      method: 'GET',
      params,
    })
  },

  getAccount(id) {
    return $api(`/v1/finance/bank-accounts/${id}`, {
      method: 'GET',
    })
  },

  // Periodos bancarios
  listPeriods(accountId) {
    return $api(`/v1/finance/bank-accounts/${accountId}/periods`, {
      method: 'GET',
    })
  },

  openInitialPeriod(accountId, payload) {
    return $api(`/v1/finance/bank-accounts/${accountId}/periods`, {
      method: 'POST',
      body: payload,
    })
  },

  closePeriod(periodId, payload) {
    return $api(`/v1/finance/bank-periods/${periodId}/close`, {
      method: 'POST',
      body: payload,
    })
  },

  // Movimientos bancarios
  listMovements(accountId, params = {}) {
    return $api(`/v1/finance/bank-accounts/${accountId}/movements`, {
      method: 'GET',
      params,
    })
  },

  createMovement(accountId, formData) {
    return $api(`/v1/finance/bank-accounts/${accountId}/movements`, {
      method: 'POST',
      body: formData,
    })
  },

  cancelMovement(movementId, reason) {
    return $api(`/v1/finance/bank-movements/${movementId}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  openVoucher(movementId) {
    return openFile(`/v1/finance/bank-movements/${movementId}/voucher`)
  },

  // Operaciones bancarias (depósito, retiro, transferencia)
  listOperations(params = {}) {
    return $api('/v1/finance/bank-operations', {
      method: 'GET',
      params,
    })
  },

  getOperation(id) {
    return $api(`/v1/finance/bank-operations/${id}`, {
      method: 'GET',
    })
  },

  createOperation(formData) {
    return $api('/v1/finance/bank-operations', {
      method: 'POST',
      body: formData,
    })
  },

  cancelOperation(operationId, reason) {
    return $api(`/v1/finance/bank-operations/${operationId}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  openOperationVoucher(operationId) {
    return openFile(`/v1/finance/bank-operations/${operationId}/voucher`)
  },
}

export default banksApi
