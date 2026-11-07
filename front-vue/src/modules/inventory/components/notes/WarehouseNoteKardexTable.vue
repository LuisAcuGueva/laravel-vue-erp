<script setup>
/* eslint-disable camelcase */
import { formatDateDMY } from '@/utils/dates'

defineProps({
  movements: {
    type: Array,
    default: () => [],
  },
})

const formatCurrency = val => {
  const num = Number(val || 0)
  
  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <VCard
    v-if="movements.length > 0"
    title="Movimientos en Kardex"
    class="mt-6"
  >
    <VCardText class="pa-0">
      <VTable density="compact">
        <thead>
          <tr>
            <th>Fecha / Hora</th>
            <th>Dirección</th>
            <th>Presentación</th>
            <th class="text-end">
              Cantidad
            </th>
            <th class="text-end">
              Costo Unit.
            </th>
            <th class="text-end">
              Total
            </th>
            <th class="text-end">
              Saldo Cant.
            </th>
            <th class="text-end">
              Saldo Total
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="km in movements"
            :key="km.id"
          >
            <td>{{ formatDateDMY(km.moved_at) }}</td>
            <td>
              <VChip
                size="x-small"
                :color="km.direction === 'in' ? 'success' : 'warning'"
                variant="tonal"
              >
                {{ km.direction === 'in' ? 'Entrada' : 'Salida' }}
              </VChip>
            </td>
            <td>{{ km.presentation?.name || '—' }}</td>
            <td
              class="text-end font-weight-medium"
              :class="km.direction === 'in' ? 'text-success' : 'text-warning'"
            >
              {{ km.direction === 'in' ? '+' : '-' }}{{ km.presentation_quantity ?? km.quantity }}
            </td>
            <td class="text-end">
              {{ formatCurrency(km.unit_cost) }}
            </td>
            <td class="text-end">
              {{ formatCurrency(km.total_cost) }}
            </td>
            <td class="text-end text-medium-emphasis">
              {{ km.balance_quantity }}
            </td>
            <td class="text-end text-medium-emphasis">
              {{ formatCurrency(km.balance_total_cost) }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>
</template>
