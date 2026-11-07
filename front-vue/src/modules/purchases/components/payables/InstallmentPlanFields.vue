<script setup>
/* eslint-disable camelcase */
import { computed, ref } from 'vue'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Calculadora de cuotas por pagar a un proveedor: "Dividir en cuotas" prellena filas editables
 * (vencimiento + monto); el backend (`SupplierInvoiceService::buildInstallments()`) es quien valida
 * que la suma coincida exactamente con el total de la factura. Reutilizable en el formulario manual
 * de factura de proveedor y en las pantallas de recepción de compra (spec 034).
 */
const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },

  // Total de la factura (o su previsualización) contra el que se reparten las cuotas.
  total: {
    type: [Number, String],
    required: true,
  },

  // Fecha de emisión: ninguna cuota puede vencer antes de esta fecha.
  issueDate: {
    type: String,
    required: true,
  },

  // Vencimiento por defecto de la "cuota única" (emisión + días de crédito del proveedor).
  defaultDueDate: {
    type: String,
    required: true,
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
  currencyDecimals: {
    type: Number,
    default: 2,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },

  // 'outlined' cuando está anidado dentro de otra VCard (p. ej. un diálogo); 'elevated' cuando es
  // una sección de nivel de página (frontend-ui.md: una VCard de página nunca usa variant="outlined").
  variant: {
    type: String,
    default: 'outlined',
  },
})

const emit = defineEmits(['update:modelValue'])

let keySeed = 0

const splitCount = ref(2)
const splitInterval = ref(30)

const rows = computed(() => props.modelValue || [])

const addDays = (date, days) => {
  const d = new Date(`${date}T00:00:00`)

  d.setDate(d.getDate() + Number(days || 0))

  return d.toISOString().slice(0, 10)
}

const installmentsSum = computed(() => rows.value
  .reduce((sum, row) => sum + Math.round(Number(row.amount || 0) * 100), 0) / 100)

const installmentsMatch = computed(() => !rows.value.length
  || Math.abs(installmentsSum.value - Number(props.total || 0)) < 0.005)

// Divide el total en N cuotas iguales (la última absorbe el redondeo).
const splitInstallments = () => {
  const count = Math.max(1, Math.min(60, Number(splitCount.value || 1)))
  const totalCents = Math.round(Number(props.total || 0) * 100)
  const base = Math.floor(totalCents / count)
  const newRows = []

  for (let i = 0; i < count; i++) {
    const cents = i === count - 1 ? totalCents - base * (count - 1) : base

    newRows.push({
      key: `inst-${keySeed++}`,
      due_date: addDays(props.issueDate, Number(splitInterval.value || 30) * (i + 1)),
      amount: (cents / 100).toFixed(2),
    })
  }

  emit('update:modelValue', newRows)
}

const clearPlan = () => emit('update:modelValue', [])

const updateRow = (index, patch) => {
  const next = rows.value.map((row, i) => (i === index ? { ...row, ...patch } : row))

  emit('update:modelValue', next)
}
</script>

<template>
  <VCard
    :variant="variant"
    class="installment-plan-fields"
  >
    <VCardItem>
      <VCardTitle class="text-subtitle-1">
        Cuotas por pagar
      </VCardTitle>
      <VCardSubtitle>
        {{ rows.length ? `${rows.length} cuotas` : `Cuota única al ${formatDateDMY(defaultDueDate)}` }}
      </VCardSubtitle>
      <template #append>
        <div class="d-flex flex-wrap align-center gap-2">
          <VTextField
            v-model="splitCount"
            label="N.° de cuotas"
            placeholder="3"
            type="number"
            min="1"
            max="60"
            density="compact"
            style="max-width: 120px;"
            hide-details
          />
          <VTextField
            v-model="splitInterval"
            label="Cada (días)"
            placeholder="30"
            type="number"
            min="1"
            density="compact"
            style="max-width: 120px;"
            hide-details
          />
          <VBtn
            variant="tonal"
            prepend-icon="tabler-calendar-plus"
            :disabled="!Number(total)"
            @click="splitInstallments"
          >
            Dividir en cuotas
          </VBtn>
          <VBtn
            v-if="rows.length"
            variant="text"
            color="secondary"
            @click="clearPlan"
          >
            Cuota única
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VCardText v-if="rows.length">
      <VRow
        v-for="(row, index) in rows"
        :key="row.key ?? index"
        dense
      >
        <VCol
          cols="2"
          class="d-flex align-center"
        >
          Cuota {{ index + 1 }}
        </VCol>
        <VCol cols="5">
          <VTextField
            :model-value="row.due_date"
            label="Vencimiento *"
            placeholder="2026-11-19"
            type="date"
            density="compact"
            :error-messages="errors[`installments.${index}.due_date`]"
            @update:model-value="val => updateRow(index, { due_date: val })"
          />
        </VCol>
        <VCol cols="5">
          <VTextField
            :model-value="row.amount"
            :label="`Monto (${currencySymbol}) *`"
            placeholder="250.00"
            type="number"
            step="0.01"
            density="compact"
            :error-messages="errors[`installments.${index}.amount`]"
            @update:model-value="val => updateRow(index, { amount: val })"
          />
        </VCol>
      </VRow>
      <div
        class="text-end text-body-2 mt-2"
        :class="installmentsMatch ? 'text-success' : 'text-error'"
      >
        Suma de cuotas: {{ formatAmount(installmentsSum, currencySymbol, currencyDecimals) }} / total {{ formatAmount(total, currencySymbol, currencyDecimals) }}
      </div>
      <div
        v-if="errors.installments"
        class="text-error text-caption"
      >
        {{ errors.installments }}
      </div>
    </VCardText>
  </VCard>
</template>
