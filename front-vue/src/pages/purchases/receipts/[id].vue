<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { configurationApi } from '@/modules/configuration/services/configurationApi'
import DownPaymentFields from '@/modules/purchases/components/payables/DownPaymentFields.vue'
import InstallmentPlanFields from '@/modules/purchases/components/payables/InstallmentPlanFields.vue'
import ReceiptItemsTable from '@/modules/purchases/components/receipts/ReceiptItemsTable.vue'
import ReceiptKardexTable from '@/modules/purchases/components/receipts/ReceiptKardexTable.vue'
import { purchaseReceiptsApi } from '@/modules/purchases/services/purchaseReceiptsApi'
import {
  providerDocumentTypeTitle,
  receiptStatusColor,
  receiptStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { calculateDocumentTotals, formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'purchase_receipts.view',
    title: 'Detalle de recepción de compra',
    icon: 'tabler-truck-loading',
    description: 'Consulta, confirmación, anulación e ingreso a inventario de la recepción.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { alert } = useConfirm()
const { goBack } = useBackNavigation({ name: 'purchases-receipts' })

const receiptId = route.params.id
const receipt = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const isCancelOpen = ref(false)
const activeTab = ref('items')
const isConfirmDialogOpen = ref(false)
const confirmInstallments = ref([])
const confirmDownPayment = ref({ amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' })
const company = ref({ default_tax_rate: 18 })

const currencySymbol = computed(() => receipt.value?.order?.currency?.symbol || 'S/')
const currencyDecimals = computed(() => receipt.value?.order?.currency?.decimals ?? 2)
const status = computed(() => receipt.value?.status)
const canConfirm = computed(() => status.value === 'draft' && can('purchase_receipts.confirm'))
const canCancel = computed(() => ['draft', 'confirmed'].includes(status.value) && can('purchase_receipts.cancel'))
const isConfirmInvoice = computed(() => receipt.value?.provider_document_type === 'invoice')
const hasDocumentNumber = computed(() => Boolean(receipt.value?.provider_document_number?.trim()))
const canGenerateInvoice = computed(() => isConfirmInvoice.value && hasDocumentNumber.value)
const canPayNow = computed(() => can('supplier_payments.create'))
const confirmDownPaymentAmount = computed(() => Number(confirmDownPayment.value.amount || 0))
const confirmRemainderTotal = computed(() => Math.max(0, Number(confirmPreviewTotal.value) - confirmDownPaymentAmount.value))

const addDays = (date, days) => {
  const d = new Date(`${date}T00:00:00`)

  d.setDate(d.getDate() + Number(days || 0))

  return d.toISOString().slice(0, 10)
}

// Previsualización del total que facturará la recepción (el backend recalcula el total real al confirmar).
const confirmPreviewTotal = computed(() => calculateDocumentTotals(
  (receipt.value?.items || []).map(i => ({
    quantity: i.quantity,
    unitPrice: i.unit_cost,
    taxType: i.tax_type,
  })),
  { taxRate: company.value.default_tax_rate, pricesIncludeTax: false },
).total)

const confirmDefaultDueDate = computed(() => addDays(
  receipt.value?.receipt_date,
  receipt.value?.provider?.credit_days || 0,
))

const load = async () => {
  try {
    const res = await purchaseReceiptsApi.get(receiptId)

    receipt.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar la recepción de compra.')
    router.push({ name: 'purchases-receipts' })
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

const doConfirm = async () => {
  isConfirmDialogOpen.value = false
  isBusy.value = true
  try {
    const res = await purchaseReceiptsApi.confirm(receiptId, {
      installments: canGenerateInvoice.value ? confirmInstallments.value : [],
      down_payment: canGenerateInvoice.value && confirmDownPaymentAmount.value > 0 ? confirmDownPayment.value : null,
    })

    notify.success('Recepción confirmada e inventario actualizado correctamente')

    const warnings = res?.meta?.warnings || []

    if (warnings.length)
      notify.warning(warnings.join(' '))

    await load()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo confirmar la recepción.' })
  }
  finally {
    isBusy.value = false
  }
}

const confirmReceipt = async () => {
  if (isConfirmInvoice.value && !hasDocumentNumber.value) {
    await alert({ message: 'Esta recepción quedó guardada como factura pero sin número de comprobante, así que no se podrá generar la factura del proveedor ni su pago al confirmar. Si corresponde, regístrala manualmente desde "Nueva factura de proveedor" después de confirmar.' })
  }

  if (canGenerateInvoice.value) {
    confirmInstallments.value = []
    confirmDownPayment.value = { amount: '', payment_method_id: null, cash_session_id: null, reference: '', payment_date: '' }
    isConfirmDialogOpen.value = true

    return
  }

  doConfirm()
}

const handleCancel = async reason => {
  isBusy.value = true
  isCancelOpen.value = false
  try {
    await purchaseReceiptsApi.cancel(receiptId, reason)
    notify.success('Recepción anulada correctamente')
    await load()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo anular la recepción.' })
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
        <PageHeader
          :title="receipt ? `Recepción ${receipt.number}` : 'Cargando recepción...'"
          :description="receipt ? `${receipt.provider?.business_name || ''} · ${formatAmount(receipt.total_cost, currencySymbol, currencyDecimals)}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="receipt"
              size="small"
              class="text-uppercase"
              :color="receiptStatusColor(receipt.status)"
            >
              {{ receiptStatusTitle(receipt.status) }}
            </VChip>

            <VBtn
              v-if="canConfirm"
              color="success"
              prepend-icon="tabler-check"
              :loading="isBusy"
              @click="confirmReceipt"
            >
              Confirmar
            </VBtn>

            <VBtn
              v-if="canCancel"
              color="error"
              variant="outlined"
              prepend-icon="tabler-ban"
              :disabled="isBusy"
              @click="isCancelOpen = true"
            >
              Anular
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

    <template v-if="receipt">
      <VAlert
        v-if="status === 'cancelled'"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <strong>Recepción anulada.</strong> Motivo: {{ receipt.cancel_reason || 'Sin motivo especificado' }}
        <span v-if="receipt.canceller"> · Por {{ receipt.canceller.name }} el {{ formatDateDMY(receipt.cancelled_at) }}</span>
      </VAlert>

      <!-- Resumen de datos -->
      <VCard class="mb-6">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Orden de compra
              </div>
              <div class="font-weight-medium">
                <RouterLink
                  v-if="receipt.order"
                  :to="{ name: 'purchases-orders-id', params: { id: receipt.order.id } }"
                  class="text-primary text-decoration-none"
                >
                  {{ receipt.order.number }}
                </RouterLink>
                <span v-else>—</span>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Proveedor
              </div>
              <div class="font-weight-medium">
                <RouterLink
                  v-if="receipt.provider"
                  :to="{ name: 'purchases-providers-id', params: { id: receipt.provider.id } }"
                  class="text-primary text-decoration-none"
                >
                  {{ receipt.provider.business_name }}
                </RouterLink>
                <span v-else>—</span>
              </div>
              <div class="text-caption">
                {{ receipt.provider?.document_type }} {{ receipt.provider?.document_number }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Almacén de ingreso
              </div>
              <div class="font-weight-medium">
                {{ receipt.warehouse ? `${receipt.warehouse.code} - ${receipt.warehouse.name}` : '—' }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Fecha de recepción
              </div>
              <div class="font-weight-medium">
                {{ formatDateDMY(receipt.receipt_date) }}
              </div>
            </VCol>

            <VCol
              v-if="!receipt.supplier_invoices?.length"
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Documento del proveedor
              </div>
              <div class="font-weight-medium">
                {{ providerDocumentTypeTitle(receipt.provider_document_type) }}
                <span
                  v-if="receipt.provider_document_number"
                  class="font-monospace ms-1"
                >
                  {{ receipt.provider_document_number }}
                </span>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Total costo (moneda base)
              </div>
              <div class="font-weight-medium">
                {{ formatAmount(receipt.total_cost, 'S/') }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Registrada por
              </div>
              <div class="font-weight-medium">
                {{ receipt.creator?.name || '—' }}
              </div>
            </VCol>

            <VCol
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Confirmada por
              </div>
              <div class="font-weight-medium">
                {{ receipt.confirmer?.name || '—' }}
              </div>
              <div
                v-if="receipt.confirmed_at"
                class="text-caption"
              >
                {{ formatDateDMY(receipt.confirmed_at) }}
              </div>
            </VCol>

            <VCol
              v-if="receipt.supplier_invoices?.length"
              cols="12"
              md="3"
            >
              <div class="text-caption text-medium-emphasis">
                Factura de proveedor
              </div>
              <div class="d-flex flex-wrap gap-4 mt-1">
                <RouterLink
                  v-for="invoice in receipt.supplier_invoices"
                  :key="invoice.id"
                  :to="{ name: 'purchases-supplier-invoices-id', params: { id: invoice.id } }"
                  class="d-flex flex-column text-decoration-none"
                >
                  <span class="font-weight-medium font-monospace text-primary">{{ invoice.document_label }}</span>
                  <span class="text-caption text-medium-emphasis">{{ invoice.number }}</span>
                </RouterLink>
              </div>
            </VCol>

            <VCol
              v-if="receipt.notes"
              cols="12"
            >
              <div class="text-caption text-medium-emphasis">
                Observaciones
              </div>
              <div>{{ receipt.notes }}</div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Pestañas para Líneas y Movimientos de Kardex -->
      <VCard>
        <VTabs v-model="activeTab">
          <VTab value="items">
            Líneas recibidas ({{ receipt.items?.length || 0 }})
          </VTab>
          <VTab
            v-if="receipt.kardex_movements?.length > 0"
            value="kardex"
          >
            Movimientos de inventario ({{ receipt.kardex_movements.length }})
          </VTab>
        </VTabs>

        <VDivider />

        <VCardText>
          <VWindow v-model="activeTab">
            <VWindowItem value="items">
              <ReceiptItemsTable
                :items="receipt.items || []"
                readonly
                :currency-symbol="currencySymbol"
                :currency-decimals="currencyDecimals"
              />
            </VWindowItem>

            <VWindowItem
              v-if="receipt.kardex_movements?.length > 0"
              value="kardex"
            >
              <ReceiptKardexTable :movements="receipt.kardex_movements" />
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>
    </template>

    <CancelDocumentDialog
      v-model="isCancelOpen"
      :title="`Anular recepción ${receipt?.number || ''}`"
      :document-number="receipt?.number || ''"
      :description="receipt?.status === 'confirmed' ? 'Esta acción revertirá los movimientos en inventario y actualizará el saldo recibido de la orden de compra.' : 'Esta acción anulará el borrador de recepción.'"
      reason-label="Motivo de anulación *"
      reason-placeholder="Ej. Error en cantidad o mercadería devuelta al proveedor"
      confirm-label="Confirmar anulación"
      color="error"
      required-message="El motivo de anulación es obligatorio."
      :loading="isBusy"
      @confirm="handleCancel"
    />

    <VDialog
      v-model="isConfirmDialogOpen"
      max-width="700"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pt-4 px-4">
          <VIcon
            icon="tabler-calendar-dollar"
            color="primary"
          />
          <span>Cronograma de pago de la factura</span>
        </VCardTitle>

        <VCardText class="pt-2">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Esta recepción trae el documento de factura del proveedor; se generará (o se vinculará a) su factura al confirmar. Define el cronograma de pago o deja la cuota única por defecto.
          </p>

          <DownPaymentFields
            v-if="canPayNow"
            v-model="confirmDownPayment"
            class="mb-6"
            :total="Number(confirmPreviewTotal)"
            :currency-id="receipt?.order?.currency?.id"
            :currency-symbol="currencySymbol"
            :currency-decimals="currencyDecimals"
            :today="receipt?.receipt_date"
          />

          <InstallmentPlanFields
            v-model="confirmInstallments"
            :total="confirmRemainderTotal"
            :issue-date="receipt?.receipt_date"
            :default-due-date="confirmDefaultDueDate"
            :currency-symbol="currencySymbol"
            :currency-decimals="currencyDecimals"
          />
        </VCardText>

        <VCardActions class="justify-end pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isBusy"
            @click="isConfirmDialogOpen = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="success"
            :loading="isBusy"
            @click="doConfirm"
          >
            Confirmar recepción
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
