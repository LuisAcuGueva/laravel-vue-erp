<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  balance: {
    type: Number,
    required: true,
  },
  creditDays: {
    type: Number,
    default: 30,
  },

  // Plazo máximo de crédito del cliente (InstallmentScheduleService::validate() del backend
  // rechaza cualquier cuota cuyo vencimiento exceda este plazo desde la fecha de emisión).
  maxCreditDays: {
    type: Number,
    default: null,
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
})

const emit = defineEmits(['update:plan'])

const count = ref(1)
const intervalDays = ref(30)
const previewRows = ref([])
const totalDays = ref(0)

const exceedsCreditLimit = computed(() =>
  Boolean(props.maxCreditDays) && totalDays.value > props.maxCreditDays,
)

const addDays = days => {
  const d = new Date()

  d.setDate(d.getDate() + Number(days))

  return d.toISOString().slice(0, 10)
}

const recalculate = () => {
  const total = Number(props.balance || 0)
  const n = Math.max(1, parseInt(count.value, 10) || 1)
  const interval = Math.max(1, parseInt(intervalDays.value, 10) || props.creditDays || 30)

  const basePart = Math.floor((total * 100) / n)
  const remainder = Math.round((total * 100) - (basePart * n))

  const rows = []
  let daysAccum = interval

  for (let i = 1; i <= n; i++) {
    const isFirst = i === 1
    const cents = basePart + (isFirst ? remainder : 0)
    const amount = (cents / 100).toFixed(2)

    rows.push({
      installment_number: i,
      due_date: addDays(daysAccum),
      amount: Number(amount),
    })

    daysAccum += interval
  }

  previewRows.value = rows
  totalDays.value = n * interval

  emit('update:plan', {
    installments_count: n,
    installment_interval_days: interval,
    installments: rows,
    valid: !exceedsCreditLimit.value,
  })
}

watch([() => props.balance, () => props.creditDays, count, intervalDays], () => {
  recalculate()
}, { immediate: true })
</script>

<template>
  <div class="installment-plan-editor border rounded pa-3 bg-var-theme-background">
    <div class="text-caption font-weight-bold text-uppercase mb-2 text-medium-emphasis">
      Plan de Cuotas a Crédito
    </div>

    <VRow density="compact">
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model.number="count"
          type="number"
          min="1"
          max="36"
          density="compact"
          label="N° de cuotas *"
          placeholder="Ej. 3"
        />
      </VCol>
      <VCol
        cols="12"
        sm="6"
      >
        <VTextField
          v-model.number="intervalDays"
          type="number"
          min="1"
          density="compact"
          label="Frecuencia (días) *"
          placeholder="Ej. 30"
        />
      </VCol>
    </VRow>

    <VAlert
      v-if="exceedsCreditLimit"
      type="error"
      variant="tonal"
      density="compact"
      class="mt-2"
    >
      El plazo de la última cuota ({{ totalDays }} días) excede los {{ maxCreditDays }} días de crédito permitidos para este cliente.
    </VAlert>

    <div
      v-if="previewRows.length"
      class="mt-2"
    >
      <div class="text-caption font-weight-medium mb-1">
        Cronograma estimado:
      </div>
      <div class="d-flex flex-wrap gap-2">
        <VChip
          v-for="row in previewRows"
          :key="row.installment_number"
          size="small"
          variant="tonal"
          :color="exceedsCreditLimit ? 'error' : 'info'"
        >
          Cuota {{ row.installment_number }}: {{ currencySymbol }} {{ formatAmount(row.amount) }} ({{ row.due_date }})
        </VChip>
      </div>
    </div>
  </div>
</template>
