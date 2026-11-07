<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useCashSession } from '@/modules/finance/composables/useCashSession'
import { currenciesApi, paymentMethodsApi } from '@/modules/finance/services/financeSettingsApi'

/**
 * PaymentMethodFields.vue
 *
 * Campos comunes de un cobro o pago: método de pago, referencia y sesión de caja.
 * Un método de caja (`settlement = cash_box`) usa la sesión abierta de la caja seleccionada
 * y solo admite la moneda base; un método bancario liquida en la cuenta del método,
 * por eso solo se ofrecen los métodos cuya cuenta es de la moneda del documento.
 *
 * @prop {object} modelValue { payment_method_id, cash_session_id, reference }
 * @prop {string} currencyId Moneda del documento
 * @prop {object} errors Errores de la API (`payment_method_id`, `cash_session_id`, `reference`)
 * @prop {string} errorPrefix Prefijo de los errores (ej. `payment.`)
 * @prop {string} density Densidad de los campos
 */
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({ payment_method_id: null, cash_session_id: null, reference: '' }),
  },
  currencyId: {
    type: String,
    default: null,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  errorPrefix: {
    type: String,
    default: '',
  },
  density: {
    type: String,
    default: 'comfortable',
  },
})

const emit = defineEmits(['update:modelValue', 'method'])

const {
  availableBoxes,
  selectedBoxId,
  currentSession,
  hasOpenSession,
  isLoadingBoxes,
  isLoadingSession,
  fetchAvailableBoxes,
  selectBox,
} = useCashSession()

const methods = ref([])
const currencies = ref([])
const isLoading = ref(false)

const baseCurrencyId = computed(() => currencies.value.find(c => c.is_base)?.id || null)
const isBaseCurrency = computed(() => !props.currencyId || props.currencyId === baseCurrencyId.value)

const availableMethods = computed(() => methods.value.filter(method => {
  if (method.settlement === 'cash_box')
    return isBaseCurrency.value

  return Boolean(method.bank_account) && (!props.currencyId || method.bank_account.currency_id === props.currencyId)
}))

const methodOptions = computed(() => availableMethods.value.map(method => ({
  title: method.settlement === 'bank_account' && method.bank_account
    ? `${method.name} · ${method.bank_account.alias}`
    : method.name,
  value: method.id,
})))

const selectedMethod = computed(() => methods.value.find(m => m.id === props.modelValue?.payment_method_id) || null)
const isCash = computed(() => selectedMethod.value?.settlement === 'cash_box')

const boxOptions = computed(() => availableBoxes.value.map(box => ({ title: `${box.code} - ${box.name}`, value: box.id })))

const fieldError = key => props.errors[`${props.errorPrefix}${key}`] || props.errors[key]

const update = patch => emit('update:modelValue', { ...props.modelValue, ...patch })

onMounted(async () => {
  isLoading.value = true
  try {
    const [methodsRes, currenciesRes] = await Promise.all([
      paymentMethodsApi.options(),
      currenciesApi.options(),
    ])

    methods.value = methodsRes?.data || []
    currencies.value = currenciesRes?.data || []

    if (!availableBoxes.value.length)
      await fetchAvailableBoxes()
  }
  catch {
    methods.value = []
  }
  finally {
    isLoading.value = false
  }
})

// Un método de caja toma la sesión abierta de la caja seleccionada.
watch([isCash, currentSession], () => {
  const sessionId = isCash.value && hasOpenSession.value ? currentSession.value?.id : null

  if (props.modelValue?.cash_session_id !== sessionId)
    update({ cash_session_id: sessionId })
}, { immediate: true })

watch(selectedMethod, method => emit('method', method), { immediate: true })

// Si cambia la moneda y el método ya no aplica, se limpia.
watch(availableMethods, list => {
  const current = props.modelValue?.payment_method_id
  if (current && methods.value.length && !list.some(m => m.id === current))
    update({ payment_method_id: null })
})
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="6"
    >
      <VSelect
        :model-value="props.modelValue?.payment_method_id"
        :items="methodOptions"
        :loading="isLoading"
        label="Método de pago *"
        placeholder="Efectivo"
        :density="props.density"
        :error-messages="fieldError('payment_method_id')"
        no-data-text="No hay métodos de pago para la moneda del documento"
        @update:model-value="update({ payment_method_id: $event })"
      />
    </VCol>

    <VCol
      cols="12"
      md="6"
    >
      <VTextField
        :model-value="props.modelValue?.reference"
        :label="selectedMethod?.requires_reference ? 'Referencia / N.° de operación *' : 'Referencia / N.° de operación'"
        placeholder="OP-00012345"
        maxlength="60"
        :density="props.density"
        :error-messages="fieldError('reference')"
        @update:model-value="update({ reference: $event })"
      />
    </VCol>

    <template v-if="isCash">
      <VCol
        v-if="boxOptions.length > 1"
        cols="12"
        md="6"
      >
        <VSelect
          :model-value="selectedBoxId"
          :items="boxOptions"
          :loading="isLoadingBoxes || isLoadingSession"
          label="Caja *"
          placeholder="CJ01 - Caja principal"
          :density="props.density"
          @update:model-value="selectBox($event)"
        />
      </VCol>

      <VCol cols="12">
        <VAlert
          v-if="hasOpenSession"
          type="info"
          variant="tonal"
          density="compact"
        >
          El pago saldrá de la sesión de caja <strong>{{ currentSession?.number }}</strong>.
        </VAlert>
        <VAlert
          v-else
          type="warning"
          variant="tonal"
          density="compact"
        >
          {{ boxOptions.length ? 'La caja seleccionada no tiene una sesión abierta. Abre la caja antes de pagar en efectivo.' : 'No tienes cajas disponibles para pagar en efectivo.' }}
        </VAlert>
        <div
          v-if="fieldError('cash_session_id')"
          class="text-error text-caption mt-1"
        >
          {{ fieldError('cash_session_id') }}
        </div>
      </VCol>
    </template>
  </VRow>
</template>
