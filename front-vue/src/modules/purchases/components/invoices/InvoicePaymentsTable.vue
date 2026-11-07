<script setup>
import { supplierPaymentStatusColor, supplierPaymentStatusTitle } from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Pagos aplicados a una factura de proveedor (una fila por asignación a cuota).
 */
const props = defineProps({
  payments: {
    type: Array,
    default: () => [],
  },
  installments: {
    type: Array,
    default: () => [],
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
  currencyDecimals: {
    type: Number,
    default: 2,
  },
})

const installmentNumber = id => props.installments.find(i => i.id === id)?.number ?? '—'
</script>

<template>
  <VAlert
    v-if="!props.payments.length"
    type="info"
    variant="tonal"
    density="compact"
  >
    Esta factura aún no tiene pagos.
  </VAlert>

  <VTable
    v-else
    density="comfortable"
  >
    <thead>
      <tr>
        <th>PAGO</th>
        <th>FECHA</th>
        <th>CUOTA</th>
        <th class="text-end">
          APLICADO
        </th>
        <th class="text-center">
          ESTADO
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="row in props.payments"
        :key="row.allocation_id"
      >
        <td class="font-monospace">
          <RouterLink
            v-if="row.payment"
            :to="{ name: 'purchases-supplier-payments-id', params: { id: row.payment.id } }"
            class="text-primary text-decoration-none"
          >
            {{ row.payment.number }}
          </RouterLink>
        </td>
        <td>{{ formatDateDMY(row.payment?.payment_date) }}</td>
        <td>{{ installmentNumber(row.payable_installment_id) }}</td>
        <td class="text-end font-weight-medium">
          {{ formatAmount(row.amount, props.currencySymbol, props.currencyDecimals) }}
        </td>
        <td class="text-center">
          <VChip
            size="small"
            class="text-uppercase"
            :color="supplierPaymentStatusColor(row.payment?.status)"
          >
            {{ supplierPaymentStatusTitle(row.payment?.status) }}
          </VChip>
        </td>
      </tr>
    </tbody>
  </VTable>
</template>
