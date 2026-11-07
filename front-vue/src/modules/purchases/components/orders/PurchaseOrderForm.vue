<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import DocumentLinesTable from '@/components/common/DocumentLinesTable.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import { currenciesApi, exchangeRatesApi } from '@/modules/finance/services/financeSettingsApi'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import PendingRequestItemsDialog from '@/modules/purchases/components/orders/PendingRequestItemsDialog.vue'
import { PAYMENT_CONDITION_OPTIONS } from '@/modules/purchases/utils/status'

/**
 * Formulario de una orden de compra (alta y edición de borrador/rechazada).
 * Emite `submit` con `{ action: 'save' | 'send', payload }`; los totales que se guardan los calcula el backend.
 */
const props = defineProps({
  order: {
    type: Object,
    default: null,
  },

  // Datos para precargar una OC nueva (ej. desde un requerimiento): { warehouse_id, lines }.
  prefill: {
    type: Object,
    default: null,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'cancel'])

const notify = useNotify()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.order?.id))

const currencies = ref([])
const warehouseOptions = ref([])
const taxRate = ref(18)
const providerItem = ref(null)
const isPendingOpen = ref(false)

let keySeed = 0
const newLineKey = () => `po-line-${keySeed++}`

const today = () => new Date().toISOString().slice(0, 10)

const form = ref({
  provider_id: null,
  warehouse_id: null,
  order_date: today(),
  expected_date: '',
  currency_id: null,
  exchange_rate: '',
  payment_condition: 'cash',
  credit_days: null,
  prices_include_tax: false,
  notes: '',
  terms: '',
  items: [],
})

const currencyOptions = computed(() => currencies.value.map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id })))
const currentCurrency = computed(() => currencies.value.find(c => c.id === form.value.currency_id) || null)
const isForeign = computed(() => Boolean(currentCurrency.value && !currentCurrency.value.is_base))
const currencySymbol = computed(() => currentCurrency.value?.symbol || 'S/')
const currencyDecimals = computed(() => currentCurrency.value?.decimals ?? 2)

const linkedRequestItemIds = computed(() => form.value.items.map(i => i.purchase_request_item_id).filter(Boolean))

const lineFromRequestItem = item => ({
  key: newLineKey(),
  item_presentation_id: item.item_presentation_id,
  initialItem: item.presentation
    ? {
      presentation_id: item.item_presentation_id,
      code: item.catalog_item?.code || '',
      name: item.catalog_item?.name || '',
      presentation_name: item.presentation.name,
    }
    : null,
  description: item.item_presentation_id ? null : item.description,
  is_free: !item.item_presentation_id,
  quantity: item.pending_quantity,
  unit_price: '',
  discount_percent: '0',
  tax_type: 'taxable',
  purchase_request_item_id: item.id,
  request_number: item.request?.number || null,
})

const lineFromOrderItem = item => ({
  key: newLineKey(),
  item_presentation_id: item.item_presentation_id,
  initialItem: item.presentation
    ? {
      presentation_id: item.item_presentation_id,
      code: item.catalog_item?.code || '',
      name: item.catalog_item?.name || '',
      presentation_name: item.presentation.name,
    }
    : null,
  description: item.item_presentation_id ? null : item.description,
  is_free: !item.item_presentation_id,
  quantity: item.quantity,
  unit_price: item.unit_price,
  discount_percent: item.discount_percent,
  tax_type: item.tax_type,
  purchase_request_item_id: item.purchase_request_item_id,
  request_number: item.request_number || null,
})

onMounted(async () => {
  try {
    const [currenciesRes, warehousesRes, summaryRes] = await Promise.all([
      currenciesApi.options(),
      warehousesApi.options(),
      configurationApi.getSummary(),
    ])

    currencies.value = currenciesRes?.data || []
    warehouseOptions.value = (warehousesRes?.data || []).map(w => ({ title: `${w.code} - ${w.name}`, value: w.id }))

    const company = summaryRes?.data || {}

    if (!isEditing.value) {
      taxRate.value = Number(company.default_tax_rate ?? 18)
      form.value.prices_include_tax = Boolean(company.prices_include_tax)
      form.value.currency_id ||= currencies.value.find(c => c.is_base)?.id || null

      if (!form.value.warehouse_id && warehouseOptions.value.length === 1)
        form.value.warehouse_id = warehouseOptions.value[0].value
    }
  }
  catch {
    notify.error('No se pudieron cargar los datos del formulario.')
  }
})

watch(() => props.order, order => {
  if (!order)
    return

  taxRate.value = Number(order.tax_rate)
  providerItem.value = order.provider
    ? {
      id: order.provider.id,
      document_type: order.provider.document_type,
      document_number: order.provider.document_number,
      business_name: order.provider.business_name,
    }
    : null
  form.value = {
    provider_id: order.provider_id,
    warehouse_id: order.warehouse_id,
    order_date: order.order_date,
    expected_date: order.expected_date || '',
    currency_id: order.currency_id,
    exchange_rate: order.exchange_rate,
    payment_condition: order.payment_condition,
    credit_days: order.credit_days || null,
    prices_include_tax: order.prices_include_tax,
    notes: order.notes || '',
    terms: order.terms || '',
    items: (order.items || []).map(lineFromOrderItem),
  }
}, { immediate: true })

watch(() => props.prefill, prefill => {
  if (!prefill || isEditing.value)
    return

  if (prefill.warehouse_id)
    form.value.warehouse_id = prefill.warehouse_id
  if (prefill.lines)
    form.value.items = prefill.lines.map(lineFromRequestItem)
}, { immediate: true })

// Al elegir proveedor se heredan su moneda y su condición de pago.
const onProviderSelected = provider => {
  if (!provider)
    return

  if (provider.default_currency_id)
    form.value.currency_id = provider.default_currency_id

  if (Number(provider.credit_days) > 0) {
    form.value.payment_condition = 'credit'
    form.value.credit_days = Number(provider.credit_days)
  }
  else {
    form.value.payment_condition = 'cash'
    form.value.credit_days = null
  }
}

// Moneda extranjera: se sugiere el tipo de cambio de venta vigente (editable).
const suggestExchangeRate = async () => {
  if (!isForeign.value) {
    form.value.exchange_rate = ''

    return
  }

  try {
    const res = await exchangeRatesApi.current(form.value.currency_id, form.value.order_date || undefined)

    form.value.exchange_rate = res?.data?.rate?.sell_rate ?? ''
  }
  catch {
    form.value.exchange_rate = ''
  }
}

watch(() => form.value.currency_id, (_, previous) => {
  // Al cargar una OC existente la moneda pasa de nula a su valor y se conserva su tipo de cambio.
  const changedByUser = previous !== undefined && previous !== null
  const newWithoutRate = !isEditing.value && isForeign.value && !form.value.exchange_rate

  if (changedByUser || newWithoutRate)
    suggestExchangeRate()
})

const addFromRequests = items => {
  form.value.items.push(...items.map(lineFromRequestItem))
}

const handleSubmit = action => {
  clearErrors()

  emit('submit', {
    action,
    payload: {
      provider_id: form.value.provider_id,
      warehouse_id: form.value.warehouse_id,
      order_date: form.value.order_date,
      expected_date: form.value.expected_date || null,
      currency_id: form.value.currency_id,
      exchange_rate: isForeign.value && form.value.exchange_rate !== '' ? form.value.exchange_rate : null,
      payment_condition: form.value.payment_condition,
      credit_days: form.value.payment_condition === 'credit' ? form.value.credit_days : null,
      prices_include_tax: form.value.prices_include_tax,
      notes: form.value.notes?.trim() || null,
      terms: form.value.terms?.trim() || null,
      items: form.value.items.map(row => ({
        item_presentation_id: row.is_free ? null : row.item_presentation_id,
        description: row.is_free ? row.description?.trim() : null,
        quantity: row.quantity,
        unit_price: row.unit_price,
        discount_percent: row.discount_percent || 0,
        tax_type: row.tax_type,
        purchase_request_item_id: row.purchase_request_item_id || null,
      })),
    },
  })
}

defineExpose({ setErrors, clearErrors })
</script>

<template>
  <VForm @submit.prevent="handleSubmit('save')">
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <ProviderPicker
          v-model="form.provider_id"
          :initial-item="providerItem"
          label="Proveedor *"
          :error-messages="errors.provider_id"
          @update:model-value="clearFieldError('provider_id')"
          @selected="onProviderSelected"
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VAutocomplete
          v-model="form.warehouse_id"
          :items="warehouseOptions"
          label="Almacén de entrega *"
          placeholder="ALM-01 - Almacén Central"
          :error-messages="errors.warehouse_id"
          @update:model-value="clearFieldError('warehouse_id')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model="form.order_date"
          label="Fecha de emisión *"
          type="date"
          :error-messages="errors.order_date"
          @input="clearFieldError('order_date')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model="form.expected_date"
          label="Entrega esperada"
          type="date"
          :min="form.order_date"
          :error-messages="errors.expected_date"
          @input="clearFieldError('expected_date')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VSelect
          v-model="form.currency_id"
          :items="currencyOptions"
          label="Moneda *"
          placeholder="PEN (S/)"
          :error-messages="errors.currency_id"
          @update:model-value="clearFieldError('currency_id')"
        />
      </VCol>

      <VCol
        v-if="isForeign"
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model="form.exchange_rate"
          label="Tipo de cambio *"
          placeholder="3.8000"
          type="number"
          step="0.0001"
          min="0"
          hint="Sugerido: tipo de cambio de venta vigente"
          persistent-hint
          :error-messages="errors.exchange_rate"
          @input="clearFieldError('exchange_rate')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VSelect
          v-model="form.payment_condition"
          :items="PAYMENT_CONDITION_OPTIONS"
          label="Condición de pago *"
          placeholder="Contado"
          :error-messages="errors.payment_condition"
          @update:model-value="clearFieldError('payment_condition')"
        />
      </VCol>

      <VCol
        v-if="form.payment_condition === 'credit'"
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model.number="form.credit_days"
          label="Días de crédito *"
          placeholder="30"
          type="number"
          min="1"
          max="365"
          :error-messages="errors.credit_days"
          @input="clearFieldError('credit_days')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
        class="d-flex align-center"
      >
        <VSwitch
          v-model="form.prices_include_tax"
          label="Los precios incluyen IGV"
          density="compact"
          hide-details
        />
      </VCol>
    </VRow>

    <div class="text-subtitle-1 font-weight-medium mt-6 mb-2">
      Líneas de la orden
    </div>

    <VAlert
      v-if="errors.items"
      type="error"
      density="compact"
      class="mb-3"
    >
      {{ errors.items }}
    </VAlert>

    <DocumentLinesTable
      v-model="form.items"
      context="purchase"
      allow-free-text
      :prices-include-tax="form.prices_include_tax"
      :tax-rate="taxRate"
      :currency-symbol="currencySymbol"
      :currency-decimals="currencyDecimals"
      :errors="errors"
    >
      <template #actions>
        <VBtn
          variant="tonal"
          color="info"
          prepend-icon="tabler-clipboard-list"
          @click="isPendingOpen = true"
        >
          Agregar desde requerimientos
        </VBtn>
      </template>
    </DocumentLinesTable>

    <VRow class="mt-4">
      <VCol
        cols="12"
        md="6"
      >
        <VTextarea
          v-model="form.notes"
          label="Observaciones"
          placeholder="Entregar en la puerta 2 en horario de oficina"
          rows="3"
          maxlength="2000"
          :error-messages="errors.notes"
          @input="clearFieldError('notes')"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VTextarea
          v-model="form.terms"
          label="Términos y condiciones"
          placeholder="Pago contra entrega, garantía de 6 meses"
          rows="3"
          maxlength="2000"
          :error-messages="errors.terms"
          @input="clearFieldError('terms')"
        />
      </VCol>
    </VRow>

    <VDivider class="my-6" />

    <div class="d-flex flex-wrap gap-3">
      <VBtn
        type="submit"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="props.isSubmitting"
      >
        {{ isEditing ? 'Guardar cambios' : 'Guardar borrador' }}
      </VBtn>
      <VBtn
        color="success"
        variant="tonal"
        prepend-icon="tabler-send"
        :disabled="props.isSubmitting"
        @click="handleSubmit('send')"
      >
        Guardar y enviar
      </VBtn>
      <VBtn
        variant="tonal"
        color="secondary"
        :disabled="props.isSubmitting"
        @click="emit('cancel')"
      >
        Cancelar
      </VBtn>
    </div>

    <PendingRequestItemsDialog
      v-model="isPendingOpen"
      :warehouse-id="form.warehouse_id"
      :exclude-ids="linkedRequestItemIds"
      @select="addFromRequests"
    />
  </VForm>
</template>
