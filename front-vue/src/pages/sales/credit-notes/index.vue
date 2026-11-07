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
import { creditNotesApi } from '@/modules/sales/services/creditNotesApi'
import { useCreditNotesStore } from '@/modules/sales/stores/creditNotesStore'
import { formatDateDMY } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'credit_notes.view',
    title: 'Notas de Crédito',
    icon: 'tabler-receipt-refund',
    description: 'Registro, emisión y seguimiento de notas de crédito electrónicas de ventas.',
  },
})

const router = useRouter()
const store = useCreditNotesStore()
const { can } = useAcl()
const notify = useNotify()

const isCancelDialogOpen = ref(false)
const isCancelling = ref(false)
const noteToCancel = ref(null)

const isPdfDialogOpen = ref(false)
const selectedNoteForPdf = ref(null)
const isDownloadingPdf = ref(false)

const STATUS_OPTIONS = [
  { title: 'Emitida', value: 'issued' },
  { title: 'Anulada', value: 'cancelled' },
]

const EINVOICE_STATUS_OPTIONS = [
  { title: 'Pendiente', value: 'pending' },
  { title: 'Enviada', value: 'sent' },
  { title: 'Aceptada', value: 'accepted' },
  { title: 'Rechazada', value: 'rejected' },
  { title: 'No aplica', value: 'not_applicable' },
]

const REASON_OPTIONS = [
  { title: '01 · Anulación de la operación', value: '01' },
  { title: '04 · Descuento global', value: '04' },
  { title: '05 · Descuento por ítem', value: '05' },
  { title: '06 · Devolución total', value: '06' },
  { title: '07 · Devolución por ítem', value: '07' },
  { title: '09 · Disminución en el valor', value: '09' },
]

const table = useServerTable({
  fetch: params => store.fetchCreditNotes(params),
  filters: {
    status: null,
    reason_code: null,
    einvoice_status: null,
    date_from: null,
    date_to: null,
    client_id: null,
  },
})

const headers = [
  { title: 'EMISIÓN', key: 'issue_date', sortable: false, alwaysVisible: true },
  { title: 'NOTA DE CRÉDITO', key: 'full_number', sortable: false, alwaysVisible: true },
  { title: 'AFECTA A', key: 'sale_full_number', sortable: false },
  { title: 'CLIENTE', key: 'client', sortable: false },
  { title: 'MOTIVO', key: 'reason_label', sortable: false },
  { title: 'TOTAL', key: 'total', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'SUNAT', key: 'einvoice_status', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center', width: 140 },
]

const loadSummary = async () => {
  await store.fetchSummary()
}

onMounted(() => {
  loadSummary()
})

const statusBadge = status => {
  switch (status) {
  case 'issued':
    return { text: 'Emitida', color: 'success' }
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
    return { text: 'No aplica', color: 'secondary' }
  default:
    return { text: status, color: 'default' }
  }
}

const openCancel = note => {
  noteToCancel.value = note
  isCancelDialogOpen.value = true
}

const confirmCancel = async cancelReason => {
  if (!noteToCancel.value) return
  isCancelling.value = true
  try {
    await creditNotesApi.cancel(noteToCancel.value.id, { cancel_reason: cancelReason })
    notify.success(`Nota de crédito ${noteToCancel.value.full_number} anulada correctamente`)
    isCancelDialogOpen.value = false
    noteToCancel.value = null
    await Promise.all([table.reload(), loadSummary()])
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al anular la nota de crédito')
  }
  finally {
    isCancelling.value = false
  }
}

const handleRetryEInvoice = async note => {
  try {
    await creditNotesApi.retryEInvoice(note.id)
    notify.success(`Envío electrónico encolado para ${note.full_number}`)
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al reintentar envío a SUNAT')
  }
}

const openPdfDialog = note => {
  selectedNoteForPdf.value = note
  isPdfDialogOpen.value = true
}

const downloadPdfFormat = async format => {
  if (!selectedNoteForPdf.value) return
  isDownloadingPdf.value = true
  try {
    await creditNotesApi.downloadPdf(
      selectedNoteForPdf.value.id,
      format,
      `${selectedNoteForPdf.value.full_number}-${format}.pdf`,
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
                Total Notas Emitidas
              </div>
              <div class="text-h5 font-weight-bold mt-1">
                {{ store.summary.issued_count || 0 }}
              </div>
            </div>
            <VAvatar
              color="primary"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-receipt-refund"
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
                Importe Total Emitido
              </div>
              <div class="text-h5 font-weight-bold mt-1 text-primary">
                {{ formatAmount(store.summary.totals_by_currency?.[0]?.total || 0, store.summary.totals_by_currency?.[0]?.currency_symbol || 'S/') }}
              </div>
            </div>
            <VAvatar
              color="info"
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
                Total Anuladas
              </div>
              <div class="text-h5 font-weight-bold mt-1 text-error">
                {{ store.summary.cancelled_count || 0 }}
              </div>
            </div>
            <VAvatar
              color="error"
              variant="tonal"
              size="42"
            >
              <VIcon
                icon="tabler-ban"
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
        table-id="sales-credit-notes"
        :headers="headers"
        :items="store.items"
        :total-items="store.meta.total"
        :loading="store.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="FC01-00000001 o Cliente"
        create-label="Nueva nota de crédito"
        :creatable="can('credit_notes.create')"
        @refresh="table.reload"
        @create="router.push('/sales/credit-notes/new')"
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
              v-model="table.filters.status"
              :items="STATUS_OPTIONS"
              label="Estado"
              placeholder="Seleccione estado"
              density="compact"
              clearable
            />

            <VSelect
              v-model="table.filters.reason_code"
              :items="REASON_OPTIONS"
              label="Motivo"
              placeholder="Seleccione motivo"
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
        <template #item.issue_date="{ item }">
          <div class="text-no-wrap font-mono text-body-2">
            {{ formatDateDMY(item.issue_date) }}
          </div>
        </template>

        <!-- Columna Comprobante -->
        <template #item.full_number="{ item }">
          <span class="font-weight-bold font-mono">{{ item.full_number }}</span>
        </template>

        <!-- Columna Afecta A -->
        <template #item.sale_full_number="{ item }">
          <RouterLink
            v-if="item.sale_id"
            :to="`/sales/history/${item.sale_id}`"
            class="text-primary font-weight-bold font-mono"
          >
            {{ item.sale?.full_number }}
          </RouterLink>
          <span
            v-else
            class="text-medium-emphasis font-weight-bold font-mono"
          >{{ item.sale?.full_number }}</span>
        </template>

        <!-- Columna Cliente -->
        <template #item.client="{ item }">
          <div class="d-flex flex-column py-1">
            <RouterLink
              v-if="item.client_id"
              :to="`/clients/${item.client_id}`"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ item.client_name }}
            </RouterLink>
            <span
              v-else
              class="font-weight-medium text-high-emphasis"
            >{{ item.client_name }}</span>
            <span
              v-if="item.client_document_number"
              class="text-caption text-medium-emphasis"
            >
              {{ item.client_document_type?.toUpperCase?.() }} {{ item.client_document_number }}
            </span>
          </div>
        </template>

        <!-- Columna Motivo -->
        <template #item.reason_label="{ item }">
          <div>
            {{ item.reason_name || item.reason_code }}
          </div>
        </template>

        <!-- Columna Total -->
        <template #item.total="{ item }">
          <span class="font-weight-bold font-mono">
            {{ formatAmount(item.total, item.currency_symbol) }}
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
              @click="router.push(`/sales/credit-notes/${item.id}`)"
            />

            <RowActionButton
              action="custom"
              icon="tabler-printer"
              tooltip="Descargar PDF"
              @click="openPdfDialog(item)"
            />

            <RowActionButton
              v-if="['rejected', 'pending'].includes(item.einvoice_status) && can('document_series.manage')"
              action="custom"
              icon="tabler-refresh"
              tooltip="Reintentar envío SUNAT"
              @click="handleRetryEInvoice(item)"
            />

            <RowActionButton
              v-if="item.status === 'issued' && can('credit_notes.cancel')"
              action="delete"
              tooltip="Anular nota de crédito"
              @click="openCancel(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Diálogo de Anulación -->
    <CancelDocumentDialog
      v-model="isCancelDialogOpen"
      title="Anular Nota de Crédito"
      :document-number="noteToCancel?.full_number"
      description="Esta acción anulará la nota de crédito y revertirá cualquier devolución de stock al inventario, reposición de cuotas o ajuste de saldo realizado."
      :loading="isCancelling"
      @confirm="confirmCancel"
    />

    <!-- Diálogo de Descarga de PDF -->
    <VDialog
      v-model="isPdfDialogOpen"
      max-width="400"
    >
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-printer"
              color="primary"
            />
            <span>Descargar Nota de Crédito</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Seleccione el formato para el comprobante
            <strong class="font-mono">{{ selectedNoteForPdf?.full_number }}</strong>:
          </p>

          <div class="d-flex flex-column gap-3">
            <VBtn
              variant="tonal"
              color="primary"
              prepend-icon="tabler-file-text"
              block
              :loading="isDownloadingPdf"
              @click="downloadPdfFormat('a4')"
            >
              Formato A4 (Estándar)
            </VBtn>

            <VBtn
              variant="tonal"
              color="secondary"
              prepend-icon="tabler-receipt"
              block
              :loading="isDownloadingPdf"
              @click="downloadPdfFormat('ticket')"
            >
              Formato Ticket (80mm)
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
