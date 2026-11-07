<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import DocumentLinesTable from '@/components/common/DocumentLinesTable.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import InvoiceInstallmentsTable from '@/modules/purchases/components/invoices/InvoiceInstallmentsTable.vue'
import InvoicePaymentsTable from '@/modules/purchases/components/invoices/InvoicePaymentsTable.vue'
import { supplierInvoicesApi } from '@/modules/purchases/services/supplierInvoicesApi'
import {
  supplierDocumentTypeTitle,
  supplierInvoiceKindTitle,
  supplierInvoiceStatusColor,
  supplierInvoiceStatusTitle,
} from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'
import { downloadFile } from '@/utils/download'

definePage({
  meta: {
    action: 'supplier_invoices.view',
    title: 'Detalle de factura de proveedor',
    icon: 'tabler-receipt-tax',
    description: 'Consulta de la factura, su cronograma de cuotas, pagos aplicados y recepciones vinculadas.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { alert } = useConfirm()
const { goBack } = useBackNavigation({ name: 'purchases-supplier-invoices' })

const invoiceId = route.params.id
const invoice = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const isCancelOpen = ref(false)
const activeTab = ref('lines')

// Advertencias devueltas al registrar (ej. diferencia con lo recibido).
const warnings = ref(window.history.state?.warnings || [])

const symbol = computed(() => invoice.value?.currency?.symbol || 'S/')
const decimals = computed(() => invoice.value?.currency?.decimals ?? 2)
const counterparty = computed(() => invoice.value?.provider?.business_name || invoice.value?.counterparty_name || '')
const activePayments = computed(() => (invoice.value?.payments || []).filter(p => p.payment?.status === 'completed'))
const canCancel = computed(() => invoice.value && invoice.value.status !== 'cancelled' && can('supplier_invoices.cancel'))

const lines = computed(() => (invoice.value?.items || []).map(item => ({
  key: item.id,
  item_presentation_id: item.item_presentation_id,
  initialItem: item.catalog_item
    ? { code: item.catalog_item.code, name: item.catalog_item.name, presentation_name: item.presentation?.name }
    : null,
  description: item.description,
  quantity: item.quantity,
  unit_price: item.unit_price,
  discount_percent: item.discount_percent,
  tax_type: item.tax_type,
})))

const load = async () => {
  try {
    const res = await supplierInvoicesApi.get(invoiceId)

    invoice.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar la factura de proveedor.')
    router.push({ name: 'purchases-supplier-invoices' })
  }
  finally {
    isLoading.value = false
  }
}

onMounted(load)

const handleCancel = async reason => {
  isBusy.value = true
  isCancelOpen.value = false
  try {
    await supplierInvoicesApi.cancel(invoiceId, reason)
    notify.success('Factura de proveedor anulada correctamente')
    await load()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo anular la factura.' })
  }
  finally {
    isBusy.value = false
  }
}

const downloadAttachment = async () => {
  try {
    await downloadFile(supplierInvoicesApi.attachmentUrl(invoiceId), {}, `adjunto-${invoice.value?.number || 'factura'}`)
  }
  catch {
    notify.error('No se pudo descargar el adjunto.')
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader
          :title="invoice ? `${supplierDocumentTypeTitle(invoice.document_type)} ${invoice.document_label}` : 'Cargando factura...'"
          :description="invoice ? `${invoice.number} · ${counterparty} · ${formatAmount(invoice.total, symbol, decimals)}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="invoice"
              size="small"
              class="text-uppercase"
              :color="invoice.is_overdue ? 'error' : supplierInvoiceStatusColor(invoice.status)"
            >
              {{ invoice.is_overdue ? 'Vencida' : supplierInvoiceStatusTitle(invoice.status) }}
            </VChip>

            <VBtn
              v-if="invoice?.attachment_url"
              variant="tonal"
              prepend-icon="tabler-paperclip"
              @click="downloadAttachment"
            >
              Adjunto
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

    <template v-else-if="invoice">
      <VAlert
        v-for="(warning, i) in warnings"
        :key="i"
        type="warning"
        variant="tonal"
        closable
        class="mb-4"
      >
        {{ warning }}
      </VAlert>

      <VAlert
        v-if="invoice.status === 'cancelled'"
        type="error"
        variant="tonal"
        class="mb-4"
      >
        Anulada el {{ formatDateDMY(invoice.cancelled_at) }}: {{ invoice.cancel_reason }}
      </VAlert>

      <VRow class="mb-2">
        <VCol
          cols="12"
          md="8"
        >
          <VCard>
            <VCardText>
              <VRow>
                <VCol
                  cols="6"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Tipo
                  </div>
                  <div>{{ supplierInvoiceKindTitle(invoice.kind) }}</div>
                </VCol>
                <VCol
                  cols="6"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Proveedor / beneficiario
                  </div>
                  <RouterLink
                    v-if="invoice.provider"
                    :to="{ name: 'purchases-providers-id', params: { id: invoice.provider.id } }"
                    class="font-weight-medium text-primary text-decoration-none"
                  >
                    {{ counterparty }}
                  </RouterLink>
                  <div
                    v-else
                    class="font-weight-medium"
                  >
                    {{ counterparty }}
                  </div>
                  <div
                    v-if="invoice.provider"
                    class="text-caption"
                  >
                    {{ invoice.provider.document_type }} {{ invoice.provider.document_number }}
                  </div>
                </VCol>
                <VCol
                  cols="6"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Emisión / vencimiento
                  </div>
                  <div>{{ formatDateDMY(invoice.issue_date) }} · {{ formatDateDMY(invoice.due_date) }}</div>
                </VCol>
                <VCol
                  v-if="invoice.order"
                  cols="6"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Orden de compra
                  </div>
                  <RouterLink
                    :to="{ name: 'purchases-orders-id', params: { id: invoice.order.id } }"
                    class="text-primary text-decoration-none font-weight-medium"
                  >
                    {{ invoice.order.number }}
                  </RouterLink>
                </VCol>
                <VCol
                  v-if="invoice.concept"
                  cols="6"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Concepto
                  </div>
                  <div>{{ invoice.concept.name }}</div>
                </VCol>
                <VCol
                  v-if="invoice.receipts?.length"
                  cols="12"
                  md="4"
                >
                  <div class="text-caption text-medium-emphasis">
                    Recepciones
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <RouterLink
                      v-for="receipt in invoice.receipts"
                      :key="receipt.id"
                      :to="{ name: 'purchases-receipts-id', params: { id: receipt.id } }"
                      class="text-primary text-decoration-none font-weight-medium"
                    >
                      {{ receipt.number }}
                    </RouterLink>
                  </div>
                </VCol>
                <VCol
                  v-if="invoice.notes"
                  cols="12"
                >
                  <div class="text-caption text-medium-emphasis">
                    Observaciones
                  </div>
                  <div>{{ invoice.notes }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="4"
        >
          <VCard>
            <VCardText class="d-flex flex-column gap-2">
              <div class="d-flex justify-space-between">
                <span class="text-medium-emphasis">Total</span>
                <span class="font-weight-medium">{{ formatAmount(invoice.total, symbol, decimals) }}</span>
              </div>
              <div class="d-flex justify-space-between">
                <span class="text-medium-emphasis">Pagado</span>
                <span class="text-success">{{ formatAmount(invoice.paid_amount, symbol, decimals) }}</span>
              </div>
              <VDivider />
              <div class="d-flex justify-space-between text-h6">
                <span>Saldo</span>
                <span :class="{ 'text-error': invoice.is_overdue }">{{ formatAmount(invoice.balance, symbol, decimals) }}</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VCard>
        <VTabs v-model="activeTab">
          <VTab value="lines">
            <VIcon
              icon="tabler-list-details"
              class="me-2"
            />
            Detalle
          </VTab>
          <VTab value="installments">
            <VIcon
              icon="tabler-calendar-dollar"
              class="me-2"
            />
            Cuotas ({{ invoice.installments?.length || 0 }})
          </VTab>
          <VTab value="payments">
            <VIcon
              icon="tabler-cash"
              class="me-2"
            />
            Pagos ({{ activePayments.length }})
          </VTab>
        </VTabs>

        <VDivider />

        <VCardText>
          <VWindow v-model="activeTab">
            <VWindowItem value="lines">
              <DocumentLinesTable
                :model-value="lines"
                readonly
                :prices-include-tax="invoice.prices_include_tax"
                :tax-rate="invoice.tax_rate"
                :currency-symbol="symbol"
                :currency-decimals="decimals"
              />
            </VWindowItem>
            <VWindowItem value="installments">
              <InvoiceInstallmentsTable
                :installments="invoice.installments || []"
                :currency-symbol="symbol"
                :currency-decimals="decimals"
              />
            </VWindowItem>
            <VWindowItem value="payments">
              <InvoicePaymentsTable
                :payments="invoice.payments || []"
                :installments="invoice.installments || []"
                :currency-symbol="symbol"
                :currency-decimals="decimals"
              />
            </VWindowItem>
          </VWindow>
        </VCardText>
      </VCard>
    </template>

    <CancelDocumentDialog
      v-model="isCancelOpen"
      :title="`Anular factura ${invoice?.number || ''}`"
      :document-number="invoice?.number || ''"
      description="La factura quedará anulada y sus recepciones podrán volver a facturarse o anularse. Si tiene pagos vigentes, anúlelos primero."
      reason-label="Motivo de anulación *"
      reason-placeholder="Ej. Documento registrado por error o duplicado"
      confirm-label="Confirmar anulación"
      color="error"
      required-message="El motivo de anulación es obligatorio."
      :loading="isBusy"
      @confirm="handleCancel"
    />
  </div>
</template>
