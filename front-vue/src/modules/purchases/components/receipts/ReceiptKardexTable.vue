<script setup>
/* eslint-disable camelcase */
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

defineProps({
  movements: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <VTable density="compact">
    <thead>
      <tr>
        <th>FECHA / HORA</th>
        <th>MOVIMIENTO</th>
        <th>DIRECCIÓN</th>
        <th class="text-end">
          CANTIDAD
        </th>
        <th class="text-end">
          COSTO UNIT.
        </th>
        <th class="text-end">
          TOTAL
        </th>
        <th class="text-end">
          SALDO CANT.
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="km in movements"
        :key="km.id"
      >
        <td>{{ formatDateDMY(km.moved_at) }}</td>
        <td>{{ km.reason?.name || km.movement_type }}</td>
        <td>
          <VChip
            size="x-small"
            :color="km.direction === 'in' ? 'success' : 'warning'"
            variant="tonal"
          >
            {{ km.direction === 'in' ? 'Entrada' : 'Salida' }}
          </VChip>
        </td>
        <td
          class="text-end font-weight-medium"
          :class="km.direction === 'in' ? 'text-success' : 'text-warning'"
        >
          {{ km.direction === 'in' ? '+' : '-' }}{{ km.presentation_quantity ?? km.quantity }}
        </td>
        <td class="text-end">
          {{ formatAmount(km.unit_cost, 'S/') }}
        </td>
        <td class="text-end font-weight-medium">
          {{ formatAmount(km.total_cost, 'S/') }}
        </td>
        <td class="text-end text-medium-emphasis">
          {{ km.balance_quantity }}
        </td>
      </tr>
    </tbody>
  </VTable>
</template>
