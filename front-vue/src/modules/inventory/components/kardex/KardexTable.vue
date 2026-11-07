<script setup>
/* eslint-disable camelcase */
defineProps({
  kardexData: {
    type: Object,
    required: true,
  },
  dateFrom: {
    type: String,
    required: true,
  },
  dateTo: {
    type: String,
    required: true,
  },
})

const MOVEMENT_TYPE_LABELS = {
  opening: 'Saldo inicial',
  purchase: 'Compra',
  purchase_return: 'Devolución de compra',
  sale: 'Venta',
  sale_return: 'Devolución de venta',
  transfer_in: 'Entrada por traslado',
  transfer_out: 'Salida por traslado',
  adjustment_in: 'Ajuste de ingreso',
  adjustment_out: 'Ajuste de salida',
  note_in: 'Nota de ingreso',
  note_out: 'Nota de salida',
  reversal_in: 'Reversión (Entrada)',
  reversal_out: 'Reversión (Salida)',
}

const formatMovementType = type => MOVEMENT_TYPE_LABELS[type] || type

const SOURCE_DOCUMENT_ROUTES = {
  sale: id => `/sales/history/${id}`,
  credit_note: id => `/sales/credit-notes/${id}`,
  purchase_receipt: id => `/purchases/receipts/${id}`,
  warehouse_note: id => `/inventory/warehouse-notes/${id}`,
  inventory_count: id => `/inventory/counts/${id}`,
}

const documentLink = m => {
  const buildRoute = SOURCE_DOCUMENT_ROUTES[m.source_type]

  return m.source_id && buildRoute ? buildRoute(m.source_id) : null
}

// YYYY-MM-DD → DD-MM-YYYY sin pasar por Date (evita el desfase de zona horaria)
const formatDate = value => {
  const [year, month, day] = (value || '').split('-')

  return year && month && day ? `${day}-${month}-${year}` : value
}

const formatDateTime = isoString => {
  if (!isoString) return '—'
  const date = new Date(isoString)
  if (isNaN(date.getTime())) return isoString

  return date.toLocaleString('es-PE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <VTable
    class="text-no-wrap kardex-table"
    density="comfortable"
  >
    <thead>
      <tr>
        <th
          rowspan="2"
          class="text-left font-weight-bold"
        >
          Fecha / Hora
        </th>
        <th
          rowspan="2"
          class="text-left font-weight-bold"
        >
          Tipo Movimiento
        </th>
        <th
          rowspan="2"
          class="text-left font-weight-bold"
        >
          Documento
        </th>
        <th
          rowspan="2"
          class="text-left font-weight-bold"
        >
          Motivo
        </th>
        <th
          colspan="3"
          class="text-center font-weight-bold border-start border-end group-header"
        >
          ENTRADAS
        </th>
        <th
          colspan="3"
          class="text-center font-weight-bold border-end group-header"
        >
          SALIDAS
        </th>
        <th
          colspan="3"
          class="text-center font-weight-bold group-header"
        >
          SALDO EXISTENCIAS
        </th>
      </tr>
      <tr>
        <th class="text-right border-start">
          Cant.
        </th>
        <th class="text-right">
          C.U.
        </th>
        <th class="text-right border-end">
          Total
        </th>

        <th class="text-right">
          Cant.
        </th>
        <th class="text-right">
          C.U.
        </th>
        <th class="text-right border-end">
          Total
        </th>

        <th class="text-right font-weight-bold">
          Cant.
        </th>
        <th class="text-right font-weight-bold">
          C.P.
        </th>
        <th class="text-right font-weight-bold">
          Total
        </th>
      </tr>
    </thead>
    <tbody>
      <!-- Fila de Saldo Inicial -->
      <tr class="initial-balance-row">
        <td
          colspan="4"
          class="text-primary font-weight-bold"
        >
          <VIcon
            icon="tabler-player-play"
            size="16"
            class="me-1"
          />
          SALDO INICIAL AL {{ formatDate(dateFrom) }}
        </td>
        <td
          colspan="3"
          class="text-center text-disabled border-start border-end"
        >
          —
        </td>
        <td
          colspan="3"
          class="text-center text-disabled border-end"
        >
          —
        </td>
        <td class="text-right font-weight-bold text-primary">
          {{ Number(kardexData.opening_balance.quantity).toFixed(2) }}
        </td>
        <td class="text-right font-weight-bold text-primary">
          S/ {{ Number(kardexData.opening_balance.unit_cost).toFixed(4) }}
        </td>
        <td class="text-right font-weight-bold text-primary">
          S/ {{ Number(kardexData.opening_balance.total).toFixed(2) }}
        </td>
      </tr>

      <!-- Lista de Movimientos -->
      <tr
        v-for="m in kardexData.movements"
        :key="m.id"
      >
        <td class="text-caption">
          {{ formatDateTime(m.moved_at) }}
        </td>
        <td>
          <VChip
            :color="m.direction === 'in' ? 'success' : 'warning'"
            size="small"
            variant="tonal"
          >
            {{ formatMovementType(m.movement_type) }}
          </VChip>
        </td>
        <td>
          <div class="d-flex flex-column">
            <RouterLink
              v-if="documentLink(m)"
              :to="documentLink(m)"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ m.source_number }}
            </RouterLink>
            <span
              v-else
              class="font-weight-medium"
            >{{ m.source_number }}</span>
            <span class="text-caption text-medium-emphasis">{{ m.source_type }}</span>
          </div>
        </td>
        <td>
          <span class="text-caption">{{ m.reason?.name || m.reason?.code || '—' }}</span>
        </td>

        <!-- Entradas -->
        <td class="text-right border-start">
          <span v-if="m.direction === 'in'">{{ Number(m.quantity).toFixed(2) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>
        <td class="text-right">
          <span v-if="m.direction === 'in'">S/ {{ Number(m.unit_cost).toFixed(4) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>
        <td class="text-right border-end">
          <span
            v-if="m.direction === 'in'"
            class="font-weight-medium"
          >S/ {{ Number(m.total_cost).toFixed(2) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>

        <!-- Salidas -->
        <td class="text-right">
          <span v-if="m.direction === 'out'">{{ Number(m.quantity).toFixed(2) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>
        <td class="text-right">
          <span v-if="m.direction === 'out'">S/ {{ Number(m.unit_cost).toFixed(4) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>
        <td class="text-right border-end">
          <span
            v-if="m.direction === 'out'"
            class="font-weight-medium"
          >S/ {{ Number(m.total_cost).toFixed(2) }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </td>

        <!-- Saldo -->
        <td class="text-right font-weight-medium">
          {{ Number(m.balance_quantity).toFixed(2) }}
        </td>
        <td class="text-right text-caption">
          S/ {{ Number(m.balance_unit_cost).toFixed(4) }}
        </td>
        <td class="text-right font-weight-bold text-high-emphasis">
          S/ {{ Number(m.balance_total_cost).toFixed(2) }}
        </td>
      </tr>

      <!-- Si no hay movimientos en el período -->
      <tr v-if="kardexData.movements.length === 0">
        <td
          colspan="13"
          class="text-center py-4 text-medium-emphasis"
        >
          No se registraron movimientos en el período seleccionado.
        </td>
      </tr>

      <!-- Saldo Final de Cierre -->
      <tr class="closing-balance-row">
        <td
          colspan="10"
          class="text-right font-weight-bold"
        >
          SALDO FINAL AL {{ formatDate(dateTo) }}:
        </td>
        <td class="text-right font-weight-bold text-primary">
          {{ Number(kardexData.closing_balance.quantity).toFixed(2) }}
        </td>
        <td class="text-right font-weight-bold text-primary">
          S/ {{ Number(kardexData.closing_balance.unit_cost).toFixed(4) }}
        </td>
        <td class="text-right font-weight-bold text-primary">
          S/ {{ Number(kardexData.closing_balance.total).toFixed(2) }}
        </td>
      </tr>
    </tbody>
  </VTable>
</template>

<style scoped>
.kardex-table th,
.kardex-table td {
  padding-block: 8px;
  padding-inline: 12px;
}

.group-header {
  background-color: rgba(var(--v-theme-on-surface), var(--v-hover-opacity));
}

.initial-balance-row,
.closing-balance-row {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
