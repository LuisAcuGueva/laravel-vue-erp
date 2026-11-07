<script setup>
/* eslint-disable camelcase */
import { computed } from 'vue'
import PaymentMethodFields from '@/components/common/PaymentMethodFields.vue'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Pago mixto al recibir una compra: un monto al contado que se paga de inmediato (caja o banco) y el saldo
 * se financia con el calculador de cuotas (`InstallmentPlanFields.vue`). El backend valida que el monto no
 * supere el total; aquí solo se muestra el saldo resultante como referencia.
 */
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' }),
  },
  total: {
    type: [Number, String],
    required: true,
  },
  currencyId: {
    type: String,
    default: null,
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
  today: {
    type: String,
    required: true,
  },

  // 'outlined' cuando está anidado dentro de otra VCard (p. ej. un diálogo); 'elevated' cuando es
  // una sección de nivel de página (frontend-ui.md: una VCard de página nunca usa variant="outlined").
  variant: {
    type: String,
    default: 'outlined',
  },
})

const emit = defineEmits(['update:modelValue'])

const update = patch => emit('update:modelValue', { ...props.modelValue, ...patch })

const amount = computed(() => Number(props.modelValue?.amount || 0))
const hasAmount = computed(() => amount.value > 0)
const remainder = computed(() => Math.max(0, Number(props.total || 0) - amount.value))

const paymentFields = computed({
  get: () => ({
    payment_method_id: props.modelValue?.payment_method_id ?? null,
    cash_session_id: props.modelValue?.cash_session_id ?? null,
    reference: props.modelValue?.reference ?? '',
  }),
  set: value => update(value),
})
</script>

<template>
  <VCard
    :variant="variant"
    class="down-payment-fields"
  >
    <VCardItem>
      <VCardTitle class="text-subtitle-1">
        Pago al contado
      </VCardTitle>
      <VCardSubtitle>
        Paga una parte ahora y financia el resto en cuotas
      </VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <VTextField
            :model-value="modelValue?.amount"
            label="Monto al contado"
            placeholder="0.00"
            type="number"
            step="0.01"
            min="0"
            :suffix="currencySymbol"
            hint="Déjalo en blanco para financiar todo en cuotas."
            persistent-hint
            :error-messages="errors['down_payment.amount']"
            @update:model-value="val => update({ amount: val })"
          />
        </VCol>

        <VCol
          v-if="hasAmount"
          cols="12"
          md="4"
        >
          <VTextField
            :model-value="modelValue?.payment_date || today"
            label="Fecha de pago"
            type="date"
            :error-messages="errors['down_payment.payment_date']"
            @update:model-value="val => update({ payment_date: val })"
          />
        </VCol>

        <VCol
          v-if="hasAmount"
          cols="12"
          md="4"
          class="d-flex align-center"
        >
          <div>
            <div class="text-caption text-medium-emphasis">
              Saldo a financiar en cuotas
            </div>
            <div class="text-subtitle-1 font-weight-medium">
              {{ formatAmount(remainder, currencySymbol, currencyDecimals) }}
            </div>
          </div>
        </VCol>
      </VRow>

      <PaymentMethodFields
        v-if="hasAmount"
        v-model="paymentFields"
        class="mt-4"
        :currency-id="currencyId"
        :errors="errors"
        error-prefix="down_payment."
      />
    </VCardText>
  </VCard>
</template>
