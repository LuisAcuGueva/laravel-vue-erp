<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import OrderReceiptsCard from '@/modules/purchases/components/orders/OrderReceiptsCard.vue'
import PurchaseOrderForm from '@/modules/purchases/components/orders/PurchaseOrderForm.vue'
import PurchaseOrderItemsTable from '@/modules/purchases/components/orders/PurchaseOrderItemsTable.vue'
import DownPaymentFields from '@/modules/purchases/components/payables/DownPaymentFields.vue'
import InstallmentPlanFields from '@/modules/purchases/components/payables/InstallmentPlanFields.vue'
import { purchaseOrdersApi } from '@/modules/purchases/services/purchaseOrdersApi'
import { purchaseReceiptsApi } from '@/modules/purchases/services/purchaseReceiptsApi'
import {
  PROVIDER_DOCUMENT_TYPE_OPTIONS,
  orderStatusColor,
  orderStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { calculateDocumentTotals, formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'purchase_orders.view',
    title: 'Detalle de la orden de compra',
    icon: 'tabler-receipt-2',
    description: 'Detalle, aprobación, avance de recepción y PDF de la orden de compra.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const { alert } = useConfirm()
const notify = useNotify()
const authStore = useAuthStore()
const { goBack } = useBackNavigation({ name: 'purchases-orders' })

const orderId = route.params.id
const order = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const formRef = ref(null)

const dialog = ref({ open: false, mode: 'reject' })

const status = computed(() => order.value?.status)
const isCreator = computed(() => order.value && authStore.user?.id === order.value.created_by)
const isEditable = computed(() => ['draft', 'rejected'].includes(status.value) && can('purchase_orders.update'))
const canSubmit = computed(() => status.value === 'draft' && can('purchase_orders.create'))
const canResolve = computed(() => status.value === 'pending_approval' && can('purchase_orders.approve') && !isCreator.value)
const canClose = computed(() => ['approved', 'partially_received'].includes(status.value) && can('purchase_orders.cancel'))
const canCancel = computed(() => ['draft', 'pending_approval', 'approved'].includes(status.value) && can('purchase_orders.cancel'))
const canReceive = computed(() => ['approved', 'partially_received'].includes(status.value) && can('purchase_receipts.create'))
const canReceiveAll = computed(() => ['approved', 'partially_received'].includes(status.value) && can('purchase_receipts.create') && can('purchase_receipts.confirm'))

const today = () => new Date().toISOString().slice(0, 10)

const addDays = (date, days) => {
  const d = new Date(`${date}T00:00:00`)

  d.setDate(d.getDate() + Number(days || 0))

  return d.toISOString().slice(0, 10)
}

const receiveAllDialog = ref({ open: false })
const pendingItems = ref([])
const isLoadingPendingItems = ref(false)
const company = ref({ default_tax_rate: 18 })
const receiveAllInstallments = ref([])
const receiveAllDownPayment = ref({ amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' })

const receiveAllForm = ref({
  receipt_date: today(),
  provider_document_type: 'guide',
  provider_document_number: '',
})

const isReceiveAllInvoice = computed(() => receiveAllForm.value.provider_document_type === 'invoice')
const canPayNow = computed(() => can('supplier_payments.create'))

// Previsualización del total que facturará la recepción total (el backend recalcula el total real al confirmar).
const receiveAllPreviewTotal = computed(() => calculateDocumentTotals(
  pendingItems.value.map(i => ({
    quantity: i.pending_quantity,
    unitPrice: i.unit_cost_preview,
    taxType: i.tax_type,
  })),
  { taxRate: company.value.default_tax_rate, pricesIncludeTax: false },
).total)

const receiveAllDownPaymentAmount = computed(() => Number(receiveAllDownPayment.value.amount || 0))
const receiveAllRemainderTotal = computed(() => Math.max(0, Number(receiveAllPreviewTotal.value) - receiveAllDownPaymentAmount.value))

const receiveAllDefaultDueDate = computed(() => addDays(receiveAllForm.value.receipt_date, order.value?.credit_days || 0))

const openReceiveAllDialog = async () => {
  receiveAllForm.value = {
    receipt_date: today(),
    provider_document_type: 'guide',
    provider_document_number: '',
  }
  receiveAllInstallments.value = []
  receiveAllDownPayment.value = { amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' }
  receiveAllDialog.value.open = true

  isLoadingPendingItems.value = true
  try {
    const res = await purchaseReceiptsApi.getPendingItems(orderId)

    pendingItems.value = res?.data || []
  }
  catch {
    pendingItems.value = []
  }
  finally {
    isLoadingPendingItems.value = false
  }
}

const confirmReceiveAll = async () => {
  if (!receiveAllForm.value.receipt_date) {
    notify.error('La fecha de recepción es obligatoria.')

    return
  }

  if (isReceiveAllInvoice.value && !receiveAllForm.value.provider_document_number?.trim()) {
    notify.error('El número de comprobante es obligatorio para generar la factura del proveedor (y su pago al contado o cuotas, si definiste alguno).')

    return
  }

  receiveAllDialog.value.open = false
  await run(
    () => purchaseReceiptsApi.receiveAll(orderId, {
      warehouse_id: order.value?.warehouse_id,
      receipt_date: receiveAllForm.value.receipt_date,
      provider_document_type: receiveAllForm.value.provider_document_type,
      provider_document_number: receiveAllForm.value.provider_document_number || null,
      installments: isReceiveAllInvoice.value ? receiveAllInstallments.value : [],
      down_payment: isReceiveAllInvoice.value && receiveAllDownPaymentAmount.value > 0 ? receiveAllDownPayment.value : null,
    }),
    'Recepción total registrada y confirmada correctamente',
  )
}

const load = async () => {
  try {
    const res = await purchaseOrdersApi.get(orderId)

    order.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar la orden de compra')
    router.push({ name: 'purchases-orders' })
  }
  finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await load()
  try {
    const summaryRes = await configurationApi.getSummary()

    company.value = summaryRes?.data || company.value
  }
  catch {
    // La previsualización de cuotas usa el valor por defecto si no se puede cargar.
  }
})

const run = async (action, successMessage) => {
  isBusy.value = true
  try {
    const result = await action()

    notify.success(typeof successMessage === 'function' ? successMessage(result) : successMessage)

    const warnings = result?.meta?.warnings || []

    if (warnings.length)
      notify.warning(warnings.join(' '))

    await load()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo completar la acción.' })
  }
  finally {
    isBusy.value = false
  }
}

const handleSave = async ({ action, payload }) => {
  isBusy.value = true
  formRef.value?.clearErrors()
  try {
    await purchaseOrdersApi.update(orderId, payload)
    if (action === 'send') {
      const sent = await purchaseOrdersApi.submit(orderId)

      notify.success(sent?.data?.status === 'approved' ? 'Orden enviada y aprobada automáticamente' : 'Orden enviada a aprobación')
    }
    else {
      notify.success('Orden de compra actualizada correctamente')
    }
    await load()
  }
  catch (err) {
    formRef.value?.setErrors(err)
  }
  finally {
    isBusy.value = false
  }
}

const submitOrder = () => run(
  () => purchaseOrdersApi.submit(orderId),
  result => (result?.data?.status === 'approved' ? 'Orden enviada y aprobada automáticamente' : 'Orden enviada a aprobación'),
)

const approve = () => run(() => purchaseOrdersApi.approve(orderId), 'Orden aprobada correctamente')

const openDialog = mode => {
  dialog.value = { open: true, mode }
}

const dialogTexts = computed(() => ({
  reject: {
    title: 'Rechazar orden de compra',
    description: 'La orden volverá al comprador, que podrá corregirla y enviarla de nuevo. Se liberan las cantidades de los requerimientos.',
    label: 'Motivo del rechazo *',
    confirm: 'Confirmar rechazo',
    color: 'error',
  },
  cancel: {
    title: 'Anular orden de compra',
    description: 'La orden quedará anulada y se liberan las cantidades de los requerimientos. No es posible si ya tiene recepciones.',
    label: 'Motivo de anulación *',
    confirm: 'Confirmar anulación',
    color: 'error',
  },
  close: {
    title: 'Cerrar orden de compra',
    description: 'La orden se cierra y no se recibirá más mercadería. Los saldos pendientes quedan sin atender.',
    label: 'Motivo del cierre',
    confirm: 'Cerrar orden',
    color: 'warning',
  },
}[dialog.value.mode]))

const confirmDialog = async reason => {
  const mode = dialog.value.mode

  dialog.value.open = false

  const actions = {
    reject: [() => purchaseOrdersApi.reject(orderId, reason), 'Orden rechazada correctamente'],
    cancel: [() => purchaseOrdersApi.cancel(orderId, reason), 'Orden anulada correctamente'],
    close: [() => purchaseOrdersApi.close(orderId, reason), 'Orden cerrada correctamente'],
  }

  await run(...actions[mode])
}

const downloadPdf = async () => {
  try {
    await purchaseOrdersApi.downloadPdf(orderId, order.value.number)
  }
  catch {
    notify.error('No se pudo descargar el PDF.')
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader
          :title="order ? `Orden de compra ${order.number}` : 'Cargando orden...'"
          :description="order ? `${order.provider?.business_name || ''} · ${formatAmount(order.total, order.currency?.symbol, order.currency?.decimals)}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="order"
              size="small"
              class="text-uppercase"
              :color="orderStatusColor(order.status)"
            >
              {{ orderStatusTitle(order.status) }}
            </VChip>
            <VBtn
              v-if="canSubmit && !isEditable"
              color="success"
              prepend-icon="tabler-send"
              :loading="isBusy"
              @click="submitOrder"
            >
              Enviar
            </VBtn>
            <VBtn
              v-if="canResolve"
              color="success"
              prepend-icon="tabler-check"
              :loading="isBusy"
              @click="approve"
            >
              Aprobar
            </VBtn>
            <VBtn
              v-if="canResolve"
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              :disabled="isBusy"
              @click="openDialog('reject')"
            >
              Rechazar
            </VBtn>
            <VBtn
              v-if="canReceive"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-truck-loading"
              @click="router.push({ name: 'purchases-receipts-new', query: { order_id: orderId } })"
            >
              Recibir
            </VBtn>
            <VBtn
              v-if="canReceiveAll"
              color="primary"
              prepend-icon="tabler-checks"
              :disabled="isBusy"
              @click="openReceiveAllDialog"
            >
              Recibir todo
            </VBtn>
            <VBtn
              v-if="canClose"
              color="warning"
              variant="tonal"
              prepend-icon="tabler-lock"
              :disabled="isBusy"
              @click="openDialog('close')"
            >
              Cerrar
            </VBtn>
            <VBtn
              v-if="canCancel"
              color="error"
              variant="outlined"
              prepend-icon="tabler-ban"
              :disabled="isBusy"
              @click="openDialog('cancel')"
            >
              Anular
            </VBtn>
            <VBtn
              v-if="order"
              variant="tonal"
              prepend-icon="tabler-file-type-pdf"
              @click="downloadPdf"
            >
              Descargar PDF
            </VBtn>
            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-arrow-left"
              @click="goBack"
            >
              Volver
            </VBtn>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <VProgressLinear
      v-if="isLoading"
      indeterminate
    />

    <template v-if="order">
      <VAlert
        v-if="status === 'rejected'"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <strong>Rechazada.</strong> {{ order.rejection_reason }} Edita la orden para corregirla y volver a enviarla.
      </VAlert>
      <VAlert
        v-if="status === 'pending_approval'"
        type="warning"
        variant="tonal"
        class="mb-6"
      >
        Esta orden supera el umbral de aprobación y espera la autorización de un aprobador distinto a quien la creó.
      </VAlert>
      <VAlert
        v-if="status === 'cancelled'"
        type="warning"
        variant="tonal"
        class="mb-6"
      >
        <strong>Anulada.</strong> {{ order.cancel_reason }}
      </VAlert>

      <VCard v-if="isEditable">
        <VCardText>
          <PurchaseOrderForm
            ref="formRef"
            :order="order"
            :is-submitting="isBusy"
            @submit="handleSave"
            @cancel="router.push({ name: 'purchases-orders' })"
          />
        </VCardText>
      </VCard>

      <template v-else>
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Proveedor
                </div>
                <div class="font-weight-medium">
                  <RouterLink
                    v-if="order.provider"
                    :to="{ name: 'purchases-providers-id', params: { id: order.provider.id } }"
                    class="text-primary text-decoration-none"
                  >
                    {{ order.provider.business_name }}
                  </RouterLink>
                  <span v-else>—</span>
                </div>
                <div class="text-caption">
                  {{ order.provider?.document_type }} {{ order.provider?.document_number }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Emisión / entrega esperada
                </div>
                <div class="font-weight-medium">
                  {{ formatDateDMY(order.order_date) }} → {{ formatDateDMY(order.expected_date) }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Condición de pago
                </div>
                <div class="font-weight-medium">
                  {{ order.payment_condition === 'credit' ? `Crédito ${order.credit_days} días` : 'Contado' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Moneda
                </div>
                <div class="font-weight-medium">
                  {{ order.currency?.code }}
                  <span v-if="Number(order.exchange_rate) !== 1">· T.C. {{ Number(order.exchange_rate) }}</span>
                </div>
                <div
                  v-if="Number(order.exchange_rate) !== 1"
                  class="text-caption"
                >
                  Equivale a {{ formatAmount(order.total_base_currency, 'S/') }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Almacén de entrega
                </div>
                <div class="font-weight-medium">
                  {{ order.warehouse?.name }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Creada por
                </div>
                <div class="font-weight-medium">
                  {{ order.creator?.name || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Aprobada por
                </div>
                <div class="font-weight-medium">
                  {{ order.approver?.name || (order.approved_at ? 'Automática (bajo el umbral)' : '—') }}
                </div>
              </VCol>
              <VCol
                v-if="order.notes"
                cols="12"
                md="6"
              >
                <div class="text-caption text-medium-emphasis">
                  Observaciones
                </div>
                <div>{{ order.notes }}</div>
              </VCol>
              <VCol
                v-if="order.terms"
                cols="12"
                md="6"
              >
                <div class="text-caption text-medium-emphasis">
                  Términos y condiciones
                </div>
                <div>{{ order.terms }}</div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard>
          <VCardText>
            <PurchaseOrderItemsTable :order="order" />
          </VCardText>
        </VCard>

        <!-- Recepciones asociadas -->
        <OrderReceiptsCard
          v-if="['approved', 'partially_received', 'received', 'closed', 'cancelled'].includes(status)"
          :order="order"
          :can-receive="canReceive"
        />
      </template>
    </template>

    <CancelDocumentDialog
      v-model="dialog.open"
      :title="dialogTexts.title"
      :document-number="order?.number || ''"
      :description="dialogTexts.description"
      :reason-label="dialogTexts.label"
      reason-placeholder="Ej. El proveedor no puede cumplir el plazo acordado"
      :confirm-label="dialogTexts.confirm"
      :color="dialogTexts.color"
      required-message="El motivo es obligatorio."
      :reason-optional="dialog.mode === 'close'"
      :loading="isBusy"
      @confirm="confirmDialog"
    />

    <VDialog
      v-model="receiveAllDialog.open"
      max-width="700"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pt-4 px-4">
          <VIcon
            icon="tabler-checks"
            color="primary"
          />
          <span>Recibir todo el saldo pendiente</span>
        </VCardTitle>

        <VCardText class="pt-2">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Se generará y confirmará automáticamente una recepción con todas las líneas pendientes de la orden de compra <strong>{{ order?.number }}</strong>.
          </p>

          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="receiveAllForm.receipt_date"
                label="Fecha de recepción *"
                placeholder="2026-10-20"
                type="date"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="receiveAllForm.provider_document_type"
                :items="PROVIDER_DOCUMENT_TYPE_OPTIONS"
                label="Tipo de comprobante *"
                placeholder="Guía de remisión"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="receiveAllForm.provider_document_number"
                :label="isReceiveAllInvoice ? 'Número de comprobante *' : 'Número de comprobante'"
                placeholder="T001-000123"
              />
            </VCol>
          </VRow>

          <VProgressLinear
            v-if="isReceiveAllInvoice && isLoadingPendingItems"
            indeterminate
            class="mb-4"
          />

          <template v-else-if="isReceiveAllInvoice && pendingItems.length">
            <DownPaymentFields
              v-if="canPayNow"
              v-model="receiveAllDownPayment"
              class="mt-4 mb-6"
              :total="Number(receiveAllPreviewTotal)"
              :currency-id="order?.currency?.id"
              :currency-symbol="order?.currency?.symbol || 'S/'"
              :currency-decimals="order?.currency?.decimals ?? 2"
              :today="receiveAllForm.receipt_date"
            />

            <InstallmentPlanFields
              v-model="receiveAllInstallments"
              class="mt-4"
              :total="receiveAllRemainderTotal"
              :issue-date="receiveAllForm.receipt_date"
              :default-due-date="receiveAllDefaultDueDate"
              :currency-symbol="order?.currency?.symbol || 'S/'"
              :currency-decimals="order?.currency?.decimals ?? 2"
            />
          </template>
        </VCardText>

        <VCardActions class="justify-end pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isBusy"
            @click="receiveAllDialog.open = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="isBusy"
            @click="confirmReceiveAll"
          >
            Confirmar recepción
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
