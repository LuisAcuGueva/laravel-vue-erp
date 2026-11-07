<script setup>
import { supplierInvoiceStatusColor, supplierInvoiceStatusTitle } from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Cronograma de cuotas de una factura de proveedor con su saldo y estado.
 */
const props = defineProps({
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
</script>

<template>
  <VTable density="comfortable">
    <thead>
      <tr>
        <th>CUOTA</th>
        <th>VENCIMIENTO</th>
        <th class="text-end">
          MONTO
        </th>
        <th class="text-end">
          PAGADO
        </th>
        <th class="text-end">
          SALDO
        </th>
        <th class="text-center">
          ESTADO
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="installment in props.installments"
        :key="installment.id"
      >
        <td>{{ installment.number }}</td>
        <td :class="{ 'text-error font-weight-medium': installment.is_overdue }">
          {{ formatDateDMY(installment.due_date) }}
          <span
            v-if="installment.is_overdue"
            class="text-caption"
          >
            ({{ installment.days_overdue }} días vencida)
          </span>
        </td>
        <td class="text-end">
          {{ formatAmount(installment.amount, props.currencySymbol, props.currencyDecimals) }}
        </td>
        <td class="text-end">
          {{ formatAmount(installment.paid_amount, props.currencySymbol, props.currencyDecimals) }}
        </td>
        <td class="text-end font-weight-medium">
          {{ formatAmount(installment.balance, props.currencySymbol, props.currencyDecimals) }}
        </td>
        <td class="text-center">
          <VChip
            size="small"
            class="text-uppercase"
            :color="supplierInvoiceStatusColor(installment.status)"
          >
            {{ supplierInvoiceStatusTitle(installment.status) }}
          </VChip>
        </td>
      </tr>
    </tbody>
  </VTable>
</template>
