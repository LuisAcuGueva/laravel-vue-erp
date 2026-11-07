<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  installments: {
    type: Array,
    default: () => [],
  },
  sale: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['reschedule'])

const { can } = useAcl()

const currencySymbol = computed(() => props.sale?.currency?.symbol || props.sale?.currency_symbol || 'S/')

const installmentBadge = status => {
  switch (status) {
  case 'paid':
    return { text: 'Pagada', color: 'success' }
  case 'partially_paid':
    return { text: 'Pago Parcial', color: 'info' }
  case 'pending':
    return { text: 'Pendiente', color: 'warning' }
  case 'cancelled':
    return { text: 'Anulada', color: 'secondary' }
  default:
    return { text: status, color: 'default' }
  }
}
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between pa-4">
      <div class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-calendar-dollar"
          size="20"
        />
        <span class="text-subtitle-1 font-weight-bold">Cronograma de Cuotas a Crédito</span>
      </div>

      <VBtn
        v-if="can('receivables.reschedule') && sale.status !== 'cancelled'"
        size="small"
        variant="tonal"
        color="primary"
        prepend-icon="tabler-calendar-time"
        @click="emit('reschedule')"
      >
        Reprogramar Cuotas
      </VBtn>
    </VCardTitle>

    <VCardText class="pa-0">
      <VTable density="comfortable">
        <thead>
          <tr>
            <th class="text-center">
              CUOTA
            </th>
            <th>FECHA VENCIMIENTO</th>
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
          <tr v-if="!installments.length">
            <td
              colspan="6"
              class="text-center py-4 text-medium-emphasis"
            >
              No hay cuotas registradas para esta venta.
            </td>
          </tr>
          <tr
            v-for="inst in installments"
            :key="inst.id"
          >
            <td class="text-center font-mono font-weight-bold">
              #{{ inst.installment_number || inst.number }}
            </td>
            <td class="font-mono">
              <span :class="{ 'text-error font-weight-bold': inst.is_overdue }">
                {{ formatDateDMY(inst.due_date) }}
              </span>
              <span
                v-if="inst.is_overdue"
                class="text-caption text-error ms-2"
              >
                (Vencida)
              </span>
            </td>
            <td class="text-end font-mono">
              {{ currencySymbol }} {{ formatAmount(inst.amount) }}
            </td>
            <td class="text-end font-mono text-medium-emphasis">
              {{ currencySymbol }} {{ formatAmount(inst.paid_amount) }}
            </td>
            <td class="text-end font-mono font-weight-bold">
              {{ currencySymbol }} {{ formatAmount(inst.balance) }}
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                variant="tonal"
                :color="installmentBadge(inst.status).color"
              >
                {{ installmentBadge(inst.status).text }}
              </VChip>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
