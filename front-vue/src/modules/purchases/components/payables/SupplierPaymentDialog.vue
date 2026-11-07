<script setup>
/* eslint-disable camelcase */
import { computed, ref, watch } from 'vue'
import PaymentMethodFields from '@/components/common/PaymentMethodFields.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { supplierPaymentsApi } from '@/modules/purchases/services/supplierPaymentsApi'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Registra un pago a proveedor aplicado a las cuotas elegidas (mismo proveedor y moneda),
 * con el monto de cada cuota editable (por defecto, su saldo).
 */
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },

  // Cuotas (PayableInstallmentResource) del mismo proveedor y moneda.
  installments: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'paid'])

const notify = useNotify()
const { errors, generalError, setErrors, clearErrors } = useApiFormErrors()

const today = () => new Date().toISOString().slice(0, 10)

const rows = ref([])
const payment = ref({ payment_method_id: null, cash_session_id: null, reference: '' })
const paymentDate = ref(today())
const notes = ref('')
const isSubmitting = ref(false)

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const first = computed(() => props.installments[0] || null)
const symbol = computed(() => first.value?.currency?.symbol || 'S/')
const counterparty = computed(() => first.value?.provider?.business_name || first.value?.invoice?.counterparty_name || '')

const total = computed(() => rows.value.reduce((sum, row) => sum + Math.round(Number(row.amount || 0) * 100), 0) / 100)

watch(() => props.modelValue, open => {
  if (!open)
    return

  clearErrors()
  rows.value = props.installments.map(installment => ({ installment, amount: installment.balance }))
  payment.value = { payment_method_id: null, cash_session_id: null, reference: '' }
  paymentDate.value = today()
  notes.value = ''
})

const submit = async () => {
  clearErrors()
  isSubmitting.value = true
  try {
    await supplierPaymentsApi.create({
      provider_id: first.value?.provider_id || null,
      payment_date: paymentDate.value,
      currency_id: first.value?.currency_id,
      payment_method_id: payment.value.payment_method_id,
      cash_session_id: payment.value.cash_session_id,
      reference: payment.value.reference?.trim() || null,
      notes: notes.value?.trim() || null,
      allocations: rows.value
        .filter(row => Number(row.amount) > 0)
        .map(row => ({ installment_id: row.installment.id, amount: row.amount })),
    })

    notify.success('Pago a proveedor registrado correctamente')
    emit('paid')
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
    max-width="760"
    persistent
  >
    <VCard>
      <VCardItem>
        <VCardTitle>Pagar cuotas</VCardTitle>
        <VCardSubtitle>{{ counterparty }}</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          density="compact"
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VRow
          v-for="(row, index) in rows"
          :key="row.installment.id"
          dense
          class="align-center"
        >
          <VCol
            cols="12"
            sm="7"
          >
            <div class="font-weight-medium">
              {{ row.installment.invoice?.document_label }} · cuota {{ row.installment.number }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Vence {{ formatDateDMY(row.installment.due_date) }} · saldo {{ formatAmount(row.installment.balance, symbol) }}
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="5"
          >
            <VTextField
              v-model="row.amount"
              :label="`Monto a pagar (${symbol}) *`"
              placeholder="150.00"
              type="number"
              min="0"
              :max="row.installment.balance"
              step="0.01"
              density="compact"
              :error-messages="errors[`allocations.${index}.amount`] || errors[`allocations.${index}.installment_id`]"
            />
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <PaymentMethodFields
          v-model="payment"
          :currency-id="first?.currency_id"
          :errors="errors"
        />

        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="paymentDate"
              label="Fecha de pago *"
              placeholder="2026-10-20"
              type="date"
              :error-messages="errors.payment_date"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="notes"
              label="Observaciones"
              placeholder="Pago parcial acordado con el proveedor"
              maxlength="500"
              :error-messages="errors.notes"
            />
          </VCol>
        </VRow>

        <div
          v-if="errors.allocations"
          class="text-error text-caption"
        >
          {{ errors.allocations }}
        </div>
      </VCardText>

      <VCardActions class="pa-4">
        <span class="text-h6">Total: {{ formatAmount(total, symbol) }}</span>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="isOpen = false"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-cash"
          :loading="isSubmitting"
          :disabled="total <= 0"
          @click="submit"
        >
          Registrar pago
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
