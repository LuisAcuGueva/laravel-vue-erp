<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import ClientPicker from '@/components/common/ClientPicker.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { salesApi } from '@/modules/sales/services/salesApi'
import { useSalesStore } from '@/modules/sales/stores/salesStore'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'sales.view',
    title: 'Historial de Ventas',
    icon: 'tabler-file-text',
    description: 'Registro y consulta de comprobantes emitidos, notas de venta y cobranzas.',
  },
})

const router = useRouter()
const store = useSalesStore()
const { can } = useAcl()
const notify = useNotify()

const isCancelDialogOpen = ref(false)
const isCancelling = ref(false)
const saleToCancel = ref(null)

const isPdfDialogOpen = ref(false)
const selectedSaleForPdf = ref(null)
const isDownloadingPdf = ref(false)

const DOCUMENT_TYPE_OPTIONS = [
  { title: 'Factura', value: 'invoice' },
  { title: 'Boleta de Venta', value: 'receipt' },
  { title: 'Nota de Venta', value: 'sale_note' },
]

const STATUS_OPTIONS = [
  { title: 'Completada', value: 'completed' },
  { title: 'Anulada', value: 'cancelled' },
]

const EINVOICE_STATUS_OPTIONS = [
  { title: 'Pendiente', value: 'pending' },
  { title: 'Enviada', value: 'sent' },
  { title: 'Aceptada', value: 'accepted' },
  { title: 'Rechazada', value: 'rejected' },
  { title: 'No aplica', value: 'not_applicable' },
]

const table = useServerTable({
  fetch: params => store.fetchSales(params),
  filters: {
    document_type: null,
    status: null,
    einvoice_status: null,
    date_from: null,
    date_to: null,
    client_id: null,
  },
})

const headers = [
  { title: 'EMISIÓN', key: 'issued_at', sortable: false, alwaysVisible: true },
  { title: 'COMPROBANTE', key: 'full_number', sortable: false, alwaysVisible: true },
  { title: 'CLIENTE', key: 'client', sortable: false },
  { title: 'CONDICIÓN', key: 'payment_condition', sortable: false },
  { title: 'TOTAL', key: 'total_amount', sortable: false, align: 'end' },
  { title: 'PAGADO', key: 'paid_amount', sortable: false, align: 'end' },
  { title: 'SALDO', key: 'balance', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'SUNAT', key: 'einvoice_status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const formatDate = val => {
  if (!val) return '—'
  const d = new Date(val)

  return Number.isNaN(d.getTime()) ? val : d.toLocaleString('es-PE', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).replace(/\//g, '-')
}

const documentTypeBadge = type => {
  switch (type) {
  case 'invoice':
    return { text: 'Factura', color: 'primary' }
  case 'receipt':
    return { text: 'Boleta', color: 'info' }
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
    return { text: 'Aceptada', color: 'success' }
  case 'rejected':
    return { text: 'Rechazada', color: 'error' }
  case 'sent':
    return { text: 'Enviada', color: 'info' }
  case 'pending':
    return { text: 'Pendiente', color: 'warning' }
  case 'not_applicable':
    return { text: 'N/A', color: 'secondary' }
  default:
    return { text: status || '—', color: 'default' }
  }
}

const openDetail = sale => {
  router.push(`/sales/history/${sale.id}`)
}

const openCancel = sale => {
  saleToCancel.value = sale
  isCancelDialogOpen.value = true
}

const handleConfirmCancel = async reason => {
  if (!saleToCancel.value) return
  isCancelling.value = true
  try {
    await salesApi.cancel(saleToCancel.value.id, { cancellation_reason: reason })
    notify.success(`Venta ${saleToCancel.value.full_number} anulada correctamente`)
    isCancelDialogOpen.value = false
    saleToCancel.value = null
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al anular la venta')
  }
  finally {
    isCancelling.value = false
  }
}

const handleRetryEInvoice = async sale => {
  try {
    await salesApi.retryEInvoice(sale.id)
    notify.success(`Envío electrónico encolado para ${sale.full_number}`)
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al reintentar envío a SUNAT')
  }
}

const openPdfDialog = sale => {
  selectedSaleForPdf.value = sale
  isPdfDialogOpen.value = true
}

const downloadPdfFormat = async format => {
  if (!selectedSaleForPdf.value) return
  isDownloadingPdf.value = true
  try {
    await salesApi.downloadPdf(
      selectedSaleForPdf.value.id,
      format,
      `${selectedSaleForPdf.value.full_number}-${format}.pdf`,
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
      <VCardText>
        <PageHeader />
      </VCardText>
    </VCard>

    <!-- Métricas del listado -->
    <VRow>
      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between py-4">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-medium">
                Total Facturado
              </div>
              <div class="text-h5 font-weight-bold mt-1">
                {{ formatAmount(store.totals.total_amount || 0, 'S/') }}
              </div>
            </div>
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-currency-dollar"
                size="24"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between py-4">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-medium">
                Total Cobrado
              </div>
              <div class="text-h5 font-weight-bold mt-1 text-success">
                {{ formatAmount(store.totals.paid_amount || 0, 'S/') }}
              </div>
            </div>
            <VAvatar
              color="success"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-cash"
                size="24"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        sm="4"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between py-4">
            <div>
              <div class="text-caption text-medium-emphasis text-uppercase font-weight-medium">
                Saldo Pendiente
              </div>
              <div class="text-h5 font-weight-bold mt-1 text-warning">
                {{ formatAmount(store.totals.balance || 0, 'S/') }}
              </div>
            </div>
            <VAvatar
              color="warning"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-alert-circle"
                size="24"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="sales-history"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="F001-00000001 o Los Andes"
        create-label="Nueva venta (POS)"
        :creatable="can('sales.create')"
        @refresh="table.reload"
        @create="router.push('/sales/pos')"
      >
        <template #filters>
          <ClientPicker
            v-model="table.filters.client_id"
            label="Cliente"
            placeholder="Escribe nombre o documento, p. ej. 20601234567"
            density="compact"
            :creatable="false"
            clearable
            class="sales-filter-client"
          />

          <div class="sales-filters-row-2 d-flex flex-wrap align-center gap-4 w-100">
            <VSelect
              v-model="table.filters.document_type"
              :items="DOCUMENT_TYPE_OPTIONS"
              label="Tipo de comprobante"
              placeholder="Seleccione tipo"
              density="compact"
              clearable
            />

            <VSelect
              v-model="table.filters.status"
              :items="STATUS_OPTIONS"
              label="Estado"
              placeholder="Seleccione estado"
              density="compact"
              clearable
            />

            <VSelect
              v-model="table.filters.einvoice_status"
              :items="EINVOICE_STATUS_OPTIONS"
              label="Estado SUNAT"
              placeholder="Seleccione estado SUNAT"
              density="compact"
              clearable
            />

            <VTextField
              v-model="table.filters.date_from"
              type="date"
              label="Fecha desde"
              placeholder="2026-01-01"
              density="compact"
              clearable
            />

            <VTextField
              v-model="table.filters.date_to"
              type="date"
              label="Fecha hasta"
              placeholder="2026-12-31"
              density="compact"
              clearable
            />
          </div>
        </template>

        <!-- Columna Emisión -->
        <template #item.issued_at="{ item }">
          <div class="text-no-wrap font-mono text-body-2">
            {{ formatDate(item.issued_at) }}
          </div>
        </template>

        <!-- Columna Comprobante -->
        <template #item.full_number="{ item }">
          <div class="d-flex align-center gap-2">
            <span class="font-weight-bold font-mono">{{ item.full_number }}</span>
            <VChip
              size="x-small"
              :color="documentTypeBadge(item.document_type).color"
              variant="tonal"
            >
              {{ documentTypeBadge(item.document_type).text }}
            </VChip>
          </div>
        </template>

        <!-- Columna Cliente -->
        <template #item.client="{ item }">
          <div class="d-flex flex-column py-1">
            <RouterLink
              v-if="item.client_id"
              :to="`/clients/${item.client_id}`"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ item.client_name || 'Cliente Genérico' }}
            </RouterLink>
            <span
              v-else
              class="font-weight-medium text-high-emphasis"
            >{{ item.client_name || 'Cliente Genérico' }}</span>
            <span
              v-if="item.client_document_number"
              class="text-caption text-medium-emphasis"
            >
              {{ item.client_document_type?.toUpperCase?.() }} {{ item.client_document_number }}
            </span>
          </div>
        </template>

        <!-- Columna Condición -->
        <template #item.payment_condition="{ item }">
          <div>
            <span class="text-capitalize">{{ item.payment_condition === 'credit' ? 'Crédito' : 'Contado' }}</span>
            <span
              v-if="item.payment_condition === 'credit' && item.credit_days"
              class="text-caption text-medium-emphasis"
            >
              ({{ item.credit_days }} d)
            </span>
          </div>
        </template>

        <!-- Columna Total -->
        <template #item.total_amount="{ item }">
          <span class="font-weight-bold font-mono">
            {{ formatAmount(item.total_amount, item.currency?.symbol || 'S/') }}
          </span>
        </template>

        <!-- Columna Pagado -->
        <template #item.paid_amount="{ item }">
          <span class="font-mono text-success">
            {{ formatAmount(item.paid_amount, item.currency?.symbol || 'S/') }}
          </span>
        </template>

        <!-- Columna Saldo -->
        <template #item.balance="{ item }">
          <span
            class="font-mono"
            :class="Number(item.balance) > 0 ? 'text-warning font-weight-medium' : 'text-medium-emphasis'"
          >
            {{ formatAmount(item.balance, item.currency?.symbol || 'S/') }}
          </span>
        </template>

        <!-- Columna Estado -->
        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="statusBadge(item.status).color"
            variant="tonal"
          >
            {{ statusBadge(item.status).text }}
          </VChip>
        </template>

        <!-- Columna SUNAT -->
        <template #item.einvoice_status="{ item }">
          <VChip
            size="small"
            :color="einvoiceBadge(item.einvoice_status).color"
            variant="tonal"
          >
            {{ einvoiceBadge(item.einvoice_status).text }}
          </VChip>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <RowActionButton
              action="view"
              tooltip="Ver detalle"
              @click="openDetail(item)"
            />

            <RowActionButton
              action="custom"
              icon="tabler-printer"
              tooltip="Descargar comprobante PDF"
              @click="openPdfDialog(item)"
            />

            <RowActionButton
              v-if="['rejected', 'pending'].includes(item.einvoice_status) && item.document_type !== 'sale_note' && can('document_series.manage')"
              action="custom"
              icon="tabler-refresh"
              tooltip="Reintentar envío SUNAT"
              @click="handleRetryEInvoice(item)"
            />

            <RowActionButton
              v-if="item.status === 'completed' && can('sales.cancel')"
              action="delete"
              tooltip="Anular venta"
              @click="openCancel(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Diálogo para elegir formato PDF -->
    <VDialog
      v-model="isPdfDialogOpen"
      max-width="400"
    >
      <VCard title="Descargar comprobante">
        <VCardText>
          <p class="text-body-2 mb-4">
            Seleccione el formato deseado para el comprobante <strong>{{ selectedSaleForPdf?.full_number }}</strong>:
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
      :document-number="saleToCancel?.full_number"
      title="Anular venta"
      description="Esta acción anulará la venta de forma irreversible, reversará el stock en el kardex y registrará la salida del dinero de la caja activa."
      :loading="isCancelling"
      @confirm="handleConfirmCancel"
    />
  </div>
</template>

<style scoped>
.sales-filter-client {
  flex: 2 1 20rem;
  min-inline-size: 16rem;
  max-inline-size: 34rem;

  :deep(.v-input) {
    inline-size: 100%;
  }
}

.sales-filters-row-2 {
  flex-basis: 100%;

  :deep(.v-input) {
    flex: 1 1 9rem;
    min-inline-size: 8rem;
    max-inline-size: 14rem;
  }
}

@media (max-width: 599.98px) {
  .sales-filter-client {
    max-inline-size: none;
    inline-size: 100%;
  }

  .sales-filters-row-2 {
    flex-direction: column;
    align-items: stretch !important;

    :deep(.v-input) {
      max-inline-size: none;
      inline-size: 100%;
    }
  }
}
</style>
