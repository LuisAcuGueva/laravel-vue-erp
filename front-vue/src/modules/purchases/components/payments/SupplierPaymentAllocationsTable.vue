<script setup>
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Cuotas liquidadas por un pago a proveedor (una fila por asignación a cuota).
 */
const props = defineProps({
  allocations: {
    type: Array,
    default: () => [],
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
})
</script>

<template>
  <VAlert
    v-if="!props.allocations.length"
    type="info"
    variant="tonal"
    density="compact"
  >
    Sin cuotas liquidadas.
  </VAlert>

  <VTable
    v-else
    density="compact"
  >
    <thead>
      <tr>
        <th>FACTURA</th>
        <th>CUOTA</th>
        <th>VENCIMIENTO</th>
        <th class="text-end">
          MONTO APLICADO
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="allocation in props.allocations"
        :key="allocation.id"
      >
        <td>
          <RouterLink
            v-if="allocation.invoice"
            :to="{ name: 'purchases-supplier-invoices-id', params: { id: allocation.invoice.id } }"
            class="font-weight-medium font-monospace text-primary text-decoration-none"
          >
            {{ allocation.invoice.document_label }}
          </RouterLink>
        </td>
        <td>{{ allocation.installment?.number }}</td>
        <td>{{ formatDateDMY(allocation.installment?.due_date) }}</td>
        <td class="text-end font-weight-medium">
          {{ formatAmount(allocation.amount, props.currencySymbol) }}
        </td>
      </tr>
    </tbody>
  </VTable>
</template>
