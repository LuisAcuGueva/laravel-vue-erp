<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import ClientPicker from '@/components/common/ClientPicker.vue'
import DocumentLinesTable from '@/components/common/DocumentLinesTable.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useNotify } from '@/composables/useNotify'
import { clientsApi } from '@/modules/clients/services/clientsApi'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import { currenciesApi, exchangeRatesApi } from '@/modules/finance/services/financeSettingsApi'
import { employeesApi } from '@/modules/maintenance/services/employeesApi'
import { PRICE_TYPE_OPTIONS } from '@/modules/sales/utils/status'

const props = defineProps({
  quotation: {
    type: Object,
    default: null,
  },
  prefill: {
    type: Object,
    default: null,
  },
  readonly: {
    type: Boolean,
    default: false,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['submit', 'cancel'])

const { can } = useAcl()
const notify = useNotify()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const isEditing = computed(() => Boolean(props.quotation?.id))
const isReadonly = computed(() => props.readonly || (props.quotation && props.quotation.status !== 'draft'))

const currencies = ref([])
const sellers = ref([])
const clientItem = ref(null)
const clientContacts = ref([])
const taxRate = ref(18)
const maxLineDiscount = ref(15)
const maxGlobalDiscount = ref(20)

let keySeed = 0
const newLineKey = () => `quote-line-${keySeed++}`

const today = () => new Date().toISOString().slice(0, 10)

const form = ref({
  client_id: null,
  contact_id: null,
  seller_id: null,
  issue_date: today(),
  valid_until: '',
  currency_id: null,
  exchange_rate: '',
  price_type: 'retail',
  prices_include_tax: true,
  global_discount_percent: '0',
  delivery_time: '',
  payment_terms: '',
  notes: '',
  terms: '',
  items: [],
})

const currencyOptions = computed(() =>
  currencies.value.map(c => ({
    title: `${c.code} (${c.symbol})`,
    value: c.id,
  })),
)

const currentCurrency = computed(() =>
  currencies.value.find(c => c.id === form.value.currency_id) || null,
)

const isForeign = computed(() =>
  Boolean(currentCurrency.value && !currentCurrency.value.is_base),
)

const currencySymbol = computed(() => currentCurrency.value?.symbol || 'S/')
const currencyDecimals = computed(() => currentCurrency.value?.decimals ?? 2)

const sellerOptions = computed(() =>
  sellers.value.map(s => ({
    title: s.full_name,
    value: s.id,
  })),
)

const contactOptions = computed(() =>
  clientContacts.value.map(c => ({
    title: c.name + (c.position ? ` (${c.position})` : ''),
    value: c.id,
  })),
)

// Detección en vivo de topes de descuento superados
const lineDiscountExceeded = computed(() =>
  form.value.items.some(
    item => Number(item.discount_percent || 0) > maxLineDiscount.value,
  ),
)

const globalDiscountExceeded = computed(() =>
  Number(form.value.global_discount_percent || 0) > maxGlobalDiscount.value,
)

const hasDiscountExceeded = computed(() =>
  lineDiscountExceeded.value || globalDiscountExceeded.value,
)

const lineFromQuotationItem = item => ({
  key: newLineKey(),
  item_presentation_id: item.item_presentation_id,
  initialItem: item.presentation
    ? {
      presentation_id: item.item_presentation_id,
      code: item.catalog_item?.code || '',
      name: item.catalog_item?.name || item.description,
      presentation_name: item.presentation.name,
      tax_type: item.tax_type,
    }
    : null,
  description: item.item_presentation_id ? null : item.description,
  is_free: !item.item_presentation_id,
  quantity: item.quantity,
  unit_price: item.unit_price,
  discount_percent: item.discount_percent || '0',
  tax_type: item.tax_type || 'taxable',
})

const loadClientDetails = async clientId => {
  if (!clientId) {
    clientContacts.value = []

    return
  }
  try {
    const res = await clientsApi.get(clientId)
    const client = res?.data || {}

    clientItem.value = client
    clientContacts.value = client.contacts || []

    if (!isEditing.value) {
      if (client.default_price_type) {
        form.value.price_type = client.default_price_type
      }
      const primaryContact = clientContacts.value.find(c => c.is_primary)
      if (primaryContact && !form.value.contact_id) {
        form.value.contact_id = primaryContact.id
      }
    }
  }
  catch {
    // Silencioso
  }
}

const onClientSelected = client => {
  if (!client) {
    clientItem.value = null
    clientContacts.value = []
    form.value.contact_id = null

    return
  }
  loadClientDetails(typeof client === 'string' ? client : client.id)
}

// Carga de tipo de cambio automática si es moneda extranjera
const fetchExchangeRate = async () => {
  if (!isForeign.value || !form.value.currency_id) {
    form.value.exchange_rate = ''

    return
  }
  try {
    const res = await exchangeRatesApi.current(
      form.value.currency_id,
      form.value.issue_date || today(),
    )

    if (res?.data?.sale_rate) {
      form.value.exchange_rate = String(res.data.sale_rate)
    }
  }
  catch {
    // Si no hay TC registrado hoy, el usuario lo puede tipear manualmente
  }
}

watch(() => form.value.currency_id, fetchExchangeRate)
watch(() => form.value.issue_date, () => {
  if (isForeign.value && !form.value.exchange_rate) {
    fetchExchangeRate()
  }
})

onMounted(async () => {
  try {
    const [currenciesRes, employeesRes, summaryRes, paramsRes] = await Promise.allSettled([
      currenciesApi.options(),
      employeesApi.options(),
      configurationApi.getSummary(),
      configurationApi.listParameters({ group_key: 'sales' }),
    ])

    if (currenciesRes.status === 'fulfilled') {
      currencies.value = currenciesRes.value?.data || []
    }
    if (employeesRes.status === 'fulfilled') {
      sellers.value = employeesRes.value?.data || []
    }

    const company = summaryRes.status === 'fulfilled' ? summaryRes.value?.data || {} : {}

    if (paramsRes.status === 'fulfilled') {
      const pList = paramsRes.value?.data || []
      const pLine = pList.find(p => p.item_key === 'max_line_discount_percent')
      const pGlobal = pList.find(p => p.item_key === 'max_global_discount_percent')
      if (pLine?.value) maxLineDiscount.value = Number(pLine.value)
      if (pGlobal?.value) maxGlobalDiscount.value = Number(pGlobal.value)
    }

    if (!isEditing.value) {
      taxRate.value = Number(company.default_tax_rate ?? 18)
      form.value.prices_include_tax = Boolean(company.prices_include_tax ?? true)
      form.value.currency_id ||= currencies.value.find(c => c.is_base)?.id || null

      if (props.prefill?.client_id) {
        form.value.client_id = props.prefill.client_id
        loadClientDetails(props.prefill.client_id)
      }
    }
  }
  catch {
    notify.error('No se pudieron cargar los datos iniciales del formulario.')
  }
})

watch(() => props.quotation, quotation => {
  if (!quotation) return

  taxRate.value = Number(quotation.tax_rate ?? 18)
  form.value = {
    client_id: quotation.client?.id || quotation.client_id,
    contact_id: quotation.contact?.id || quotation.contact_id || null,
    seller_id: quotation.seller?.id || quotation.seller_id || null,
    issue_date: quotation.issue_date,
    valid_until: quotation.valid_until || '',
    currency_id: quotation.currency?.id || quotation.currency_id,
    exchange_rate: quotation.exchange_rate ? String(quotation.exchange_rate) : '',
    price_type: quotation.price_type || 'retail',
    prices_include_tax: Boolean(quotation.prices_include_tax),
    global_discount_percent: quotation.global_discount_percent ? String(quotation.global_discount_percent) : '0',
    delivery_time: quotation.delivery_time || '',
    payment_terms: quotation.payment_terms || '',
    notes: quotation.notes || '',
    terms: quotation.terms || '',
    items: (quotation.items || []).map(lineFromQuotationItem),
  }

  if (quotation.client) {
    clientItem.value = quotation.client
    loadClientDetails(quotation.client.id)
  }
  else if (quotation.client_id) {
    loadClientDetails(quotation.client_id)
  }
}, { immediate: true })

const handleSubmit = action => {
  clearErrors()

  if (!form.value.client_id) {
    setErrors({ errors: { client_id: ['El cliente es obligatorio.'] } })

    return
  }

  if (!form.value.items.length) {
    notify.error('La cotización debe tener al menos un artículo o servicio.')

    return
  }

  if (isForeign.value && (!form.value.exchange_rate || Number(form.value.exchange_rate) <= 0)) {
    setErrors({ errors: { exchange_rate: ['El tipo de cambio es obligatorio para moneda extranjera.'] } })

    return
  }

  const payload = {
    client_id: form.value.client_id,
    contact_id: form.value.contact_id || null,
    seller_id: form.value.seller_id || null,
    issue_date: form.value.issue_date,
    valid_until: form.value.valid_until || null,
    currency_id: form.value.currency_id,
    exchange_rate: isForeign.value ? Number(form.value.exchange_rate) : null,
    price_type: form.value.price_type,
    prices_include_tax: form.value.prices_include_tax,
    global_discount_percent: Number(form.value.global_discount_percent || 0),
    delivery_time: form.value.delivery_time || null,
    payment_terms: form.value.payment_terms || null,
    notes: form.value.notes || null,
    terms: form.value.terms || null,
    items: form.value.items.map(row => {
      if (row.is_free || !row.item_presentation_id) {
        return {
          description: row.description || '',
          quantity: Number(row.quantity),
          unit_price: Number(row.unit_price),
          discount_percent: Number(row.discount_percent || 0),
          tax_type: row.tax_type || 'taxable',
        }
      }

      return {
        item_presentation_id: row.item_presentation_id,
        quantity: Number(row.quantity),
        unit_price: row.unit_price ? Number(row.unit_price) : null,
        discount_percent: Number(row.discount_percent || 0),
        description: row.description || null,
      }
    }),
  }

  emit('submit', { action, payload })
}

defineExpose({ setErrors, clearErrors })
</script>

<template>
  <form @submit.prevent="handleSubmit('save')">
    <VCard class="mb-6">
      <VCardText>
        <VAlert
          v-if="generalError"
          type="error"
          variant="tonal"
          closable
          class="mb-4"
        >
          {{ generalError }}
        </VAlert>

        <VAlert
          v-if="hasDiscountExceeded && !can('sales.discount_override')"
          type="warning"
          variant="tonal"
          icon="tabler-alert-triangle"
          class="mb-4"
        >
          <strong>Atención:</strong> Uno o más descuentos superan el tope permitido por política comercial (Ítem máx. {{ maxLineDiscount }}%, Global máx. {{ maxGlobalDiscount }}%). Se requiere autorización de un supervisor para emitir esta cotización.
        </VAlert>

        <VAlert
          v-else-if="hasDiscountExceeded && can('sales.discount_override')"
          type="info"
          variant="tonal"
          icon="tabler-shield-check"
          class="mb-4"
        >
          <strong>Descuento extraordinario:</strong> Los descuentos superan la política estándar, pero su usuario cuenta con autorización para aplicarlos. Quedará registrado en la auditoría del documento.
        </VAlert>

        <VRow>
          <!-- Fila 1: Cliente, Contacto, Vendedor -->
          <VCol
            cols="12"
            md="5"
          >
            <ClientPicker
              v-model="form.client_id"
              :initial-item="clientItem"
              label="Cliente *"
              :readonly="isReadonly"
              :error-messages="errors.client_id"
              @selected="onClientSelected"
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <VSelect
              v-model="form.contact_id"
              :items="contactOptions"
              label="Contacto"
              placeholder="Persona de contacto"
              clearable
              :readonly="isReadonly"
              :error-messages="errors.contact_id"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <VAutocomplete
              v-model="form.seller_id"
              :items="sellerOptions"
              label="Vendedor"
              placeholder="Vendedor responsable"
              clearable
              :readonly="isReadonly"
              :error-messages="errors.seller_id"
            />
          </VCol>

          <!-- Fila 2: Fechas, Moneda, TC, Tipo de Precio -->
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="form.issue_date"
              label="Fecha de emisión *"
              placeholder="YYYY-MM-DD"
              type="date"
              :readonly="isReadonly"
              :error-messages="errors.issue_date"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <VTextField
              v-model="form.valid_until"
              label="Válida hasta"
              placeholder="Por defecto 15 días"
              type="date"
              clearable
              :readonly="isReadonly"
              :error-messages="errors.valid_until"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="form.currency_id"
              :items="currencyOptions"
              label="Moneda *"
              placeholder="PEN / USD"
              :readonly="isReadonly"
              :error-messages="errors.currency_id"
            />
          </VCol>

          <VCol
            v-if="isForeign"
            cols="12"
            sm="6"
            md="2"
          >
            <VTextField
              v-model="form.exchange_rate"
              label="Tipo de cambio *"
              placeholder="3.8000"
              type="number"
              step="0.0001"
              min="0.0001"
              :readonly="isReadonly"
              :error-messages="errors.exchange_rate"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="form.price_type"
              :items="PRICE_TYPE_OPTIONS"
              label="Lista de precios *"
              placeholder="Minorista / Mayorista"
              :readonly="isReadonly"
              :error-messages="errors.price_type"
            />
          </VCol>

          <VCol
            cols="12"
            sm="6"
            md="2"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="form.prices_include_tax"
              label="Precios con IGV"
              color="primary"
              :readonly="isReadonly"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabla editable de líneas -->
    <VCard class="mb-6">
      <VCardItem>
        <template #title>
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-list"
              size="22"
            />
            <span>Artículos y Servicios</span>
          </div>
        </template>
      </VCardItem>

      <VCardText class="pa-4">
        <DocumentLinesTable
          v-model="form.items"
          context="sale"
          allow-free-text
          :prices-include-tax="form.prices_include_tax"
          :tax-rate="taxRate"
          :currency-symbol="currencySymbol"
          :currency-decimals="currencyDecimals"
          :errors="errors"
          :readonly="isReadonly"
        />
      </VCardText>
    </VCard>

    <!-- Condiciones comerciales y Totales -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <VTextField
              v-model="form.global_discount_percent"
              label="Descuento global %"
              placeholder="0.00"
              type="number"
              min="0"
              max="100"
              step="0.01"
              :readonly="isReadonly"
              :error-messages="errors.global_discount_percent"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.delivery_time"
              label="Tiempo de entrega"
              placeholder="Ej. Inmediato o 3 a 5 días hábiles"
              maxlength="255"
              :readonly="isReadonly"
              :error-messages="errors.delivery_time"
            />
          </VCol>

          <VCol
            cols="12"
            md="5"
          >
            <VTextField
              v-model="form.payment_terms"
              label="Forma de pago"
              placeholder="Ej. Contado contra entrega, Crédito 30 días"
              maxlength="255"
              :readonly="isReadonly"
              :error-messages="errors.payment_terms"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VTextarea
              v-model="form.notes"
              label="Observaciones para el cliente"
              placeholder="Información adicional visible en la cotización"
              rows="3"
              :readonly="isReadonly"
              :error-messages="errors.notes"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VTextarea
              v-model="form.terms"
              label="Términos y condiciones"
              placeholder="Garantía, condiciones de instalación, validez de stock"
              rows="3"
              :readonly="isReadonly"
              :error-messages="errors.terms"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions
        v-if="!isReadonly"
        class="justify-end pa-4 gap-2"
      >
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="emit('cancel')"
        >
          Cancelar
        </VBtn>

        <VBtn
          type="button"
          color="primary"
          variant="outlined"
          :loading="isSubmitting"
          prepend-icon="tabler-device-floppy"
          @click="handleSubmit('save')"
        >
          Guardar borrador
        </VBtn>

        <VBtn
          type="button"
          color="primary"
          :loading="isSubmitting"
          prepend-icon="tabler-send"
          @click="handleSubmit('send')"
        >
          Guardar y Enviar
        </VBtn>
      </VCardActions>
    </VCard>
  </form>
</template>
