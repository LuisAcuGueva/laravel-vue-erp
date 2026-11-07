<script setup>
import { computed } from 'vue'
import { formatDateTime12h } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  payments: {
    type: Array,
    default: () => [],
  },
  sale: {
    type: Object,
    required: true,
  },
})

const currencySymbol = computed(() => props.sale?.currency_symbol || 'S/')
</script>

<template>
  <VCard title="Registro de Cobros y Pagos">
    <VTable
      v-if="payments.length"
      hover
      density="comfortable"
    >
      <thead>
        <tr>
          <th style="min-width: 180px">
            MÉTODO DE PAGO
          </th>
          <th style="min-width: 150px">
            CAJA / SESIÓN
          </th>
          <th style="min-width: 140px">
            REFERENCIA
          </th>
          <th
            class="text-right"
            style="width: 130px"
          >
            RECIBIDO
          </th>
          <th
            class="text-right"
            style="width: 120px"
          >
            VUELTO
          </th>
          <th
            class="text-right"
            style="width: 140px"
          >
            COBRADO
          </th>
          <th
            class="text-center"
            style="width: 160px"
          >
            FECHA
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="pm in payments"
          :key="pm.id"
        >
          <td>
            <div class="font-weight-medium">
              {{ pm.payment_method_name || 'Efectivo' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ pm.is_cash ? 'Pago en efectivo' : 'Operación bancaria / digital' }}
            </div>
          </td>

          <td>
            <div class="text-body-2">
              {{ pm.box_name || 'Caja General' }}
            </div>
            <div
              v-if="pm.cash_session_id"
              class="text-caption font-mono"
            >
              <RouterLink
                :to="`/finance/cash/sessions/${pm.cash_session_id}`"
                class="text-primary text-decoration-none"
              >
                Sesión #{{ pm.cash_session_id.slice(0, 8) }}
              </RouterLink>
            </div>
          </td>

          <td class="font-mono text-body-2">
            {{ pm.reference || '—' }}
          </td>

          <td class="text-right font-mono">
            {{ pm.received_amount != null ? formatAmount(pm.received_amount, currencySymbol) : '—' }}
          </td>

          <td class="text-right font-mono text-medium-emphasis">
            {{ pm.change_amount != null ? formatAmount(pm.change_amount, currencySymbol) : '—' }}
          </td>

          <td class="text-right font-mono font-weight-bold text-success">
            {{ formatAmount(pm.amount, currencySymbol) }}
          </td>

          <td class="text-center font-mono text-caption text-no-wrap">
            {{ formatDateTime12h(pm.created_at) }}
          </td>
        </tr>
      </tbody>
    </VTable>

    <div
      v-else
      class="pa-8 text-center text-medium-emphasis"
    >
      <VIcon
        icon="tabler-receipt-off"
        size="36"
        class="mb-2"
      />
      <div class="text-body-1 font-weight-medium">
        Sin cobros registrados
      </div>
      <p class="text-caption mb-0">
        Esta venta fue emitada al crédito o no cuenta con cobros liquidados aún.
      </p>
    </div>

    <VDivider />

    <VCardText class="d-flex flex-wrap justify-space-between align-center gap-4">
      <div>
        <span class="text-body-2 text-medium-emphasis me-2">Condición de Venta:</span>
        <VChip
          size="small"
          :color="sale.payment_condition === 'credit' ? 'warning' : 'info'"
          variant="tonal"
        >
          {{ sale.payment_condition_label || (sale.payment_condition === 'credit' ? 'Crédito' : 'Contado') }}
          <span
            v-if="sale.payment_condition === 'credit' && sale.credit_days"
            class="ms-1"
          >
            ({{ sale.credit_days }} días)
          </span>
        </VChip>
      </div>

      <div class="d-flex align-center gap-6">
        <div>
          <span class="text-caption text-medium-emphasis d-block">Total Cobrado</span>
          <span class="text-h6 font-weight-bold font-mono text-success">
            {{ formatAmount(sale.paid_amount || 0, currencySymbol) }}
          </span>
        </div>

        <div>
          <span class="text-caption text-medium-emphasis d-block">Saldo Pendiente</span>
          <span
            class="text-h6 font-weight-bold font-mono"
            :class="Number(sale.balance) > 0 ? 'text-warning' : 'text-medium-emphasis'"
          >
            {{ formatAmount(sale.balance || 0, currencySymbol) }}
          </span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
