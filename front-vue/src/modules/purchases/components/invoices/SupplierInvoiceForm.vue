<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import DocumentLinesTable from '@/components/common/DocumentLinesTable.vue'
import PaymentMethodFields from '@/components/common/PaymentMethodFields.vue'
import ProviderPicker from '@/components/common/ProviderPicker.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import { conceptsApi, currenciesApi, exchangeRatesApi } from '@/modules/finance/services/financeSettingsApi'
import ReceiptPickerDialog from '@/modules/purchases/components/invoices/ReceiptPickerDialog.vue'
import InstallmentPlanFields from '@/modules/purchases/components/payables/InstallmentPlanFields.vue'
import { supplierInvoicesApi } from '@/modules/purchases/services/supplierInvoicesApi'
import { SUPPLIER_DOCUMENT_TYPE_OPTIONS, SUPPLIER_INVOICE_KIND_OPTIONS } from '@/modules/purchases/utils/status'
import { formatAmount } from '@/utils/documentTotals'

/**
 * Formulario de una factura de proveedor (compra con recepciones) o de un gasto directo.
 * Emite `submit` con un FormData listo para `supplierInvoicesApi.create()`; los totales los calcula el backend.
 */
const props = defineProps({
  // Fija el tipo de documento (ej. 'expense' al registrar un gasto desde la caja).
  fixedKind: {
    type: String,
    default: null,
  },

  // Activa "Pagar ahora" al abrir el formulario.
  payNow: {
    type: Boolean,
    default: false,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'cancel'])

const notify = useNotify()
const { can } = useAcl()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const today = () => new Date().toISOString().slice(0, 10)

const addDays = (date, days) => {
  const d = new Date(`${date}T00:00:00`)

  d.setDate(d.getDate() + Number(days || 0))

  return d.toISOString().slice(0, 10)
}

let keySeed = 0
const newLineKey = () => `si-line-${keySeed++}`

const currencies = ref([])
const concepts = ref([])
const taxRate = ref(18)
const providerItem = ref(null)
const creditDays = ref(0)
const totals = ref({ total: '0.00' })
const isReceiptsOpen = ref(false)
const isLoadingPrefill = ref(false)
const purchaseOrder = ref(null)
const dueDateTouched = ref(false)

const form = ref({
  kind: props.fixedKind || 'purchase',
  provider_id: null,
  counterparty_name: '',
  document_type: 'invoice',
  series: '',
  document_number: '',
  issue_date: today(),
  due_date: today(),
  currency_id: null,
  exchange_rate: '',
  prices_include_tax: true,
  concept_id: null,
  receipt_ids: [],
  items: [],
  installments: [],
  notes: '',
  attachment: null,
  pay_now: props.payNow,
  down_payment_amount: '',
  payment: { payment_method_id: null, cash_session_id: null, reference: '' },
  payment_date: today(),
})

const isExpense = computed(() => form.value.kind === 'expense')
const hasProvider = computed(() => Boolean(form.value.provider_id))
const currentCurrency = computed(() => currencies.value.find(c => c.id === form.value.currency_id) || null)
const isForeign = computed(() => Boolean(currentCurrency.value && !currentCurrency.value.is_base))
const currencySymbol = computed(() => currentCurrency.value?.symbol || 'S/')
const currencyDecimals = computed(() => currentCurrency.value?.decimals ?? 2)
const currencyOptions = computed(() => currencies.value.map(c => ({ title: `${c.code} (${c.symbol})`, value: c.id })))
const conceptOptions = computed(() => concepts.value.map(c => ({ title: `${c.code} · ${c.name}`, value: c.id })))
const canPayNow = computed(() => can('supplier_payments.create'))

// Pago mixto: con "Pagar ahora" activo, un monto al contado menor al total deja el resto por financiar en cuotas.
const hasDownPayment = computed(() => form.value.pay_now && canPayNow.value
  && form.value.down_payment_amount !== '' && Number(form.value.down_payment_amount) > 0
  && Number(form.value.down_payment_amount) < Number(totals.value.total || 0))

const remainderTotal = computed(() => hasDownPayment.value
  ? Math.max(0, Number(totals.value.total || 0) - Number(form.value.down_payment_amount))
  : Number(totals.value.total || 0))

const installmentsSum = computed(() => form.value.installments
  .reduce((sum, row) => sum + Math.round(Number(row.amount || 0) * 100), 0) / 100)

const installmentsMatch = computed(() => !form.value.installments.length
  || Math.abs(installmentsSum.value - remainderTotal.value) < 0.005)

watch(() => form.value.pay_now, value => {
  if (!value)
    form.value.down_payment_amount = ''
})

onMounted(async () => {
  try {
    const [currenciesRes, conceptsRes, summaryRes] = await Promise.all([
      currenciesApi.options(),
      conceptsApi.options({ direction: 'expense', 'account_type': 'expense' }),
      configurationApi.getSummary(),
    ])

    currencies.value = currenciesRes?.data || []
    concepts.value = conceptsRes?.data || []

    const company = summaryRes?.data || {}

    taxRate.value = Number(company.default_tax_rate ?? 18)
    form.value.prices_include_tax = company.prices_include_tax ?? true
    form.value.currency_id ||= currencies.value.find(c => c.is_base)?.id || null
  }
  catch {
    notify.error('No se pudieron cargar los datos del formulario.')
  }
})

// El vencimiento por defecto es la emisión más los días de crédito del proveedor.
const refreshDueDate = () => {
  if (!dueDateTouched.value)
    form.value.due_date = addDays(form.value.issue_date, creditDays.value)
}

watch(() => form.value.issue_date, refreshDueDate)

const onProviderSelected = provider => {
  creditDays.value = Number(provider?.credit_days || 0)
  if (provider?.default_currency_id)
    form.value.currency_id = provider.default_currency_id
  refreshDueDate()
}

watch(() => form.value.provider_id, (value, previous) => {
  if (previous && value !== previous) {
    form.value.receipt_ids = []
    purchaseOrder.value = null
  }
  if (!value) {
    creditDays.value = 0
    refreshDueDate()
  }
})

watch(() => form.value.kind, kind => {
  if (kind === 'purchase') {
    form.value.concept_id = null
    form.value.counterparty_name = ''
  }
  else {
    form.value.receipt_ids = []
    purchaseOrder.value = null
  }
})

const suggestExchangeRate = async () => {
  if (!isForeign.value) {
    form.value.exchange_rate = ''

    return
  }

  try {
    const res = await exchangeRatesApi.current(form.value.currency_id, form.value.issue_date || undefined)

    form.value.exchange_rate = res?.data?.rate?.sell_rate ?? ''
  }
  catch {
    form.value.exchange_rate = ''
  }
}

watch(() => form.value.currency_id, suggestExchangeRate)

const lineFromPrefill = item => ({
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
  invoice_description: item.description,
  is_free: !item.item_presentation_id,
  quantity: item.quantity,
  unit_price: item.unit_price,
  discount_percent: item.discount_percent || '0',
  tax_type: item.tax_type,
  purchase_receipt_item_id: item.purchase_receipt_item_id,
  request_number: item.purchase_receipt_number,
})

const loadFromReceipts = async ids => {
  isLoadingPrefill.value = true
  try {
    const res = await supplierInvoicesApi.prefill(ids)
    const data = res?.data || {}

    form.value.receipt_ids = ids
    form.value.items = (data.items || []).map(lineFromPrefill)
    purchaseOrder.value = data.purchase_order || null
    if (data.currency?.id)
      form.value.currency_id = data.currency.id
    if (data.prices_include_tax !== null && data.prices_include_tax !== undefined)
      form.value.prices_include_tax = Boolean(data.prices_include_tax)
  }
  catch (err) {
    notify.error(err?.data?.message || 'No se pudieron cargar las recepciones.')
  }
  finally {
    isLoadingPrefill.value = false
  }
}

const removeReceipts = () => {
  form.value.receipt_ids = []
  purchaseOrder.value = null
  form.value.items = form.value.items.filter(row => !row.purchase_receipt_item_id)
}

const lineDescription = row => {
  if (row.is_free)
    return row.description?.trim() || ''
  if (row.invoice_description)
    return row.invoice_description
  const item = row.initialItem

  return item ? `${item.name}${item.presentation_name ? ` (${item.presentation_name})` : ''}` : ''
}

const buildFormData = () => {
  const data = new FormData()

  const put = (key, value) => {
    if (value !== null && value !== undefined && value !== '')
      data.append(key, value)
  }

  put('kind', form.value.kind)
  put('provider_id', form.value.provider_id)
  if (!hasProvider.value)
    put('counterparty_name', form.value.counterparty_name?.trim())
  put('document_type', hasProvider.value ? form.value.document_type : 'other')
  put('series', form.value.series?.trim())
  put('document_number', form.value.document_number?.trim())
  put('issue_date', form.value.issue_date)
  put('due_date', form.value.due_date)
  put('currency_id', form.value.currency_id)
  if (isForeign.value)
    put('exchange_rate', form.value.exchange_rate)
  data.append('prices_include_tax', form.value.prices_include_tax ? '1' : '0')
  if (isExpense.value)
    put('concept_id', form.value.concept_id)
  if (purchaseOrder.value?.id)
    put('purchase_order_id', purchaseOrder.value.id)
  form.value.receipt_ids.forEach((id, i) => put(`receipt_ids[${i}]`, id))

  form.value.items.forEach((row, i) => {
    put(`items[${i}][description]`, lineDescription(row))
    if (!row.is_free)
      put(`items[${i}][item_presentation_id]`, row.item_presentation_id)
    put(`items[${i}][purchase_receipt_item_id]`, row.purchase_receipt_item_id)
    put(`items[${i}][quantity]`, row.quantity)
    put(`items[${i}][unit_price]`, row.unit_price)
    put(`items[${i}][discount_percent]`, row.discount_percent || 0)
    put(`items[${i}][tax_type]`, row.tax_type)
  })

  form.value.installments.forEach((row, i) => {
    put(`installments[${i}][due_date]`, row.due_date)
    put(`installments[${i}][amount]`, row.amount)
  })

  put('notes', form.value.notes?.trim())

  const file = Array.isArray(form.value.attachment) ? form.value.attachment[0] : form.value.attachment
  if (file)
    data.append('attachment', file)

  if (form.value.pay_now && canPayNow.value) {
    data.append('pay_now', '1')
    if (hasDownPayment.value)
      put('down_payment_amount', form.value.down_payment_amount)
    put('payment[payment_method_id]', form.value.payment.payment_method_id)
    put('payment[cash_session_id]', form.value.payment.cash_session_id)
    put('payment[payment_date]', form.value.payment_date)
    put('payment[reference]', form.value.payment.reference?.trim())
  }

  return data
}

const handleSubmit = () => {
  clearErrors()

  if (!installmentsMatch.value) {
    notify.error('La suma de las cuotas debe ser igual al total de la factura.')

    return
  }

  emit('submit', buildFormData())
}

defineExpose({ setErrors, clearErrors })
</script>

<template>
  <VForm @submit.prevent="handleSubmit">
    <VAlert
      v-if="generalError"
      type="error"
      density="compact"
      class="mb-4"
    >
      {{ generalError }}
    </VAlert>

    <VRadioGroup
      v-if="!props.fixedKind"
      v-model="form.kind"
      inline
      class="mt-0 mb-2"
      hide-details
    >
      <VRadio
        v-for="option in SUPPLIER_INVOICE_KIND_OPTIONS"
        :key="option.value"
        :value="option.value"
        :label="option.value === 'purchase' ? 'Factura de compra' : 'Gasto directo'"
        color="primary"
      />
    </VRadioGroup>

    <VRow>
      <VCol
        cols="12"
        md="6"
      >
        <ProviderPicker
          v-model="form.provider_id"
          :initial-item="providerItem"
          :label="isExpense ? 'Proveedor' : 'Proveedor *'"
          :clearable="isExpense"
          :error-messages="errors.provider_id"
          @update:model-value="clearFieldError('provider_id')"
          @selected="onProviderSelected"
        />
      </VCol>

      <VCol
        v-if="isExpense && !hasProvider"
        cols="12"
        md="6"
      >
        <VTextField
          v-model="form.counterparty_name"
          label="Beneficiario *"
          placeholder="Juan Pérez (movilidad)"
          maxlength="150"
          :error-messages="errors.counterparty_name"
          @input="clearFieldError('counterparty_name')"
        />
      </VCol>

      <VCol
        v-if="isExpense"
        cols="12"
        md="6"
      >
        <VAutocomplete
          v-model="form.concept_id"
          :items="conceptOptions"
          label="Concepto de egreso *"
          placeholder="Servicios básicos"
          :error-messages="errors.concept_id"
          @update:model-value="clearFieldError('concept_id')"
        />
      </VCol>

      <VCol
        v-if="hasProvider"
        cols="12"
        sm="6"
        md="3"
      >
        <VSelect
          v-model="form.document_type"
          :items="SUPPLIER_DOCUMENT_TYPE_OPTIONS"
          label="Tipo de documento *"
          placeholder="Factura"
          :error-messages="errors.document_type"
        />
      </VCol>

      <VCol
        cols="6"
        sm="3"
        md="2"
      >
        <VTextField
          v-model="form.series"
          label="Serie"
          placeholder="F001"
          maxlength="10"
          :error-messages="errors.series"
        />
      </VCol>

      <VCol
        cols="6"
        sm="3"
        md="3"
      >
        <VTextField
          v-model="form.document_number"
          label="Número *"
          placeholder="000123"
          maxlength="20"
          :error-messages="errors.document_number"
          @input="clearFieldError('document_number')"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model="form.issue_date"
          label="Fecha de emisión *"
          placeholder="2026-10-20"
          type="date"
          :error-messages="errors.issue_date"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
        md="3"
      >
        <VTextField
          v-model="form.due_date"
          label="Vencimiento"
          placeholder="2026-11-19"
          type="date"
          :hint="creditDays ? `Crédito del proveedor: ${creditDays} días` : ''"
          persistent-hint
          :error-messages="errors.due_date"
          @update:model-value="dueDateTouched = true"
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
          :disabled="form.receipt_ids.length > 0"
          :error-messages="errors.currency_id"
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
          placeholder="3.7500"
          type="number"
          step="0.0001"
          :error-messages="errors.exchange_rate"
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

    <div class="d-flex flex-wrap align-center gap-3 mt-6 mb-2">
      <span class="text-subtitle-1 font-weight-medium">Líneas del documento</span>
      <VChip
        v-if="purchaseOrder"
        size="small"
        color="info"
        variant="tonal"
      >
        {{ purchaseOrder.number }} · {{ form.receipt_ids.length }} recepción(es)
      </VChip>
      <VBtn
        v-if="form.receipt_ids.length"
        size="small"
        variant="text"
        color="error"
        prepend-icon="tabler-unlink"
        @click="removeReceipts"
      >
        Quitar recepciones
      </VBtn>
    </div>

    <VAlert
      v-if="errors.items || errors.receipt_ids"
      type="error"
      density="compact"
      class="mb-3"
    >
      {{ errors.items || errors.receipt_ids }}
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
      @totals="totals = $event"
    >
      <template #actions>
        <VBtn
          v-if="!isExpense"
          variant="tonal"
          color="info"
          prepend-icon="tabler-truck-loading"
          :disabled="!hasProvider"
          :loading="isLoadingPrefill"
          @click="isReceiptsOpen = true"
        >
          Cargar desde recepciones
        </VBtn>
      </template>
    </DocumentLinesTable>

    <VCard
      v-if="canPayNow"
      variant="outlined"
      class="mt-6"
    >
      <VCardText>
        <VSwitch
          v-model="form.pay_now"
          label="Pagar ahora (el total, o un adelanto financiando el resto en cuotas)"
          density="compact"
          hide-details
        />

        <div
          v-if="form.pay_now"
          class="mt-4"
        >
          <VRow>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="form.down_payment_amount"
                label="Monto al contado"
                placeholder="Déjalo vacío para pagar el total"
                type="number"
                step="0.01"
                min="0"
                :suffix="currencySymbol"
                hint="El saldo se financia con las cuotas definidas abajo."
                persistent-hint
                :error-messages="errors.down_payment_amount"
              />
            </VCol>
            <VCol
              v-if="hasDownPayment"
              cols="12"
              md="4"
              class="d-flex align-center"
            >
              <div>
                <div class="text-caption text-medium-emphasis">
                  Saldo a financiar en cuotas
                </div>
                <div class="text-subtitle-1 font-weight-medium">
                  {{ formatAmount(remainderTotal, currencySymbol, currencyDecimals) }}
                </div>
              </div>
            </VCol>
          </VRow>

          <PaymentMethodFields
            v-model="form.payment"
            class="mt-2"
            :currency-id="form.currency_id"
            :errors="errors"
            error-prefix="payment."
          />
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.payment_date"
                :label="hasDownPayment ? 'Fecha del pago al contado *' : 'Fecha de pago *'"
                placeholder="2026-10-20"
                type="date"
                :error-messages="errors['payment.payment_date']"
              />
            </VCol>
          </VRow>
        </div>
      </VCardText>
    </VCard>

    <InstallmentPlanFields
      v-model="form.installments"
      class="mt-6"
      :total="remainderTotal"
      :issue-date="form.issue_date"
      :default-due-date="form.due_date"
      :currency-symbol="currencySymbol"
      :currency-decimals="currencyDecimals"
      :errors="errors"
    />

    <VRow class="mt-4">
      <VCol
        cols="12"
        md="6"
      >
        <VTextarea
          v-model="form.notes"
          label="Observaciones"
          placeholder="Factura por servicios de octubre"
          rows="3"
          maxlength="1000"
          :error-messages="errors.notes"
        />
      </VCol>
      <VCol
        cols="12"
        md="6"
      >
        <VFileInput
          v-model="form.attachment"
          label="Adjunto (PDF, imagen o XML)"
          placeholder="factura-F001-000123.pdf"
          accept=".pdf,.jpg,.jpeg,.png,.xml"
          prepend-icon=""
          prepend-inner-icon="tabler-paperclip"
          show-size
          clearable
          :error-messages="errors.attachment"
        />
      </VCol>
    </VRow>

    <VDivider class="my-6" />

    <div class="d-flex flex-wrap align-center gap-3">
      <VBtn
        type="submit"
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="props.isSubmitting"
      >
        {{ hasDownPayment ? 'Registrar y pagar adelanto' : (form.pay_now && canPayNow ? 'Registrar y pagar' : 'Registrar') }}
      </VBtn>
      <VBtn
        variant="tonal"
        color="secondary"
        :disabled="props.isSubmitting"
        @click="emit('cancel')"
      >
        Cancelar
      </VBtn>
      <VSpacer />
      <span class="text-h6">
        Total: {{ formatAmount(totals.total, currencySymbol, currencyDecimals) }}
      </span>
    </div>

    <ReceiptPickerDialog
      v-model="isReceiptsOpen"
      :provider-id="form.provider_id"
      :selected-ids="form.receipt_ids"
      @select="loadFromReceipts"
    />
  </VForm>
</template>
