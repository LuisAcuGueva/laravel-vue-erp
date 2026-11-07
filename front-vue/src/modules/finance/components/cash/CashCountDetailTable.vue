<script setup>
const props = defineProps({
  countDetails: {
    type: Array,
    default: () => [],
  },
  totalCounted: {
    type: Number,
    default: 0,
  },
})

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <VCard border>
    <VCardItem>
      <template #prepend>
        <VIcon
          icon="tabler-coins"
          class="me-2 text-primary"
        />
      </template>
      <VCardTitle class="text-subtitle-1">
        Detalle Físico del Arqueo de Caja
      </VCardTitle>
    </VCardItem>

    <VCardText class="pa-0">
      <VTable
        density="compact"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th class="text-uppercase text-caption font-weight-bold">
              Denominación
            </th>
            <th class="text-uppercase text-caption font-weight-bold text-center">
              Tipo
            </th>
            <th class="text-uppercase text-caption font-weight-bold text-center">
              Cantidad
            </th>
            <th class="text-uppercase text-caption font-weight-bold text-end">
              Subtotal
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in props.countDetails"
            :key="item.id || item.denomination"
          >
            <td>
              <span class="font-weight-medium font-monospace">
                {{ Number(item.denomination) >= 1 ? `S/ ${Number(item.denomination).toFixed(2)}` : `${(Number(item.denomination) * 100).toFixed(0)} céntimos` }}
              </span>
            </td>
            <td class="text-center">
              <VChip
                size="x-small"
                variant="tonal"
                :color="Number(item.denomination) >= 10 ? 'success' : 'warning'"
              >
                {{ Number(item.denomination) >= 10 ? 'Billete' : 'Moneda' }}
              </VChip>
            </td>
            <td class="text-center font-monospace">
              {{ item.quantity }}
            </td>
            <td class="text-end font-monospace font-weight-bold">
              {{ formatCurrency(item.subtotal) }}
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td
              colspan="3"
              class="text-end font-weight-bold text-caption text-uppercase"
            >
              Total Contado:
            </td>
            <td class="text-end font-weight-bold font-monospace text-primary">
              {{ formatCurrency(props.totalCounted) }}
            </td>
          </tr>
        </tfoot>
      </VTable>
    </VCardText>
  </VCard>
</template>
