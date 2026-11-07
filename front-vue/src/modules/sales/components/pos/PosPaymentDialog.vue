<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { employeesApi } from '@/modules/maintenance/services/employeesApi'
import { paymentMethodsApi } from '@/modules/finance/services/financeSettingsApi'
import InstallmentPlanEditor from '@/modules/sales/components/receivables/InstallmentPlanEditor.vue'
import { formatAmount } from '@/utils/documentTotals'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  totalAmount: {
    type: String,
    required: true,
  },
  currencySymbol: {
    type: String,
    default: 'S/',
  },
  client: {
    type: Object,
    default: null,
  },
  activeCashSession: {
    type: Object,
    default: null,
  },
  defaultSellerId: {
    type: String,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const condition = ref('cash') // 'cash' | 'credit'
const paymentMethods = ref([])
const sellers = ref([])
const sellerId = ref(null)
const notes = ref('')

// Pagos contado
const cashAmount = ref('0.00')
const cashReceived = ref('0.00')
const selectedPaymentMethodId = ref(null)
const paymentReference = ref('')

// Pagos crédito
const advanceAmount = ref('0.00')
const advanceMethodId = ref(null)
const advanceReference = ref('')
const creditDays = ref(30)
const installmentPlan = ref(null)

const onPlanUpdate = plan => {
  installmentPlan.value = plan
}

const QUICK_AMOUNTS = [10, 20, 50, 100, 200]

const isOpen = computed({
  get: () => props.modelValue,
  set: val => emit('update:modelValue', val),
})

const selectedMethod = computed(() =>
  paymentMethods.value.find(m => m.id === selectedPaymentMethodId.value) || null,
)

const isCashMethod = computed(() => {
  return selectedMethod.value?.kind === 'cash' || selectedMethod.value?.code === 'CASH'
})

const changeAmount = computed(() => {
  if (!isCashMethod.value) return '0.00'
  const rec = Number(cashReceived.value || 0)
  const amt = Number(cashAmount.value || 0)
  if (rec > amt)
    return (rec - amt).toFixed(2)

  return '0.00'
})

const clientAvailableCredit = computed(() => {
  if (!props.client) return 0
  const limit = Number(props.client.credit_limit || 0)
  const debt = Number(props.client.debt || 0)

  return Math.max(0, limit - debt)
})

const creditExceeded = computed(() => {
  if (condition.value !== 'credit') return false
  const saleBalance = Number(props.totalAmount) - Number(advanceAmount.value || 0)

  return saleBalance > clientAvailableCredit.value
})

// Días de crédito permitidos para el cliente (límite configurado en su ficha).
// InstallmentScheduleService::validate() rechaza cualquier cuota cuyo vencimiento
// exceda este plazo desde la fecha de emisión, sin importar lo que se tipee en "Días de Crédito".
const clientCreditDaysLimit = computed(() => Number(props.client?.credit_days || 30))

const installmentPlanInvalid = computed(() =>
  condition.value === 'credit' && installmentPlan.value?.valid === false,
)

const loadDependencies = async () => {
  try {
    const [pmRes, empRes] = await Promise.all([
      paymentMethodsApi.options(),
      employeesApi.list({ is_active: true, per_page: 100 }),
    ])

    paymentMethods.value = pmRes?.data || []
    sellers.value = empRes?.data || []

    const cashDefault = paymentMethods.value.find(m => m.code === 'CASH') || paymentMethods.value[0]
    if (cashDefault) {
      selectedPaymentMethodId.value = cashDefault.id
      advanceMethodId.value = cashDefault.id
    }
  }
  catch {
    // Fallback silencioso
  }
}

onMounted(() => {
  loadDependencies()
})

watch(() => props.modelValue, open => {
  if (!open) return

  condition.value = 'cash'
  cashAmount.value = String(props.totalAmount)
  cashReceived.value = String(props.totalAmount)
  advanceAmount.value = '0.00'
  paymentReference.value = ''
  advanceReference.value = ''
  notes.value = ''

  if (props.defaultSellerId)
    sellerId.value = props.defaultSellerId

  if (props.client?.credit_days)
    creditDays.value = Number(props.client.credit_days)
})

const setQuickAmount = amount => {
  if (amount === 'exact')
    cashReceived.value = String(cashAmount.value)
  else
    cashReceived.value = Number(amount).toFixed(2)
}

const submit = () => {
  if (creditExceeded.value || installmentPlanInvalid.value)
    return

  const payload = {
    payment_condition: condition.value,
    seller_id: sellerId.value,
    notes: notes.value || null,
    payments: [],
  }

  if (condition.value === 'cash') {
    payload.payments = [
      {
        payment_method_id: selectedPaymentMethodId.value,
        amount: Number(cashAmount.value),
        received_amount: isCashMethod.value ? Number(cashReceived.value) : Number(cashAmount.value),
        reference: paymentReference.value || null,
        cash_session_id: isCashMethod.value ? props.activeCashSession?.id : null,
      },
    ]
  }
  else {
    payload.credit_days = creditDays.value
    if (installmentPlan.value) {
      payload.installments_count = installmentPlan.value.installments_count
      payload.installment_interval_days = installmentPlan.value.installment_interval_days
      payload.installments = installmentPlan.value.installments.map(r => ({
        installment_number: r.installment_number,
        due_date: r.due_date,
        amount: r.amount,
      }))
    }
    if (Number(advanceAmount.value) > 0 && advanceMethodId.value) {
      const advMethod = paymentMethods.value.find(m => m.id === advanceMethodId.value)
      const isAdvCash = advMethod?.kind === 'cash' || advMethod?.code === 'CASH'

      payload.payments = [
        {
          payment_method_id: advanceMethodId.value,
          amount: Number(advanceAmount.value),
          received_amount: Number(advanceAmount.value),
          reference: advanceReference.value || null,
          cash_session_id: isAdvCash ? props.activeCashSession?.id : null,
        },
      ]
    }
  }

  emit('submit', payload)
}
</script>

<template>
  <VDialog
    v-model="isOpen"
    max-width="640"
    persistent
  >
    <VCard>
      <VCardTitle class="pa-6 pb-2 d-flex justify-space-between align-center">
        <div>
          <span class="text-h6 font-weight-bold">Finalizar Venta</span>
          <div class="text-caption text-medium-emphasis">
            Cliente: {{ client?.name || 'CLIENTE GENERAL' }}
          </div>
        </div>
        <div class="text-right">
          <div class="text-caption text-uppercase text-medium-emphasis">
            Total a Cobrar
          </div>
          <div class="text-h5 font-weight-bold text-primary">
            {{ formatAmount(totalAmount, currencySymbol) }}
          </div>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-6 pt-4">
        <!-- Selector Condición de Pago -->
        <div class="segmented-toggle segmented-toggle--block mb-5">
          <VBtn
            size="large"
            rounded="lg"
            class="flex-grow-1"
            :variant="condition === 'cash' ? 'flat' : 'text'"
            :color="condition === 'cash' ? 'primary' : 'default'"
            @click="condition = 'cash'"
          >
            <VIcon
              start
              icon="tabler-cash"
            />
            Al Contado
          </VBtn>

          <VBtn
            size="large"
            rounded="lg"
            class="flex-grow-1"
            :variant="condition === 'credit' ? 'flat' : 'text'"
            :color="condition === 'credit' ? 'primary' : 'default'"
            @click="condition = 'credit'"
          >
            <VIcon
              start
              icon="tabler-calendar-time"
            />
            Al Crédito
          </VBtn>
        </div>

        <!-- Sección CONTADO -->
        <div
          v-if="condition === 'cash'"
          class="space-y-4"
        >
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="selectedPaymentMethodId"
                label="Método de Pago *"
                :items="paymentMethods"
                item-title="name"
                item-value="id"
                placeholder="Seleccione el método de pago"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="cashAmount"
                label="Monto a Pagar *"
                :prefix="currencySymbol"
                type="number"
                step="0.01"
                placeholder="0.00"
              />
            </VCol>

            <VCol
              v-if="isCashMethod"
              cols="12"
            >
              <span class="text-caption font-weight-medium text-medium-emphasis">Billetes Rápidos:</span>
              <div class="d-flex flex-wrap gap-2 mt-2 mb-4">
                <VBtn
                  v-for="amt in QUICK_AMOUNTS"
                  :key="amt"
                  size="small"
                  rounded="lg"
                  variant="tonal"
                  color="secondary"
                  @click="setQuickAmount(amt)"
                >
                  {{ currencySymbol }} {{ amt }}
                </VBtn>
                <VBtn
                  size="small"
                  rounded="lg"
                  variant="tonal"
                  color="primary"
                  @click="setQuickAmount('exact')"
                >
                  Exacto
                </VBtn>
              </div>

              <VTextField
                v-model="cashReceived"
                label="Monto Recibido *"
                :prefix="currencySymbol"
                type="number"
                step="0.01"
                placeholder="0.00"
              />
            </VCol>

            <VCol
              v-if="!isCashMethod"
              cols="12"
            >
              <VTextField
                v-model="paymentReference"
                label="Número de Operación / Referencia"
                placeholder="Ej: OP-987654"
              />
            </VCol>
          </VRow>

          <!-- Vuelto / Cambio -->
          <div
            v-if="isCashMethod"
            class="change-due pa-4 rounded-lg d-flex justify-space-between align-center mt-2"
          >
            <span class="font-weight-medium">Vuelto a Entregar:</span>
            <span class="text-h6 font-weight-bold text-success">{{ formatAmount(changeAmount) }}</span>
          </div>
        </div>

        <!-- Sección CRÉDITO -->
        <div
          v-else
          class="space-y-4"
        >
          <!-- Resumen de Crédito del Cliente -->
          <VAlert
            :type="creditExceeded ? 'error' : 'info'"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            <div class="d-flex justify-space-between text-body-2">
              <span>Límite de crédito: <strong>{{ formatAmount(client?.credit_limit || 0, currencySymbol) }}</strong></span>
              <span>Deuda actual: <strong>{{ formatAmount(client?.debt || 0, currencySymbol) }}</strong></span>
              <span>Crédito disponible: <strong>{{ formatAmount(clientAvailableCredit, currencySymbol) }}</strong></span>
            </div>
            <div class="text-body-2 mt-1">
              Plazo máximo de crédito: <strong>{{ clientCreditDaysLimit }} días</strong> desde la emisión.
            </div>
            <div
              v-if="creditExceeded"
              class="mt-1 font-weight-bold text-error"
            >
              El saldo de la venta supera el crédito disponible del cliente.
            </div>
          </VAlert>

          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model.number="creditDays"
                label="Días de Crédito *"
                type="number"
                placeholder="Ej: 30"
              />
            </VCol>

            <VCol
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="advanceAmount"
                label="Pago Inicial (Opcional)"
                :prefix="currencySymbol"
                type="number"
                step="0.01"
                placeholder="0.00"
              />
            </VCol>

            <VCol
              v-if="Number(advanceAmount) > 0"
              cols="12"
              sm="6"
            >
              <VSelect
                v-model="advanceMethodId"
                label="Método de Pago del Adelanto *"
                :items="paymentMethods"
                item-title="name"
                item-value="id"
                placeholder="Seleccione método"
              />
            </VCol>

            <VCol
              v-if="Number(advanceAmount) > 0"
              cols="12"
              sm="6"
            >
              <VTextField
                v-model="advanceReference"
                label="Referencia del Adelanto"
                placeholder="Ej: OP-12345"
              />
            </VCol>

            <!-- Cronograma de Cuotas -->
            <VCol cols="12">
              <InstallmentPlanEditor
                :balance="Number(totalAmount) - Number(advanceAmount || 0)"
                :credit-days="Number(creditDays || 30)"
                :max-credit-days="clientCreditDaysLimit"
                :currency-symbol="currencySymbol"
                @update:plan="onPlanUpdate"
              />
            </VCol>
          </VRow>
        </div>

        <VDivider class="my-4" />

        <!-- Vendedor y Notas -->
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <VAutocomplete
              v-model="sellerId"
              label="Vendedor / Responsable"
              :items="sellers"
              item-title="full_name"
              item-value="id"
              placeholder="Seleccionar vendedor"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <VTextField
              v-model="notes"
              label="Notas u observaciones"
              placeholder="Ej: Entrega por delivery"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-6 pt-0 justify-end gap-2">
        <VBtn
          variant="tonal"
          color="secondary"
          size="large"
          :disabled="isSubmitting"
          @click="isOpen = false"
        >
          Cancelar (Esc)
        </VBtn>

        <VBtn
          variant="flat"
          color="primary"
          size="large"
          :loading="isSubmitting"
          :disabled="creditExceeded || installmentPlanInvalid"
          prepend-icon="tabler-check"
          @click="submit"
        >
          Completar Venta (F9)
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.segmented-toggle {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 3px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.06);
}

.segmented-toggle--block {
  display: flex;
  width: 100%;
}

.change-due {
  background: rgba(var(--v-theme-success), 0.12);
}
</style>
