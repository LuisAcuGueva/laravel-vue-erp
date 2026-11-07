<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import CreditNoteDetailItems from '@/modules/sales/components/credit-notes/CreditNoteDetailItems.vue'
import CreditNoteInstallmentAdjustments from '@/modules/sales/components/credit-notes/CreditNoteInstallmentAdjustments.vue'
import { creditNotesApi } from '@/modules/sales/services/creditNotesApi'
import { useCreditNotesStore } from '@/modules/sales/stores/creditNotesStore'
import { formatDateDMY, formatDateTime12h } from '@/utils/dates'
import { formatAmount } from '@/utils/documentTotals'

definePage({
  meta: {
    action: 'credit_notes.view',
    title: 'Detalle de Nota de Crédito',
    icon: 'tabler-receipt-refund',
    description: 'Consulta detallada de la nota de crédito, ítems modificados, liquidación y comprobante SUNAT.',
  },
})

const route = useRoute()
const store = useCreditNotesStore()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation('/sales/credit-notes')

const noteId = computed(() => route.params.id)
const note = computed(() => store.currentNote)
const currencyDecimals = computed(() => note.value?.currency_decimals ?? 2)
const isLoading = ref(true)

const isCancelDialogOpen = ref(false)
const isCancelling = ref(false)

const isPdfDialogOpen = ref(false)
const isDownloadingPdf = ref(false)
const isRetryingEInvoice = ref(false)

const loadNote = async () => {
  isLoading.value = true
  try {
    await store.fetchCreditNote(noteId.value)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar la nota de crédito')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadNote()
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
    return { text: 'Aceptada por SUNAT', color: 'success' }
  case 'rejected':
    return { text: 'Rechazada por SUNAT', color: 'error' }
  case 'sent':
    return { text: 'Enviada / En proceso', color: 'info' }
  case 'pending':
    return { text: 'Pendiente de envío', color: 'warning' }
  case 'not_applicable':
    return { text: 'No aplica (Interna)', color: 'secondary' }
  default:
    return { text: status, color: 'default' }
  }
}

const handleCancel = async cancelReason => {
  isCancelling.value = true
  try {
    await creditNotesApi.cancel(noteId.value, { cancel_reason: cancelReason })
    notify.success('Nota de crédito anulada correctamente')
    isCancelDialogOpen.value = false
    await loadNote()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al anular la nota de crédito')
  }
  finally {
    isCancelling.value = false
  }
}

const handleRetryEInvoice = async () => {
  isRetryingEInvoice.value = true
  try {
    await creditNotesApi.retryEInvoice(noteId.value)
    notify.success('Reintento de envío electrónico encolado')
    await loadNote()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al reintentar envío a SUNAT')
  }
  finally {
    isRetryingEInvoice.value = false
  }
}

const downloadPdfFormat = async format => {
  if (!note.value) return
  isDownloadingPdf.value = true
  try {
    await creditNotesApi.downloadPdf(
      note.value.id,
      format,
      `${note.value.full_number}-${format}.pdf`,
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
                v-if="note && ['rejected', 'pending'].includes(note.einvoice_status) && can('document_series.manage')"
                variant="tonal"
                color="info"
                prepend-icon="tabler-refresh"
                :loading="isRetryingEInvoice"
                @click="handleRetryEInvoice"
              >
                Reintentar SUNAT
              </VBtn>

              <VBtn
                v-if="note && note.status === 'issued' && can('credit_notes.cancel')"
                color="error"
                variant="outlined"
                prepend-icon="tabler-ban"
                @click="isCancelDialogOpen = true"
              >
                Anular Nota
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

    <template v-else-if="note">
      <!-- Alerta si fue anulada -->
      <VAlert
        v-if="note.status === 'cancelled'"
        type="error"
        variant="tonal"
        icon="tabler-alert-triangle"
        prominent
      >
        <div class="text-subtitle-1 font-weight-bold">
          NOTA DE CRÉDITO ANULADA
        </div>
        <div class="text-body-2 mt-1">
          Esta nota fue anulada el <strong>{{ formatDateTime12h(note.cancelled_at) }}</strong>
          <span v-if="note.canceller_name"> por <strong>{{ note.canceller_name }}</strong></span>.
        </div>
        <div
          v-if="note.cancel_reason"
          class="text-caption mt-2 text-medium-emphasis"
        >
          <strong>Motivo:</strong> {{ note.cancel_reason }}
        </div>
      </VAlert>

      <!-- Panel Facturación Electrónica SUNAT -->
      <VCard v-if="note.is_electronic">
        <VCardItem>
          <div class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
              <VIcon
                icon="tabler-send"
                color="primary"
              />
              <span class="text-subtitle-1 font-weight-bold">Facturación Electrónica SUNAT</span>
            </div>

            <VChip
              :color="einvoiceBadge(note.einvoice_status).color"
              variant="tonal"
            >
              {{ einvoiceBadge(note.einvoice_status).text }}
            </VChip>
          </div>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                FECHA DE ENVÍO
              </div>
              <div class="text-body-2 font-mono">
                {{ formatDateTime12h(note.einvoice_sent_at) }}
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                INTENTOS DE ENVÍO
              </div>
              <div class="text-body-2 font-mono">
                {{ note.einvoice_attempts || 0 }}
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                ESTADO
              </div>
              <div class="text-body-2">
                {{ einvoiceBadge(note.einvoice_status).text }}
              </div>
            </VCol>
          </VRow>

          <div
            v-if="note.einvoice_response"
            class="mt-3 pa-3 bg-var-theme-surface rounded border text-caption font-mono"
          >
            {{ note.einvoice_response?.message || JSON.stringify(note.einvoice_response) }}
          </div>
        </VCardText>
      </VCard>

      <!-- Resumen General -->
      <VRow>
        <!-- Datos de Comprobante y Cliente -->
        <VCol
          cols="12"
          md="8"
        >
          <VCard class="h-100">
            <VCardItem>
              <VCardTitle class="d-flex align-center justify-space-between">
                <span class="font-mono">{{ note.full_number }}</span>
                <VChip
                  :color="statusBadge(note.status).color"
                  variant="tonal"
                  size="small"
                >
                  {{ statusBadge(note.status).text }}
                </VChip>
              </VCardTitle>
            </VCardItem>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    COMPROBANTE AFECTADO
                  </div>
                  <div class="text-body-1 font-weight-bold font-mono">
                    <RouterLink
                      v-if="note.sale_id"
                      :to="`/sales/history/${note.sale_id}`"
                      class="text-primary"
                    >
                      {{ note.sale?.full_number }}
                    </RouterLink>
                    <span v-else>{{ note.sale?.full_number }}</span>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    FECHA DE EMISIÓN
                  </div>
                  <div class="text-body-1 font-mono">
                    {{ formatDateDMY(note.issue_date) }}
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    CLIENTE
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    <RouterLink
                      v-if="note.client_id"
                      :to="`/clients/${note.client_id}`"
                      class="text-primary"
                    >
                      {{ note.client_name }}
                    </RouterLink>
                    <span v-else>{{ note.client_name }}</span>
                  </div>
                  <div class="text-caption text-medium-emphasis font-mono">
                    Doc: {{ note.client_document_number || 'S/D' }}
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <div class="text-caption text-medium-emphasis">
                    SEDE / ALMACÉN
                  </div>
                  <div class="text-body-2">
                    {{ note.branch_name || 'Principal' }}
                    <span v-if="note.warehouse_name"> · Reingreso: {{ note.warehouse_name }}</span>
                  </div>
                </VCol>

                <VCol cols="12">
                  <VDivider class="my-2" />
                  <div class="text-caption text-medium-emphasis">
                    MOTIVO SUNAT (CATÁLOGO 09)
                  </div>
                  <div class="text-body-2 font-weight-medium text-primary">
                    {{ note.reason_name || note.reason_code }}
                  </div>
                  <div
                    v-if="note.reason_description"
                    class="text-caption text-medium-emphasis mt-1"
                  >
                    <strong>Sustento:</strong> {{ note.reason_description }}
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Totales de la Nota -->
        <VCol
          cols="12"
          md="4"
        >
          <VCard class="h-100">
            <VCardItem>
              <VCardTitle>Importes de la Nota</VCardTitle>
            </VCardItem>

            <VCardText>
              <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-space-between text-body-2">
                  <span class="text-medium-emphasis">Op. Gravada:</span>
                  <span class="font-mono">{{ note.currency_symbol }} {{ parseFloat(note.taxable_base || 0).toFixed(currencyDecimals) }}</span>
                </div>

                <div
                  v-if="parseFloat(note.exempt_base || 0) > 0"
                  class="d-flex justify-space-between text-body-2"
                >
                  <span class="text-medium-emphasis">Op. Exonerada:</span>
                  <span class="font-mono">{{ note.currency_symbol }} {{ parseFloat(note.exempt_base || 0).toFixed(currencyDecimals) }}</span>
                </div>

                <div
                  v-if="parseFloat(note.unaffected_base || 0) > 0"
                  class="d-flex justify-space-between text-body-2"
                >
                  <span class="text-medium-emphasis">Op. Inafecta:</span>
                  <span class="font-mono">{{ note.currency_symbol }} {{ parseFloat(note.unaffected_base || 0).toFixed(currencyDecimals) }}</span>
                </div>

                <div class="d-flex justify-space-between text-body-2">
                  <span class="text-medium-emphasis">IGV ({{ parseFloat(note.tax_rate || 18).toFixed(0) }}%):</span>
                  <span class="font-mono">{{ note.currency_symbol }} {{ parseFloat(note.tax_total || 0).toFixed(currencyDecimals) }}</span>
                </div>

                <VDivider class="my-1" />

                <div class="d-flex justify-space-between text-h6 font-weight-bold">
                  <span>TOTAL:</span>
                  <span class="font-mono text-primary">{{ note.currency_symbol }} {{ parseFloat(note.total || 0).toFixed(currencyDecimals) }}</span>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla de Ítems Afectados -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-list"
              color="primary"
            />
            <span>Detalle de Ítems</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <CreditNoteDetailItems
            :items="note.items || []"
            :currency-symbol="note.currency_symbol || 'S/'"
            :currency-decimals="currencyDecimals"
          />
        </VCardText>
      </VCard>

      <!-- Liquidación y Aplicación del Monto -->
      <VCard>
        <VCardItem>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-cash"
              color="primary"
            />
            <span>Liquidación y Aplicación</span>
          </VCardTitle>
        </VCardItem>

        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                MODO DE LIQUIDACIÓN
              </div>
              <div class="text-body-2 font-weight-medium">
                {{ note.settlement_mode === 'refund' ? 'Reembolso Directo' : 'Automático (Rebaja Saldo)' }}
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                APLICADO A SALDO DEUDA
              </div>
              <div class="text-body-1 font-mono font-weight-bold text-warning">
                {{ note.currency_symbol }} {{ parseFloat(note.applied_to_balance || 0).toFixed(currencyDecimals) }}
              </div>
            </VCol>

            <VCol
              cols="12"
              sm="4"
            >
              <div class="text-caption text-medium-emphasis">
                REEMBOLSADO AL CLIENTE
              </div>
              <div class="text-body-1 font-mono font-weight-bold text-success">
                {{ note.currency_symbol }} {{ parseFloat(note.refunded_amount || 0).toFixed(currencyDecimals) }}
              </div>
              <div
                v-if="note.refund_payment_method_name"
                class="text-caption text-medium-emphasis"
              >
                Vía: {{ note.refund_payment_method_name }}
              </div>
            </VCol>
          </VRow>

          <!-- Cuotas ajustadas si existen -->
          <div
            v-if="note.installment_adjustments && note.installment_adjustments.length > 0"
            class="mt-4"
          >
            <div class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-2">
              Ajuste en Cuotas por Cobrar (Reducción desde la última cuota)
            </div>

            <CreditNoteInstallmentAdjustments
              :adjustments="note.installment_adjustments"
              :currency-symbol="note.currency_symbol || 'S/'"
              :currency-decimals="currencyDecimals"
            />
          </div>
        </VCardText>
      </VCard>
    </template>

    <!-- Diálogo de Anulación -->
    <CancelDocumentDialog
      v-model="isCancelDialogOpen"
      title="Anular Nota de Crédito"
      :document-number="note?.full_number"
      description="Esta acción anulará la nota de crédito y revertirá cualquier devolución de stock al inventario, reposición de cuotas o ajuste de saldo realizado."
      :loading="isCancelling"
      @confirm="handleCancel"
    />

    <!-- Diálogo de Impresión / Descarga de PDF -->
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
            <strong class="font-mono">{{ note?.full_number }}</strong>:
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
