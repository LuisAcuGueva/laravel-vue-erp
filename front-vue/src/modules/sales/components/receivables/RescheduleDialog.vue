<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { receivablesApi } from '@/modules/sales/services/receivablesApi'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  sale: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'rescheduled'])

const notify = useNotify()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const isSubmitting = ref(false)
const count = ref(2)
const intervalDays = ref(30)
const firstDueDate = ref('')

const rows = ref([])

const saleCreditBalance = computed(() => {
  if (!props.sale) return 0

  // 1. Si la venta expone saldo pendiente directo (balance)
  if (props.sale.balance !== undefined && props.sale.balance !== null && props.sale.balance !== '') {
    const bal = Number(props.sale.balance)
    if (!Number.isNaN(bal)) {
      return Math.max(0, Math.round(bal * 100) / 100)
    }
  }

  // 2. Si no, calcular total menos lo pagado
  const total = Number(props.sale.total ?? props.sale.total_amount ?? 0)
  const paid = Number(props.sale.paid_amount ?? 0)
  const paymentsTotal = (props.sale.payments || []).reduce((acc, p) => acc + Number(p.amount || 0), 0)
  const effectivePaid = Math.max(paid, paymentsTotal)

  return Math.max(0, Math.round((total - effectivePaid) * 100) / 100)
})

const currencySymbol = computed(() => {
  return props.sale?.currency_symbol || props.sale?.currency?.symbol || 'S/'
})

const hasPaidInstallments = computed(() => {
  if (!props.sale?.installments?.length) return false

  return props.sale.installments.some(
    inst => inst.status === 'paid' || Number(inst.paid_amount || 0) > 0,
  )
})

const sumAmounts = computed(() => {
  const sum = rows.value.reduce((acc, r) => acc + Math.round(Number(r.amount || 0) * 100), 0)

  return sum / 100
})

const difference = computed(() => {
  return Math.round((saleCreditBalance.value - sumAmounts.value) * 100) / 100
})

const isBalanced = computed(() => Math.abs(difference.value) < 0.001)

const getTodayString = () => {
  const now = new Date()
  const y = now.getFullYear()
  const m = String(now.getMonth() + 1).padStart(2, '0')
  const d = String(now.getDate()).padStart(2, '0')

  return `${y}-${m}-${d}`
}

const addDays = (dateStr, days) => {
  if (!dateStr) return ''

  const [year, month, day] = dateStr.split('-').map(Number)
  const d = new Date(year, month - 1, day)

  d.setDate(d.getDate() + Number(days))

  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')

  return `${y}-${m}-${dd}`
}

const generateEvenSchedule = () => {
  const total = saleCreditBalance.value
  const n = Math.max(1, parseInt(count.value, 10) || 1)
  const interval = Math.max(1, parseInt(intervalDays.value, 10) || 30)

  const baseDate = firstDueDate.value || addDays(getTodayString(), interval)

  const basePart = Math.floor((total * 100) / n)
  const remainder = Math.round((total * 100) - (basePart * n))

  const newRows = []
  let currentDate = baseDate

  for (let i = 1; i <= n; i++) {
    const isFirst = i === 1
    const cents = basePart + (isFirst ? remainder : 0)
    const amount = (cents / 100).toFixed(2)

    newRows.push({
      installment_number: i,
      due_date: currentDate,
      amount,
    })

    currentDate = addDays(currentDate, interval)
  }

  rows.value = newRows
}

const distributeEvenly = () => {
  const n = rows.value.length
  if (n === 0) return

  const total = saleCreditBalance.value
  const basePart = Math.floor((total * 100) / n)
  const remainder = Math.round((total * 100) - (basePart * n))

  rows.value.forEach((r, idx) => {
    const isFirst = idx === 0
    const cents = basePart + (isFirst ? remainder : 0)

    r.amount = (cents / 100).toFixed(2)
  })
}

const adjustDifferenceToLastRow = () => {
  if (rows.value.length === 0 || isBalanced.value) return

  const lastRow = rows.value[rows.value.length - 1]
  const currentAmount = Number(lastRow.amount || 0)
  const adjusted = Math.max(0.01, Math.round((currentAmount + difference.value) * 100) / 100)

  lastRow.amount = adjusted.toFixed(2)
}

watch([() => props.modelValue, () => props.sale], ([open, sale]) => {
  if (!open || !sale) return

  clearErrors()

  // Filtrar únicamente cuotas pendientes sin cobros previos
  const pending = (sale.installments || []).filter(
    inst => inst.status === 'pending' && Number(inst.paid_amount || 0) === 0,
  )

  if (pending.length > 1) {
    rows.value = pending.map((inst, idx) => ({
      installment_number: inst.installment_number ?? inst.number ?? (idx + 1),
      due_date: inst.due_date ? String(inst.due_date).slice(0, 10) : '',
      amount: Number(inst.amount || 0).toFixed(2),
    }))
    count.value = rows.value.length
    intervalDays.value = 30
    firstDueDate.value = rows.value[0]?.due_date || addDays(getTodayString(), 30)
  }
  else if (pending.length === 1) {
    rows.value = [{
      installment_number: 1,
      due_date: pending[0].due_date ? String(pending[0].due_date).slice(0, 10) : addDays(getTodayString(), 30),
      amount: saleCreditBalance.value.toFixed(2),
    }]
    count.value = 2
    intervalDays.value = 30
    firstDueDate.value = rows.value[0].due_date
  }
  else {
    count.value = 2
    intervalDays.value = 30
    firstDueDate.value = addDays(getTodayString(), 30)
    generateEvenSchedule()
  }
}, { immediate: true })

const addRow = () => {
  const nextNum = rows.value.length + 1
  const lastDate = rows.value.length ? rows.value[rows.value.length - 1].due_date : getTodayString()

  rows.value.push({
    installment_number: nextNum,
    due_date: addDays(lastDate, intervalDays.value || 30),
    amount: difference.value > 0 ? difference.value.toFixed(2) : '0.00',
  })
  count.value = rows.value.length
}

const removeRow = index => {
  if (rows.value.length <= 1) return
  rows.value.splice(index, 1)

  // Reindexar números de cuota
  rows.value.forEach((r, idx) => {
    r.installment_number = idx + 1
  })
  count.value = rows.value.length
}

const submit = async () => {
  clearErrors()

  const hasEmptyDates = rows.value.some(r => !r.due_date)
  if (hasEmptyDates) {
    notify.error('Todas las cuotas deben tener una fecha de vencimiento.')

    return
  }

  const hasInvalidAmounts = rows.value.some(r => !r.amount || Number(r.amount) <= 0)
  if (hasInvalidAmounts) {
    notify.error('Todas las cuotas deben tener un monto mayor a 0.')

    return
  }

  if (!isBalanced.value) {
    notify.error(`La suma de las cuotas (${currencySymbol.value} ${formatAmount(sumAmounts.value)}) no coincide con el saldo de crédito (${currencySymbol.value} ${formatAmount(saleCreditBalance.value)}).`)

    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      installments: rows.value.map(r => ({
        installment_number: r.installment_number,
        due_date: r.due_date,
        amount: Number(Number(r.amount).toFixed(2)),
      })),
    }

    await receivablesApi.rescheduleInstallments(props.sale.id, payload)

    notify.success('Cronograma reprogramado correctamente')
    emit('rescheduled')
    isOpen.value = false
  }
  catch (err) {
    setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="700"
    persistent
  >
    <VCard>
      <VCardItem class="pb-2">
        <VCardTitle class="d-flex align-center justify-space-between">
          <span>Reprogramar Cronograma de Cuotas</span>
          <VChip
            size="small"
            color="primary"
            variant="tonal"
          >
            Venta: {{ sale?.full_number }}
          </VChip>
        </VCardTitle>
      </VCardItem>

      <VDivider />

      <VCardText class="pt-4">
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VAlert
          v-if="hasPaidInstallments"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          Esta venta ya cuenta con cuotas cobradas. Solo se reprogramarán las cuotas pendientes correspondientes al saldo por cobrar ({{ currencySymbol }} {{ formatAmount(saleCreditBalance) }}).
        </VAlert>

        <!-- Generador automático de cuotas -->
        <VCard
          variant="tonal"
          color="secondary"
          class="pa-4 mb-4"
        >
          <div class="d-flex align-center justify-space-between mb-1">
            <div class="text-subtitle-2 font-weight-bold">
              Generador Rápido de Cuotas
            </div>
            <span class="text-caption text-medium-emphasis">
              Saldo a financiar: <strong>{{ currencySymbol }} {{ formatAmount(saleCreditBalance) }}</strong>
            </span>
          </div>
          <p class="text-caption text-medium-emphasis mb-3">
            Calcula y divide el saldo pendiente de manera automática en partes iguales entre las cuotas seleccionadas.
          </p>

          <VRow density="compact">
            <VCol
              cols="12"
              sm="3"
            >
              <VTextField
                v-model.number="count"
                type="number"
                min="1"
                max="36"
                density="compact"
                label="N° Cuotas *"
                placeholder="Ej. 3"
                @keyup.enter="generateEvenSchedule"
              />
            </VCol>
            <VCol
              cols="12"
              sm="3"
            >
              <VTextField
                v-model.number="intervalDays"
                type="number"
                min="1"
                density="compact"
                label="Intervalo (días) *"
                placeholder="Ej. 30"
                @keyup.enter="generateEvenSchedule"
              />
            </VCol>
            <VCol
              cols="12"
              sm="3"
            >
              <VTextField
                v-model="firstDueDate"
                type="date"
                density="compact"
                label="Primera fecha *"
                placeholder="YYYY-MM-DD"
                @keyup.enter="generateEvenSchedule"
              />
            </VCol>
            <VCol
              cols="12"
              sm="3"
              class="d-flex align-center"
            >
              <VBtn
                size="small"
                variant="elevated"
                color="primary"
                block
                prepend-icon="tabler-wand"
                @click="generateEvenSchedule"
              >
                Generar
              </VBtn>
            </VCol>
          </VRow>
        </VCard>

        <!-- Tabla editable de cuotas -->
        <div class="border rounded overflow-hidden mb-3">
          <VTable density="compact">
            <thead>
              <tr>
                <th
                  class="text-center"
                  style="width: 70px;"
                >
                  CUOTA
                </th>
                <th>FECHA DE VENCIMIENTO *</th>
                <th
                  class="text-end"
                  style="width: 180px;"
                >
                  MONTO *
                </th>
                <th
                  class="text-center"
                  style="width: 50px;"
                />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, idx) in rows"
                :key="row.installment_number"
              >
                <td class="text-center font-weight-bold">
                  #{{ row.installment_number }}
                </td>
                <td>
                  <VTextField
                    v-model="row.due_date"
                    type="date"
                    density="compact"
                    hide-details
                  />
                </td>
                <td class="text-end">
                  <VTextField
                    v-model="row.amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    density="compact"
                    hide-details
                    :prefix="currencySymbol"
                    class="text-end"
                  />
                </td>
                <td class="text-center">
                  <VBtn
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="x-small"
                    :disabled="rows.length <= 1"
                    @click="removeRow(idx)"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <div class="d-flex flex-wrap justify-space-between align-center gap-2">
          <div class="d-flex gap-2">
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              prepend-icon="tabler-plus"
              @click="addRow"
            >
              Agregar Cuota
            </VBtn>

            <VBtn
              size="small"
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrows-split-2"
              :disabled="rows.length === 0"
              @click="distributeEvenly"
            >
              Distribuir en partes iguales
            </VBtn>
          </div>

          <!-- Resumen de Balance -->
          <div class="d-flex align-center flex-wrap gap-4 font-mono text-body-2">
            <div>
              <span class="text-medium-emphasis">Saldo Venta: </span>
              <strong>{{ currencySymbol }} {{ formatAmount(saleCreditBalance) }}</strong>
            </div>
            <div>
              <span class="text-medium-emphasis">Suma: </span>
              <strong :class="{ 'text-success': isBalanced, 'text-error': !isBalanced }">
                {{ currencySymbol }} {{ formatAmount(sumAmounts) }}
              </strong>
            </div>
            <div
              v-if="!isBalanced"
              class="d-flex align-center gap-1"
            >
              <VChip
                size="small"
                color="error"
                variant="tonal"
              >
                Diferencia: {{ currencySymbol }} {{ formatAmount(difference) }}
              </VChip>

              <VBtn
                size="x-small"
                variant="text"
                color="error"
                title="Ajustar diferencia en la última cuota"
                @click="adjustDifferenceToLastRow"
              >
                Ajustar última cuota
              </VBtn>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="isSubmitting"
          @click="isOpen = false"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          :loading="isSubmitting"
          :disabled="isSubmitting || !isBalanced"
          @click="submit"
        >
          Guardar Cronograma
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
