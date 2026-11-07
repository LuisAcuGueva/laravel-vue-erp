<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  expectedCash: {
    type: Number,
    default: 0,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const DENOMINATIONS = [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]

const quantities = ref({})
const closingNotes = ref('')

const resetForm = () => {
  const initial = {}

  DENOMINATIONS.forEach(d => {
    initial[d] = 0
  })
  quantities.value = initial
  closingNotes.value = ''
  clearErrors()
}

watch(() => props.isDialogOpen, isOpen => {
  if (isOpen)
    resetForm()
}, { immediate: true })

const formatCurrency = val => {
  const num = Number(val || 0)

  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const denominationRows = computed(() => {
  return DENOMINATIONS.map(denom => {
    const qty = Math.max(0, parseInt(quantities.value[denom] || 0, 10) || 0)
    const subtotal = Number((denom * qty).toFixed(2))

    return {
      denomination: denom,
      label: denom >= 1 ? `S/ ${denom}` : `${(denom * 100).toFixed(0)} céntimos`,
      isBill: denom >= 10,
      quantity: qty,
      subtotal,
    }
  })
})

const totalCounted = computed(() => {
  const sum = denominationRows.value.reduce((acc, row) => acc + row.subtotal, 0)

  return Number(sum.toFixed(2))
})

const difference = computed(() => {
  const diff = totalCounted.value - props.expectedCash

  return Number(diff.toFixed(2))
})

const hasDifference = computed(() => Math.abs(difference.value) >= 0.01)

const handleClose = () => {
  if (props.isSubmitting)
    return
  emit('update:isDialogOpen', false)
  clearErrors()
}

const handleSubmit = () => {
  clearErrors()

  const count = denominationRows.value
    .filter(row => row.quantity > 0)
    .map(row => ({
      denomination: row.denomination,
      quantity: row.quantity,
    }))

  emit('submit', {
    count,
    'counted_cash': totalCounted.value,
    'closing_notes': closingNotes.value ? closingNotes.value.trim() : null,
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="680"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="border-b">
        <template #prepend>
          <VIcon
            icon="tabler-lock"
            color="primary"
            class="me-2"
          />
        </template>
        <VCardTitle>Arqueo y Cierre de Turno de Caja</VCardTitle>
        <VCardSubtitle>
          Conteo físico de billetes y monedas para el cierre de la sesión
        </VCardSubtitle>
        <template #append>
          <VBtn
            icon
            variant="text"
            size="small"
            aria-label="Cerrar"
            :disabled="props.isSubmitting"
            @click="handleClose"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="pt-4">
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <!-- Resumen de Arqueo en Vivo -->
        <VCard
          variant="tonal"
          color="primary"
          class="mb-4"
        >
          <VCardText class="pa-4">
            <VRow>
              <VCol
                cols="12"
                sm="4"
                class="text-center"
              >
                <div class="text-caption text-medium-emphasis">
                  Saldo Esperado en Efectivo
                </div>
                <div class="text-h6 font-weight-bold">
                  {{ formatCurrency(props.expectedCash) }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
                class="text-center"
              >
                <div class="text-caption text-medium-emphasis">
                  Total Físico Contado
                </div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ formatCurrency(totalCounted) }}
                </div>
              </VCol>

              <VCol
                cols="12"
                sm="4"
                class="text-center"
              >
                <div class="text-caption text-medium-emphasis">
                  Diferencia de Arqueo
                </div>
                <div
                  class="text-h6 font-weight-bold"
                  :class="!hasDifference ? 'text-success' : (difference > 0 ? 'text-info' : 'text-error')"
                >
                  <span v-if="!hasDifference">Cuadre exacto (S/ 0.00)</span>
                  <span v-else-if="difference > 0">+{{ formatCurrency(difference) }} (Sobrante)</span>
                  <span v-else>{{ formatCurrency(difference) }} (Faltante)</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Grilla de Denominaciones -->
        <div class="mb-4">
          <h6 class="text-subtitle-2 font-weight-bold mb-2">
            Desglose por Denominación
          </h6>
          <VRow density="compact">
            <VCol
              v-for="denom in DENOMINATIONS"
              :key="denom"
              cols="12"
              sm="6"
              md="4"
            >
              <div class="d-flex align-center gap-2 pa-2 border rounded">
                <VIcon
                  :icon="denom >= 10 ? 'tabler-cash' : 'tabler-coin'"
                  size="22"
                  :color="denom >= 10 ? 'success' : 'warning'"
                />
                <div style="inline-size: 75px;">
                  <span class="text-caption font-weight-medium">
                    {{ denom >= 1 ? `S/ ${denom}` : `${(denom * 100).toFixed(0)}¢` }}
                  </span>
                </div>
                <VTextField
                  v-model.number="quantities[denom]"
                  type="number"
                  min="0"
                  density="compact"
                  hide-details
                  style="max-inline-size: 80px;"
                  placeholder="0"
                  label="Cant."
                />
                <div class="ms-auto text-end">
                  <span class="text-caption font-monospace font-weight-bold">
                    {{ formatCurrency((quantities[denom] || 0) * denom) }}
                  </span>
                </div>
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Observaciones de Cierre -->
        <VRow>
          <VCol cols="12">
            <VTextarea
              v-model="closingNotes"
              :label="hasDifference ? 'Observaciones de cierre * (Obligatorio por diferencia de arqueo)' : 'Observaciones de cierre'"
              placeholder="Detalle o justificación del arqueo..."
              maxlength="1000"
              rows="2"
              :error-messages="errors.closing_notes"
              @input="clearFieldError('closing_notes')"
            />
            <div
              v-if="hasDifference"
              class="text-caption text-warning mt-1"
            >
              Al existir diferencia entre el efectivo esperado y el contado, la sesión pasará a estado de revisión pendiente por supervisión.
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 border-t justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="props.isSubmitting"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.isSubmitting"
          :disabled="props.isSubmitting || (hasDifference && !closingNotes.trim())"
          @click="handleSubmit"
        >
          Confirmar y Cerrar Turno
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
