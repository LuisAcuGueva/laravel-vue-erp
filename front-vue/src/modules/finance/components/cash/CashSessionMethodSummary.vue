<script setup>
const props = defineProps({
  summary: {
    type: Object,
    default: () => ({}),
  },
})

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <VRow>
    <!-- Métodos de Pago -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard border>
        <VCardItem>
          <template #prepend>
            <VIcon
              icon="tabler-credit-card"
              class="me-2 text-primary"
            />
          </template>
          <VCardTitle class="text-subtitle-1">
            Resumen por Método de Pago
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
                  Método
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-end">
                  Ingresos
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-end">
                  Egresos
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-end">
                  Neto
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in props.summary?.by_payment_method || []"
                :key="item.method?.id"
              >
                <td>
                  <span class="font-weight-medium">{{ item.method?.name }}</span>
                </td>
                <td class="text-end text-success font-monospace">
                  +{{ formatCurrency(item.in) }}
                </td>
                <td class="text-end text-error font-monospace">
                  -{{ formatCurrency(item.out) }}
                </td>
                <td class="text-end font-monospace font-weight-bold">
                  {{ formatCurrency(item.net) }}
                </td>
              </tr>
              <tr v-if="!props.summary?.by_payment_method?.length">
                <td
                  colspan="4"
                  class="text-center text-disabled py-4"
                >
                  Sin movimientos registrados
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Conceptos -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard border>
        <VCardItem>
          <template #prepend>
            <VIcon
              icon="tabler-receipt"
              class="me-2 text-primary"
            />
          </template>
          <VCardTitle class="text-subtitle-1">
            Resumen por Concepto
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
                  Concepto
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-center">
                  Dirección
                </th>
                <th class="text-uppercase text-caption font-weight-bold text-end">
                  Total
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in props.summary?.by_concept || []"
                :key="item.concept?.id"
              >
                <td>
                  <span class="font-weight-medium">{{ item.concept?.name }}</span>
                </td>
                <td class="text-center">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    :color="item.direction === 'income' ? 'success' : (item.direction === 'expense' ? 'error' : 'info')"
                  >
                    {{ item.direction === 'income' ? 'Ingreso' : (item.direction === 'expense' ? 'Egreso' : 'Ambos') }}
                  </VChip>
                </td>
                <td class="text-end font-monospace font-weight-bold">
                  {{ formatCurrency(item.total) }}
                </td>
              </tr>
              <tr v-if="!props.summary?.by_concept?.length">
                <td
                  colspan="3"
                  class="text-center text-disabled py-4"
                >
                  Sin movimientos registrados
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
