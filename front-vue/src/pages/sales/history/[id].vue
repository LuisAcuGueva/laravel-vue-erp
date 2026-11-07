<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import SaleItemsTable from '@/modules/sales/components/detail/SaleItemsTable.vue'
import SalePaymentsTable from '@/modules/sales/components/detail/SalePaymentsTable.vue'
import SaleInstallmentsTable from '@/modules/sales/components/detail/SaleInstallmentsTable.vue'
import RescheduleDialog from '@/modules/sales/components/receivables/RescheduleDialog.vue'
import { salesApi } from '@/modules/sales/services/salesApi'
import { useSalesStore } from '@/modules/sales/stores/salesStore'
import { formatDateDMY, formatDateTime12h } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'sales.view',
    title: 'Detalle de Venta',
    icon: 'tabler-file-text',
    description: 'Consulta detallada de venta, comprobante fiscal, movimientos de almacén y cobros.',
  },
})

const route = useRoute()
const router = useRouter()
const store = useSalesStore()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation('/sales/history')

const saleId = computed(() => route.params.id)
const sale = computed(() => store.currentSale)
const isLoading = ref(true)

const isCancelDialogOpen = ref(false)
const isCancelling = ref(false)

const isPdfDialogOpen = ref(false)
const isDownloadingPdf = ref(false)
const isRescheduleDialogOpen = ref(false)
const isRetryingEInvoice = ref(false)

const loadSale = async () => {
  isLoading.value = true
  try {
    await store.fetchSale(saleId.value)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar la venta')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadSale()
})

const documentTypeBadge = type => {
  switch (type) {
  case 'invoice':
    return { text: 'Factura Electrónica', color: 'primary' }
  case 'receipt':
    return { text: 'Boleta de Venta Electrónica', color: 'info' }
  case 'sale_note':
    return { text: 'Nota de Venta', color: 'secondary' }
  default:
    return { text: type, color: 'default' }
  }
}

const statusBadge = status => {
  switch (status) {
  case 'completed':
    return { text: 'Completada', color: 'success' }
  case 'cancelled':
    return { text: 'Anulada', color: 'error' }
  default:
    return { text: status, color: 'default' }
  }
}

const einvoiceBadge = status => {
  switch (status) {
  case 'accepted':
    return { text: 'Aceptada por SUNAT', color: 'success' }
  case 'rejected':
    return { text: 'Rechazada por SUNAT', color: 'error' }
  case 'sent':
    return { text: 'Enviada / En proceso', color: 'info' }
  case 'pending':
    return { text: 'Pendiente de envío', color: 'warning' }
  case 'not_applicable':
    return { text: 'No aplica', color: 'secondary' }
  default:
    return { text: status || '—', color: 'default' }
  }
}

const handleConfirmCancel = async reason => {
  if (!sale.value) return
  isCancelling.value = true
  try {
    await salesApi.cancel(sale.value.id, { cancellation_reason: reason })
    notify.success(`Venta ${sale.value.full_number} anulada correctamente`)
    isCancelDialogOpen.value = false
    await loadSale()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al anular la venta')
  }
  finally {
    isCancelling.value = false
  }
}

const handleRetryEInvoice = async () => {
  if (!sale.value) return
  isRetryingEInvoice.value = true
  try {
    await salesApi.retryEInvoice(sale.value.id)
    notify.success(`Envío electrónico encolado para ${sale.value.full_number}`)
    await loadSale()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al reintentar envío a SUNAT')
  }
  finally {
    isRetryingEInvoice.value = false
  }
}

const downloadPdfFormat = async format => {
  if (!sale.value) return
  isDownloadingPdf.value = true
  try {
    await salesApi.downloadPdf(
      sale.value.id,
      format,
      `${sale.value.full_number}-${format}.pdf`,
    )
    isPdfDialogOpen.value = false
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al descargar el comprobante en PDF')
  }
  finally {
    isDownloadingPdf.value = false
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-6">
    <VCard>
      <VCardText class="py-4">
        <PageHeader>
          <template #actions>
            <div class="d-flex flex-wrap align-center gap-2">
              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-arrow-left"
                @click="goBack"
              >
                Volver
              </VBtn>

              <VBtn
                variant="tonal"
                color="primary"
                prepend-icon="tabler-printer"
                @click="isPdfDialogOpen = true"
              >
                Imprimir / PDF
              </VBtn>

              <VBtn
                v-if="sale && ['rejected', 'pending'].includes(sale.einvoice_status) && sale.document_type !== 'sale_note' && can('document_series.manage')"
                variant="tonal"
                color="info"
                prepend-icon="tabler-refresh"
                :loading="isRetryingEInvoice"
                @click="handleRetryEInvoice"
              >
                Reintentar SUNAT
              </VBtn>

              <VBtn
                v-if="sale && (sale.status === 'issued' || sale.status === 'completed') && can('credit_notes.create')"
                variant="tonal"
                color="warning"
                prepend-icon="tabler-receipt-refund"
                @click="router.push(`/sales/credit-notes/new?sale_id=${sale.id}`)"
              >
                Emitir nota de crédito
              </VBtn>

              <VBtn
                v-if="sale && (sale.status === 'issued' || sale.status === 'completed') && can('sales.cancel')"
                color="error"
                variant="outlined"
                prepend-icon="tabler-ban"
                @click="isCancelDialogOpen = true"
              >
                Anular Venta
              </VBtn>
            </div>
          </template>
        </PageHeader>
      </VCardText>
    </VCard>

    <div
      v-if="isLoading"
      class="text-center py-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <template v-else-if="sale">
      <!-- Alerta si la venta fue anulada -->
      <VAlert
        v-if="sale.status === 'cancelled'"
        type="error"
        variant="tonal"
        icon="tabler-alert-triangle"
        prominent
      >
        <div class="text-subtitle-1 font-weight-bold">
          VENTA ANULADA
        </div>
        <div class="text-body-2 mt-1">
          Esta venta fue anulada el <strong>{{ formatDateTime12h(sale.cancelled_at) }}</strong>
          <span v-if="sale.canceller_name"> por <strong>{{ sale.canceller_name }}</strong></span>.
        </div>
        <div
          v-if="sale.cancel_reason"
          class="text-caption mt-2 text-medium-emphasis"
        >
          <strong>Motivo:</strong> {{ sale.cancel_reason }}
        </div>
      </VAlert>

      <!-- Panel de Facturación Electrónica SUNAT -->
      <VCard v-if="sale.document_type !== 'sale_note'">
        <VCardText class="d-flex flex-wrap justify-space-between align-center gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar
              :color="einvoiceBadge(sale.einvoice_status).color"
              variant="tonal"
              size="40"
            >
              <VIcon
                :icon="sale.einvoice_status === 'accepted' ? 'tabler-check' : 'tabler-file-report'"
                size="24"
              />
            </VAvatar>

            <div>
              <div class="d-flex align-center gap-2">
                <span class="font-weight-medium">Estado SUNAT:</span>
                <VChip
                  size="small"
                  :color="einvoiceBadge(sale.einvoice_status).color"
                  variant="tonal"
                >
                  {{ einvoiceBadge(sale.einvoice_status).text }}
                </VChip>
              </div>

              <div
                v-if="sale.einvoice_response?.message"
                class="text-caption text-medium-emphasis mt-1"
              >
                {{ sale.einvoice_response.message }}
              </div>
            </div>
          </div>

          <div class="d-flex align-center gap-6 text-caption text-medium-emphasis">
            <div v-if="sale.einvoice_sent_at">
              <span>Enviado: </span>
              <strong class="text-high-emphasis">{{ formatDateTime12h(sale.einvoice_sent_at) }}</strong>
            </div>

            <div v-if="sale.einvoice_attempts">
              <span>Intentos: </span>
              <strong class="text-high-emphasis">{{ sale.einvoice_attempts }}</strong>
            </div>

            <div v-if="sale.einvoice_response?.hash">
              <span>Hash: </span>
              <strong class="text-high-emphasis font-mono">{{ sale.einvoice_response.hash.slice(0, 16) }}...</strong>
            </div>
          </div>
        </VCardText>
      </VCard>

      <!-- Datos del comprobante y Cliente -->
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VCard
            title="Información del Comprobante"
            class="h-100"
          >
            <VCardText>
              <div class="d-flex flex-column gap-3 text-body-2">
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Número:</span>
                  <div class="d-flex align-center gap-2">
                    <span class="font-weight-bold font-mono text-h6">{{ sale.full_number }}</span>
                    <VChip
                      size="x-small"
                      :color="documentTypeBadge(sale.document_type).color"
                      variant="tonal"
                    >
                      {{ documentTypeBadge(sale.document_type).text }}
                    </VChip>
                  </div>
                </div>

                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Fecha de emisión:</span>
                  <span class="font-mono">{{ formatDateTime12h(sale.issued_at) }}</span>
                </div>

                <div
                  v-if="sale.due_date"
                  class="d-flex justify-space-between"
                >
                  <span class="text-medium-emphasis">Fecha de vencimiento:</span>
                  <span class="font-mono">{{ formatDateDMY(sale.due_date) }}</span>
                </div>

                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Sede / Almacén:</span>
                  <span>{{ sale.branch_name }} · {{ sale.warehouse_name }}</span>
                </div>

                <div
                  v-if="sale.seller_name"
                  class="d-flex justify-space-between"
                >
                  <span class="text-medium-emphasis">Vendedor:</span>
                  <span>{{ sale.seller_name }}</span>
                </div>

                <div
                  v-if="sale.creator_name"
                  class="d-flex justify-space-between"
                >
                  <span class="text-medium-emphasis">Emitido por:</span>
                  <span>{{ sale.creator_name }}</span>
                </div>

                <div
                  v-if="sale.quotation_id"
                  class="d-flex justify-space-between align-center pt-2 border-t"
                >
                  <span class="text-medium-emphasis">Cotización vinculada:</span>
                  <VBtn
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-external-link"
                    @click="router.push(`/sales/quotations/${sale.quotation_id}`)"
                  >
                    Ver Cotización
                  </VBtn>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCard
            title="Datos del Cliente"
            class="h-100"
          >
            <VCardText>
              <div class="d-flex flex-column gap-3 text-body-2">
                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Nombre / Razón Social:</span>
                  <span class="font-weight-medium">{{ sale.client_name }}</span>
                </div>

                <div class="d-flex justify-space-between">
                  <span class="text-medium-emphasis">Documento Fiscal:</span>
                  <span class="font-mono">{{ sale.client_document_type?.toUpperCase?.() || 'DOC' }}: {{ sale.client_document_number || '—' }}</span>
                </div>

                <div
                  v-if="sale.client_address"
                  class="d-flex justify-space-between"
                >
                  <span class="text-medium-emphasis">Dirección:</span>
                  <span class="text-end">{{ sale.client_address }}</span>
                </div>

                <div class="d-flex justify-space-between align-center pt-2 border-t mt-auto">
                  <span class="text-medium-emphasis">Ficha del Cliente:</span>
                  <VBtn
                    v-if="sale.client_id"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="tabler-user"
                    @click="router.push(`/clients/${sale.client_id}`)"
                  >
                    Ver Cliente
                  </VBtn>
                  <span
                    v-else
                    class="text-caption text-medium-emphasis"
                  >Cliente sin registrar</span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla de ítems y totales -->
      <SaleItemsTable
        :items="sale.items || []"
        :sale="sale"
      />

      <!-- Tabla de cobros -->
      <SalePaymentsTable
        :payments="sale.payments || []"
        :sale="sale"
      />

      <!-- Cronograma de Cuotas a Crédito -->
      <SaleInstallmentsTable
        v-if="sale.payment_condition === 'credit'"
        :installments="sale.installments || []"
        :sale="sale"
        @reschedule="isRescheduleDialogOpen = true"
      />

      <!-- Notas adicionales -->
      <VCard
        v-if="sale.notes"
        title="Observaciones"
      >
        <VCardText>
          <p class="text-body-2 mb-0">
            {{ sale.notes }}
          </p>
        </VCardText>
      </VCard>
    </template>

    <!-- Diálogo para elegir formato PDF -->
    <VDialog
      v-model="isPdfDialogOpen"
      max-width="400"
    >
      <VCard title="Descargar comprobante">
        <VCardText>
          <p class="text-body-2 mb-4">
            Seleccione el formato deseado para el comprobante <strong>{{ sale?.full_number }}</strong>:
          </p>

          <div class="d-flex flex-column gap-3">
            <VBtn
              variant="tonal"
              color="primary"
              block
              prepend-icon="tabler-file"
              :loading="isDownloadingPdf"
              @click="downloadPdfFormat('a4')"
            >
              Comprobante A4 (Estándar)
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              block
              prepend-icon="tabler-receipt"
              :loading="isDownloadingPdf"
              @click="downloadPdfFormat('ticket')"
            >
              Ticket Térmico (80mm)
            </VBtn>
          </div>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn
            variant="text"
            color="secondary"
            @click="isPdfDialogOpen = false"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Diálogo de Anulación -->
    <CancelDocumentDialog
      v-model="isCancelDialogOpen"
      :document-number="sale?.full_number"
      title="Anular venta"
      description="Esta acción anulará la venta de forma irreversible, reversará el stock en el kardex y registrará la salida del dinero de la caja activa."
      :loading="isCancelling"
      @confirm="handleConfirmCancel"
    />

    <!-- Diálogo de Reprogramación de Cuotas -->
    <RescheduleDialog
      v-model="isRescheduleDialogOpen"
      :sale="sale"
      @rescheduled="loadSale"
    />
  </div>
</template>
