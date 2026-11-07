<script setup>
const props = defineProps({
  summary: {
    type: Object,
    default: () => ({}),
  },
  session: {
    type: Object,
    default: null,
  },
})

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
</script>

<template>
  <VRow class="mb-2">
    <!-- Apertura -->
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <VCard border>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="42"
            color="primary"
            variant="tonal"
          >
            <VIcon
              icon="tabler-door-enter"
              size="26"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-caption text-medium-emphasis">Fondo de Apertura</span>
            <span class="text-h6 font-weight-bold">
              {{ formatCurrency(props.summary?.opening_amount ?? props.session?.opening_amount) }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Ingresos Efectivo -->
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <VCard border>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="42"
            color="success"
            variant="tonal"
          >
            <VIcon
              icon="tabler-arrow-down-left"
              size="26"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-caption text-medium-emphasis">Ingresos en Efectivo</span>
            <span class="text-h6 font-weight-bold text-success">
              +{{ formatCurrency(props.summary?.cash_in ?? 0) }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Egresos Efectivo -->
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <VCard border>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="42"
            color="error"
            variant="tonal"
          >
            <VIcon
              icon="tabler-arrow-up-right"
              size="26"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-caption text-medium-emphasis">Egresos en Efectivo</span>
            <span class="text-h6 font-weight-bold text-error">
              -{{ formatCurrency(props.summary?.cash_out ?? 0) }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Esperado en Caja -->
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <VCard border>
        <VCardText class="d-flex align-center gap-4">
          <VAvatar
            rounded
            size="42"
            color="info"
            variant="tonal"
          >
            <VIcon
              icon="tabler-cash"
              size="26"
            />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-caption text-medium-emphasis">Saldo Esperado en Caja</span>
            <span class="text-h6 font-weight-bold text-info">
              {{ formatCurrency(props.summary?.expected_cash ?? props.session?.expected_cash ?? props.session?.opening_amount) }}
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
