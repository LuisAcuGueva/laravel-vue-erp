<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  count: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const treatUncountedAsZero = ref(false)

watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      treatUncountedAsZero.value = false
    }
  },
)

// Las diferencias valorizadas solo se persisten al cerrar, por eso se estiman aquí con los conteos guardados.
const summary = computed(() => {
  const items = props.count?.items || []
  let counted = 0
  let positive = 0
  let negative = 0

  items.forEach(item => {
    const hasCount = item.counted_quantity !== null && item.counted_quantity !== undefined

    if (hasCount)
      counted++

    if (!hasCount && !treatUncountedAsZero.value)
      return

    const diff = Number(hasCount ? item.counted_quantity : 0) - Number(item.system_quantity || 0)
    const value = diff * Number(item.unit_cost || 0)

    if (value > 0)
      positive += value
    else if (value < 0)
      negative += Math.abs(value)
  })

  return {
    total: items.length || props.count?.total_items || 0,
    counted: items.length ? counted : props.count?.counted_items || 0,
    positive_value: positive,
    negative_value: negative,
    net_value: positive - negative,
  }
})

const uncountedCount = computed(() => {
  const total = summary.value.total || 0
  const counted = summary.value.counted || 0
  
  return Math.max(0, total - counted)
})

const formatCurrency = val => {
  const num = Number(val || 0)
  
  return `S/ ${num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

const handleClose = () => {
  if (props.loading) return
  emit('update:modelValue', false)
}

const handleConfirm = () => {
  emit('confirm', {
    treat_uncounted_as_zero: treatUncountedAsZero.value,
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pt-4 px-6">
        <VIcon
          icon="tabler-clipboard-check"
          color="primary"
          size="26"
        />
        <span>Cerrar toma de inventario</span>
      </VCardTitle>

      <VCardText class="px-6 pt-2 pb-4">
        <p class="text-body-2 text-medium-emphasis mb-4">
          Al cerrar el conteo se calcularán las diferencias definitivas con el stock del sistema
          y se generarán automáticamente las <strong>Notas de Almacén</strong> de ajuste correspondientes.
        </p>

        <!-- Tarjeta de resumen de conteo -->
        <VCard
          variant="tonal"
          color="primary"
          class="mb-4"
        >
          <VCardText class="pa-4">
            <div class="d-flex justify-space-between mb-2">
              <span class="text-caption text-medium-emphasis">Total de artículos:</span>
              <span class="font-weight-bold">{{ summary.total }}</span>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span class="text-caption text-medium-emphasis">Artículos contados:</span>
              <span class="font-weight-bold text-success">{{ summary.counted }}</span>
            </div>
            <div
              v-if="uncountedCount > 0"
              class="d-flex justify-space-between mb-2"
            >
              <span class="text-caption text-warning">Artículos sin contar:</span>
              <span class="font-weight-bold text-warning">{{ uncountedCount }}</span>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between mb-1">
              <span class="text-caption">Ajuste positivo estimado (sobrantes):</span>
              <span class="font-weight-medium text-success">+{{ formatCurrency(summary.positive_value) }}</span>
            </div>
            <div class="d-flex justify-space-between mb-1">
              <span class="text-caption">Ajuste negativo estimado (faltantes):</span>
              <span class="font-weight-medium text-error">-{{ formatCurrency(summary.negative_value) }}</span>
            </div>
            <div class="d-flex justify-space-between font-weight-bold mt-2">
              <span>Impacto neto estimado:</span>
              <span :class="summary.net_value >= 0 ? 'text-success' : 'text-error'">
                {{ summary.net_value >= 0 ? '+' : '' }}{{ formatCurrency(summary.net_value) }}
              </span>
            </div>
          </VCardText>
        </VCard>

        <!-- Alerta si hay artículos no contados -->
        <VAlert
          v-if="uncountedCount > 0"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Quedan <strong>{{ uncountedCount }}</strong> artículos sin conteo registrado.
        </VAlert>

        <VCheckbox
          v-if="uncountedCount > 0"
          v-model="treatUncountedAsZero"
          label="Tratar artículos no contados como stock cero (generará salida por el total de su stock actual)"
          density="comfortable"
          hide-details
          class="mt-2"
        />
        <div
          v-if="uncountedCount > 0 && !treatUncountedAsZero"
          class="text-caption text-medium-emphasis mt-1 ms-8"
        >
          Si no marcas esta casilla, los artículos sin contar serán ignorados y mantendrán su stock actual sin ajustes.
        </div>
      </VCardText>

      <VCardActions class="px-6 pb-4 pt-0 justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="loading"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="loading"
          @click="handleConfirm"
        >
          <VIcon
            icon="tabler-check"
            class="me-1"
          />
          Confirmar cierre y ajustar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
