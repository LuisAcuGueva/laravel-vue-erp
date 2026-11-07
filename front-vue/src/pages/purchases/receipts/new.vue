<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useApiFormErrors } from '@/composables/useApiFormErrors'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import DownPaymentFields from '@/modules/purchases/components/payables/DownPaymentFields.vue'
import InstallmentPlanFields from '@/modules/purchases/components/payables/InstallmentPlanFields.vue'
import ReceiptItemsTable from '@/modules/purchases/components/receipts/ReceiptItemsTable.vue'
import { purchaseOrdersApi } from '@/modules/purchases/services/purchaseOrdersApi'
import { purchaseReceiptsApi } from '@/modules/purchases/services/purchaseReceiptsApi'
import {
  PROVIDER_DOCUMENT_TYPE_OPTIONS,
  orderStatusColor,
  orderStatusTitle,
} from '@/modules/purchases/utils/status'
import { calculateDocumentTotals, formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'purchase_receipts.create',
    title: 'Nueva recepción de compra',
    icon: 'tabler-truck-loading',
    description: 'Registrar la llegada de mercadería y documentos de compra contra una orden de compra',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { alert } = useConfirm()
const { errors, generalError, setErrors, clearErrors, clearFieldError } = useApiFormErrors()

const today = () => new Date().toISOString().slice(0, 10)

const addDays = (date, days) => {
  const d = new Date(`${date}T00:00:00`)

  d.setDate(d.getDate() + Number(days || 0))

  return d.toISOString().slice(0, 10)
}

const isBusy = ref(false)
const isLoadingOrder = ref(false)
const orderList = ref([])
const warehouses = ref([])
const selectedOrder = ref(null)
const items = ref([])
const company = ref({ default_tax_rate: 18 })
const installments = ref([])
const downPayment = ref({ amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' })

const form = ref({
  purchase_order_id: null,
  warehouse_id: null,
  receipt_date: today(),
  provider_document_type: 'guide',
  provider_document_number: '',
  notes: '',
})

const currencySymbol = computed(() => selectedOrder.value?.currency?.symbol || 'S/')
const currencyDecimals = computed(() => selectedOrder.value?.currency?.decimals ?? 2)
const hasItemsToReceive = computed(() => items.value.some(i => Number(i.quantity) > 0))
const isInvoice = computed(() => form.value.provider_document_type === 'invoice')

// Previsualización del total que facturará la recepción (el backend recalcula el total real al confirmar).
const previewTotal = computed(() => calculateDocumentTotals(
  items.value.filter(i => Number(i.quantity) > 0).map(i => ({
    quantity: i.quantity,
    unitPrice: i.unit_cost_preview,
    taxType: i.tax_type,
  })),
  { taxRate: company.value.default_tax_rate, pricesIncludeTax: false },
).total)

const defaultDueDate = computed(() => addDays(form.value.receipt_date, selectedOrder.value?.credit_days || 0))
const canPayNow = computed(() => can('supplier_payments.create'))
const downPaymentAmount = computed(() => Number(downPayment.value.amount || 0))
const remainderTotal = computed(() => Math.max(0, Number(previewTotal.value) - downPaymentAmount.value))

watch(isInvoice, value => {
  if (!value) {
    installments.value = []
    downPayment.value = { amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' }
  }
})

const loadOrders = async (search = '') => {
  try {
    const [resApproved, resPartial] = await Promise.all([
      purchaseOrdersApi.list({ status: 'approved', per_page: 50, search: search || undefined }),
      purchaseOrdersApi.list({ status: 'partially_received', per_page: 50, search: search || undefined }),
    ])

    const combined = [...(resApproved?.data || []), ...(resPartial?.data || [])]
    const map = new Map()

    combined.forEach(o => map.set(o.id, o))
    orderList.value = Array.from(map.values())
  }
  catch (err) {
    console.error('Error al cargar órdenes de compra', err)
  }
}

const onOrderSelect = async orderId => {
  installments.value = []
  downPayment.value = { amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' }

  if (!orderId) {
    selectedOrder.value = null
    items.value = []

    return
  }

  isLoadingOrder.value = true
  clearErrors()

  try {
    const [orderRes, pendingRes] = await Promise.all([
      purchaseOrdersApi.get(orderId),
      purchaseReceiptsApi.getPendingItems(orderId),
    ])

    const orderData = orderRes?.data || null

    selectedOrder.value = orderData

    if (orderData?.warehouse_id) {
      form.value.warehouse_id = orderData.warehouse_id
    }

    const pendingData = pendingRes?.data || []

    items.value = pendingData.map(p => ({
      purchase_order_item_id: p.purchase_order_item_id,
      line: p.line,
      description: p.description,
      is_service: p.is_service,
      catalog_item: p.catalog_item,
      presentation: p.presentation,
      ordered_quantity: p.ordered_quantity,
      received_quantity: p.received_quantity,
      pending_quantity: p.pending_quantity,
      unit_cost_preview: p.unit_cost_preview,
      tax_type: p.tax_type,
      quantity: Number(p.pending_quantity || 0),
      notes: '',
    }))

    if (items.value.length === 0) {
      notify.warning('Esta orden no tiene ítems con saldo pendiente por recibir.')
    }
  }
  catch (err) {
    notify.error('No se pudo cargar la información de la orden seleccionada.')
    console.error(err)
  }
  finally {
    isLoadingOrder.value = false
  }
}

watch(() => form.value.purchase_order_id, val => {
  onOrderSelect(val)
})

onMounted(async () => {
  try {
    const [warehousesRes, summaryRes] = await Promise.all([
      warehousesApi.options(),
      configurationApi.getSummary(),
      loadOrders(),
    ])

    warehouses.value = warehousesRes?.data || []
    company.value = summaryRes?.data || company.value

    const queryOrderId = route.query.order_id

    if (queryOrderId) {
      form.value.purchase_order_id = queryOrderId
    }
  }
  catch (err) {
    console.error('Error al inicializar formulario de recepción', err)
  }
})

const submitForm = async confirmImmediately => {
  clearErrors()

  if (!form.value.purchase_order_id) {
    notify.error('Debe seleccionar una orden de compra.')

    return
  }

  if (!form.value.receipt_date) {
    notify.error('La fecha de recepción es obligatoria.')

    return
  }

  if (!form.value.provider_document_type) {
    notify.error('El tipo de documento es obligatorio.')

    return
  }

  if (isInvoice.value && !form.value.provider_document_number?.trim()) {
    notify.error('El número de comprobante es obligatorio para generar la factura del proveedor (y su pago al contado o cuotas, si definiste alguno).')

    return
  }

  const linesToSubmit = items.value
    .filter(i => Number(i.quantity) > 0)
    .map(i => ({
      purchase_order_item_id: i.purchase_order_item_id,
      quantity: Number(i.quantity),
      notes: i.notes || null,
    }))

  if (linesToSubmit.length === 0) {
    notify.error('Debe ingresar una cantidad mayor a cero en al menos un ítem.')

    return
  }

  const payload = {
    purchase_order_id: form.value.purchase_order_id,
    warehouse_id: form.value.warehouse_id,
    receipt_date: form.value.receipt_date,
    provider_document_type: form.value.provider_document_type,
    provider_document_number: form.value.provider_document_number || null,
    notes: form.value.notes || null,
    items: linesToSubmit,
  }

  isBusy.value = true

  try {
    const createdRes = await purchaseReceiptsApi.create(payload)
    const receipt = createdRes?.data

    if (confirmImmediately && receipt?.id) {
      const confirmRes = await purchaseReceiptsApi.confirm(receipt.id, {
        installments: isInvoice.value ? installments.value : [],
        down_payment: isInvoice.value && downPaymentAmount.value > 0 ? downPayment.value : null,
      })

      notify.success('Recepción creada y confirmada correctamente')

      const warnings = confirmRes?.meta?.warnings || []

      if (warnings.length)
        notify.warning(warnings.join(' '))
    }
    else {
      notify.success('Recepción registrada correctamente en borrador')
    }

    router.push({ name: 'purchases-receipts-id', params: { id: receipt.id } })
  }
  catch (err) {
    if (err?.data?.errors) {
      setErrors(err)
    }
    else {
      await alert({ message: err?.data?.message || 'No se pudo guardar la recepción.' })
    }
  }
  finally {
    isBusy.value = false
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader>
          <template #actions>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              :disabled="isBusy"
              @click="router.push({ name: 'purchases-receipts' })"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-device-floppy"
              :loading="isBusy"
              :disabled="!form.purchase_order_id || !hasItemsToReceive"
              @click="submitForm(false)"
            >
              Guardar borrador
            </VBtn>
            <VBtn
              v-if="can('purchase_receipts.confirm')"
              color="success"
              prepend-icon="tabler-check"
              :loading="isBusy"
              :disabled="!form.purchase_order_id || !hasItemsToReceive"
              @click="submitForm(true)"
            >
              Guardar y confirmar
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <VAlert
      v-if="generalError"
      type="error"
      variant="tonal"
      class="mb-6"
    >
      {{ generalError }}
    </VAlert>

    <!-- Datos de cabecera -->
    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VAutocomplete
              v-model="form.purchase_order_id"
              :items="orderList"
              item-value="id"
              :item-title="o => `${o.number} — ${o.provider?.business_name || ''} (${formatAmount(o.total, o.currency?.symbol, o.currency?.decimals)})`"
              label="Orden de compra *"
              placeholder="OC-000001"
              :loading="isLoadingOrder"
              :error-messages="errors.purchase_order_id"
              clearable
              @update:model-value="clearFieldError('purchase_order_id')"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              v-model="form.warehouse_id"
              :items="warehouses"
              item-title="name"
              item-value="id"
              label="Almacén de recepción *"
              placeholder="Almacén Principal"
              :error-messages="errors.warehouse_id"
              clearable
              @update:model-value="clearFieldError('warehouse_id')"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.receipt_date"
              label="Fecha de recepción *"
              placeholder="2026-10-20"
              type="date"
              :error-messages="errors.receipt_date"
              @update:model-value="clearFieldError('receipt_date')"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <VSelect
              v-model="form.provider_document_type"
              :items="PROVIDER_DOCUMENT_TYPE_OPTIONS"
              label="Tipo de comprobante *"
              placeholder="Guía de remisión"
              :error-messages="errors.provider_document_type"
              @update:model-value="clearFieldError('provider_document_type')"
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <VTextField
              v-model="form.provider_document_number"
              :label="isInvoice ? 'Número de comprobante *' : 'Número de comprobante'"
              placeholder="T001-000123"
              :error-messages="errors.provider_document_number"
              @update:model-value="clearFieldError('provider_document_number')"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              label="Observaciones"
              placeholder="Ej. Mercadería recibida conforme en bultos sellados"
              rows="2"
              :error-messages="errors.notes"
              @update:model-value="clearFieldError('notes')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <DownPaymentFields
      v-if="isInvoice && hasItemsToReceive && canPayNow"
      v-model="downPayment"
      variant="elevated"
      class="mb-6"
      :total="Number(previewTotal)"
      :currency-id="selectedOrder?.currency?.id"
      :currency-symbol="currencySymbol"
      :currency-decimals="currencyDecimals"
      :today="form.receipt_date"
      :errors="errors"
    />

    <InstallmentPlanFields
      v-if="isInvoice && hasItemsToReceive"
      v-model="installments"
      variant="elevated"
      class="mb-6"
      :total="remainderTotal"
      :issue-date="form.receipt_date"
      :default-due-date="defaultDueDate"
      :currency-symbol="currencySymbol"
      :currency-decimals="currencyDecimals"
      :errors="errors"
    />

    <!-- Resumen de la Orden seleccionada si existe -->
    <VCard
      v-if="selectedOrder"
      class="mb-6"
    >
      <VCardText>
        <div class="d-flex flex-wrap justify-space-between align-center gap-4">
          <div>
            <div class="text-caption text-medium-emphasis">
              Proveedor
            </div>
            <div class="font-weight-medium">
              {{ selectedOrder.provider?.business_name }} ({{ selectedOrder.provider?.document_type }} {{ selectedOrder.provider?.document_number }})
            </div>
          </div>
          <div>
            <div class="text-caption text-medium-emphasis">
              Total de la OC
            </div>
            <div class="font-weight-medium">
              {{ formatAmount(selectedOrder.total, selectedOrder.currency?.symbol, selectedOrder.currency?.decimals) }}
            </div>
          </div>
          <div>
            <div class="text-caption text-medium-emphasis">
              Estado de la OC
            </div>
            <VChip
              size="small"
              class="text-uppercase"
              :color="orderStatusColor(selectedOrder.status)"
            >
              {{ orderStatusTitle(selectedOrder.status) }}
            </VChip>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Tabla de ítems a recibir -->
    <VCard>
      <VCardText>
        <VProgressLinear
          v-if="isLoadingOrder"
          indeterminate
          class="mb-4"
        />

        <div
          v-if="!form.purchase_order_id"
          class="text-center text-medium-emphasis py-8"
        >
          <VIcon
            icon="tabler-arrow-up"
            size="32"
            class="mb-2"
          />
          <div>Seleccione una orden de compra para cargar sus ítems pendientes.</div>
        </div>

        <ReceiptItemsTable
          v-else
          :items="items"
          :currency-symbol="currencySymbol"
          :currency-decimals="currencyDecimals"
        />
      </VCardText>
    </VCard>
  </div>
</template>
