<script setup>
import { computed, ref, watch } from 'vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  period: {
    type: Object,
    default: null,
  },
  account: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submit'])

const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const form = ref({
  'statement_balance': '',
  'closed_at': new Date().toISOString().substring(0, 10),
  notes: '',
})

const currencySymbol = computed(() => props.account?.currency?.symbol || 'S/')

const expectedBalance = computed(() => {
  if (props.account?.balance !== undefined)
    return Number(props.account.balance)
  if (props.period?.expected_balance !== undefined && props.period.expected_balance !== null)
    return Number(props.period.expected_balance)

  return 0
})

const statementBalanceNumber = computed(() => {
  if (form.value['statement_balance'] === '' || form.value['statement_balance'] === null)
    return null
  const num = Number(form.value['statement_balance'])

  return Number.isNaN(num) ? null : num
})

const difference = computed(() => {
  if (statementBalanceNumber.value === null)
    return null

  return Number((statementBalanceNumber.value - expectedBalance.value).toFixed(2))
})

const hasDifference = computed(() => {
  return difference.value !== null && Math.abs(difference.value) > 0.001
})

const formatMoney = val => {
  if (val === null || val === undefined || val === '')
    return '0.00'
  const num = Number(val)

  return Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

watch(
  () => props.isDialogOpen,
  isOpen => {
    if (isOpen) {
      clearErrors()
      form.value = {
        'statement_balance': expectedBalance.value !== 0 ? expectedBalance.value.toFixed(2) : '',
        'closed_at': new Date().toISOString().substring(0, 10),
        notes: '',
      }
    }
  },
)

const handleClose = () => {
  clearErrors()
  emit('update:isDialogOpen', false)
}

const handleSubmit = () => {
  clearErrors()

  if (form.value['statement_balance'] === '' || form.value['statement_balance'] === null) {
    setErrors({
      data: {
        errors: {
          'statement_balance': ['El saldo según extracto bancario es obligatorio.'],
        },
      },
    })

    return
  }

  if (hasDifference.value && (!form.value.notes || form.value.notes.trim() === '')) {
    setErrors({
      data: {
        errors: {
          notes: ['Debe indicar una nota explicativa cuando existe diferencia con el extracto.'],
        },
      },
    })

    return
  }

  emit('submit', {
    'statement_balance': Number(form.value['statement_balance']),
    'closed_at': form.value['closed_at'],
    notes: form.value.notes ? form.value.notes.trim() : null,
  })
}

defineExpose({
  setErrors,
  clearErrors,
})
</script>

<template>
  <VDialog
    :model-value="isDialogOpen"
    max-width="540"
    persistent
    @update:model-value="handleClose"
  >
    <VCard>
      <VCardItem class="pb-3">
        <template #prepend>
          <VAvatar
            color="warning"
            variant="tonal"
            class="me-2"
          >
            <VIcon
              icon="tabler-lock"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>Cerrar Periodo Bancario</VCardTitle>
        <VCardSubtitle>
          Periodo N° {{ period?.number || 1 }} — {{ account?.alias || 'Cuenta bancaria' }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          closable
          class="mb-4"
          @click:close="generalError = null"
        >
          {{ generalError }}
        </VAlert>

        <!-- Balance Info -->
        <VCard
          variant="tonal"
          color="secondary"
          class="mb-4"
        >
          <VCardText class="py-3">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-caption text-medium-emphasis">Saldo esperado en libros:</span>
              <span class="text-body-1 font-weight-bold">
                {{ currencySymbol }} {{ formatMoney(expectedBalance) }}
              </span>
            </div>

            <div
              v-if="difference !== null"
              class="d-flex justify-space-between align-center pt-2 border-t"
            >
              <span class="text-caption text-medium-emphasis">Diferencia de conciliación:</span>
              <span
                class="text-body-1 font-weight-bold"
                :class="hasDifference ? 'text-warning' : 'text-success'"
              >
                {{ currencySymbol }} {{ formatMoney(difference) }}
                <VChip
                  size="x-small"
                  :color="hasDifference ? 'warning' : 'success'"
                  class="ms-1"
                >
                  {{ hasDifference ? 'Con diferencia' : 'Conciliado' }}
                </VChip>
              </span>
            </div>
          </VCardText>
        </VCard>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="form['statement_balance']"
                type="number"
                step="0.01"
                label="Saldo extracto bancario *"
                placeholder="ej. 5240.50"
                :prefix="currencySymbol"
                :error-messages="errors.statement_balance"
                @update:model-value="clearFieldError('statement_balance')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="form['closed_at']"
                type="date"
                label="Fecha de corte *"
                placeholder="ej. 2026-10-31"
                :error-messages="errors.closed_at"
                @update:model-value="clearFieldError('closed_at')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.notes"
                :label="hasDifference ? 'Notas de conciliación (obligatorio por diferencia) *' : 'Notas de conciliación'"
                placeholder="ej. Cheque N° 45 pendiente de cobro por el proveedor..."
                rows="3"
                :error-messages="errors.notes"
                @update:model-value="clearFieldError('notes')"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="px-6 pb-4">
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="warning"
          variant="elevated"
          :loading="isSubmitting"
          @click="handleSubmit"
        >
          <VIcon
            start
            icon="tabler-check"
          />
          Cerrar y abrir siguiente
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
