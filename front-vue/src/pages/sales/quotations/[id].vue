<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useNotify } from '@/composables/useNotify'
import QuotationForm from '@/modules/sales/components/quotations/QuotationForm.vue'
import { quotationsApi } from '@/modules/sales/services/quotationsApi'
import {
  quotationStatusColor,
  quotationStatusTitle,
} from '@/modules/sales/utils/status'

definePage({
  meta: {
    action: 'quotations.view',
    title: 'Detalle de Cotización',
    icon: 'tabler-file-dollar',
    description: 'Seguimiento, edición, verificación de stock y flujo de estados de la cotización comercial',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const notify = useNotify()
const { goBack } = useBackNavigation({ name: 'sales-quotations' })

const quotationId = String(route.params.id)
const quotation = ref(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const formRef = ref(null)

// Diálogo de anulación y rechazo
const dialog = ref({
  open: false,
  mode: 'cancel', // 'cancel' | 'reject'
  loading: false,
})

// Diálogo de verificación de stock disponible
const availabilityDialog = ref({
  open: false,
  loading: false,
  data: [],
})

const isDraft = computed(() => quotation.value?.status === 'draft')
const isSent = computed(() => quotation.value?.status === 'sent')
const isAccepted = computed(() => quotation.value?.status === 'accepted')
const isConverted = computed(() => quotation.value?.status === 'converted')

const loadQuotation = async () => {
  isLoading.value = true
  try {
    const res = await quotationsApi.get(quotationId)

    quotation.value = res?.data || null
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al cargar la cotización')
  }
  finally {
    isLoading.value = false
  }
}

onMounted(loadQuotation)

const handleUpdate = async ({ action, payload }) => {
  isSubmitting.value = true
  try {
    const res = await quotationsApi.update(quotationId, payload)

    notify.success('Cotización actualizada correctamente')
    quotation.value = res?.data || quotation.value

    if (action === 'send') {
      await quotationsApi.send(quotationId)
      notify.success('Cotización enviada al cliente')
      await loadQuotation()
    }
  }
  catch (err) {
    if (formRef.value) {
      formRef.value.setErrors(err)
    }
    else {
      notify.error(err?.data?.message || err?.message || 'Error al actualizar la cotización')
    }
  }
  finally {
    isSubmitting.value = false
  }
}

const handleSend = async () => {
  isSubmitting.value = true
  try {
    await quotationsApi.send(quotationId)
    notify.success('Cotización enviada al cliente')
    await loadQuotation()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al enviar la cotización')
  }
  finally {
    isSubmitting.value = false
  }
}

const handleAccept = async () => {
  isSubmitting.value = true
  try {
    await quotationsApi.accept(quotationId)
    notify.success('Cotización aceptada por el cliente')
    await loadQuotation()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al aceptar la cotización')
  }
  finally {
    isSubmitting.value = false
  }
}

const openRejectDialog = () => {
  dialog.value = {
    open: true,
    mode: 'reject',
    loading: false,
  }
}

const openCancelDialog = () => {
  dialog.value = {
    open: true,
    mode: 'cancel',
    loading: false,
  }
}

const handleConfirmDialog = async reason => {
  dialog.value.loading = true
  try {
    if (dialog.value.mode === 'reject') {
      await quotationsApi.reject(quotationId, reason)
      notify.success('Cotización rechazada')
    }
    else {
      await quotationsApi.cancel(quotationId, reason)
      notify.success('Cotización anulada')
    }
    dialog.value.open = false
    await loadQuotation()
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al procesar la acción')
  }
  finally {
    dialog.value.loading = false
  }
}

const handleDuplicate = async () => {
  isSubmitting.value = true
  try {
    const res = await quotationsApi.duplicate(quotationId)

    notify.success(`Cotización ${res?.data?.number || ''} duplicada`)
    if (res?.meta?.skipped_lines?.length) {
      notify.warning(`Se omitieron las líneas ${res.meta.skipped_lines.join(', ')} por contener productos inactivos`)
    }
    if (res?.data?.id) {
      router.push({ name: 'sales-quotations-id', params: { id: res.data.id } })
    }
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al duplicar')
  }
  finally {
    isSubmitting.value = false
  }
}

const handleDownloadPdf = async () => {
  try {
    await quotationsApi.downloadPdf(quotationId, quotation.value?.number)
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al descargar PDF')
  }
}

const handleCheckAvailability = async () => {
  availabilityDialog.value.open = true
  availabilityDialog.value.loading = true
  try {
    const res = await quotationsApi.availability(quotationId)

    availabilityDialog.value.data = res?.data || []
  }
  catch (err) {
    notify.error(err?.data?.message || err?.message || 'Error al consultar disponibilidad')
  }
  finally {
    availabilityDialog.value.loading = false
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader>
          <template #title>
            <div class="d-flex align-center flex-wrap gap-2">
              <span class="text-h4 font-weight-bold font-monospace">{{ quotation?.number || 'Cargando...' }}</span>
              <VChip
                v-if="quotation"
                class="text-uppercase"
                size="small"
                :color="quotationStatusColor(quotation.status)"
              >
                {{ quotationStatusTitle(quotation.status) }}
              </VChip>
              <VChip
                v-if="quotation?.is_expired && ['draft', 'sent'].includes(quotation.status)"
                size="small"
                color="error"
                variant="tonal"
              >
                Vencida
              </VChip>
            </div>
          </template>

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
                color="info"
                prepend-icon="tabler-packages"
                @click="handleCheckAvailability"
              >
                Disponibilidad
              </VBtn>

              <VBtn
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-file-download"
                @click="handleDownloadPdf"
              >
                PDF
              </VBtn>

              <VBtn
                v-if="can('quotations.create')"
                variant="tonal"
                color="secondary"
                prepend-icon="tabler-copy"
                :loading="isSubmitting"
                @click="handleDuplicate"
              >
                Duplicar
              </VBtn>

              <!-- Acciones según estado -->
              <template v-if="isDraft && can('quotations.update')">
                <VBtn
                  color="primary"
                  prepend-icon="tabler-send"
                  :loading="isSubmitting"
                  @click="handleSend"
                >
                  Enviar al cliente
                </VBtn>
              </template>

              <template v-if="isSent && can('quotations.update')">
                <VBtn
                  color="success"
                  prepend-icon="tabler-check"
                  :loading="isSubmitting"
                  @click="handleAccept"
                >
                  Aceptar
                </VBtn>

                <VBtn
                  color="warning"
                  variant="outlined"
                  prepend-icon="tabler-x"
                  :loading="isSubmitting"
                  @click="openRejectDialog"
                >
                  Rechazar
                </VBtn>
              </template>

              <!-- Anular: permitido en draft o sent -->
              <VBtn
                v-if="(isDraft || isSent) && can('quotations.cancel')"
                color="error"
                variant="outlined"
                prepend-icon="tabler-ban"
                :loading="isSubmitting"
                @click="openCancelDialog"
              >
                Anular
              </VBtn>

              <!-- Convertir en venta -->
              <VBtn
                v-if="(isSent || isAccepted) && can('sales.create')"
                color="primary"
                prepend-icon="tabler-shopping-cart-check"
                @click="router.push(`/sales/pos?quotation_id=${quotation.id}`)"
              >
                Convertir en venta
              </VBtn>

              <!-- Ver venta vinculada -->
              <VBtn
                v-if="isConverted && quotation?.converted_sale_id && can('sales.view')"
                color="primary"
                variant="tonal"
                prepend-icon="tabler-file-invoice"
                @click="router.push(`/sales/history/${quotation.converted_sale_id}`)"
              >
                Ver venta
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

    <template v-else-if="quotation">
      <!-- Motivos si fue rechazada o anulada -->
      <VAlert
        v-if="quotation.rejection_reason"
        type="warning"
        variant="tonal"
        icon="tabler-alert-circle"
        class="mb-6"
      >
        <strong>Motivo de rechazo:</strong> {{ quotation.rejection_reason }}
      </VAlert>

      <VAlert
        v-if="quotation.cancel_reason"
        type="error"
        variant="tonal"
        icon="tabler-ban"
        class="mb-6"
      >
        <strong>Motivo de anulación:</strong> {{ quotation.cancel_reason }}
      </VAlert>

      <QuotationForm
        ref="formRef"
        :quotation="quotation"
        :readonly="!isDraft"
        :is-submitting="isSubmitting"
        @submit="handleUpdate"
        @cancel="router.push({ name: 'sales-quotations' })"
      />
    </template>

    <!-- Diálogo de Confirmación para Anular o Rechazar -->
    <CancelDocumentDialog
      v-model="dialog.open"
      :title="dialog.mode === 'reject' ? 'Rechazar cotización' : 'Anular cotización'"
      :document-number="quotation?.number || ''"
      :description="dialog.mode === 'reject' ? 'La cotización quedará marcada como rechazada por el cliente.' : 'La cotización quedará anulada y no podrá ser reactivada.'"
      :reason-label="dialog.mode === 'reject' ? 'Motivo del rechazo *' : 'Motivo de anulación *'"
      :reason-placeholder="dialog.mode === 'reject' ? 'Ej. Precios no competitivos o cliente optó por otro proveedor' : 'Ej. Solicitud cancelada por el cliente'"
      :confirm-label="dialog.mode === 'reject' ? 'Confirmar rechazo' : 'Confirmar anulación'"
      :color="dialog.mode === 'reject' ? 'warning' : 'error'"
      :loading="dialog.loading"
      @confirm="handleConfirmDialog"
    />

    <!-- Diálogo de Disponibilidad de Stock -->
    <VDialog
      v-model="availabilityDialog.open"
      max-width="650"
    >
      <VCard>
        <VCardTitle class="d-flex align-center gap-2 pt-4 px-4">
          <VIcon
            icon="tabler-packages"
            color="info"
          />
          <span>Stock disponible en la sede activa</span>
        </VCardTitle>

        <VCardText class="pt-2">
          <p class="text-caption text-medium-emphasis mb-3">
            Muestra el stock físico actual en todos los almacenes de la sede para cada línea de la cotización.
          </p>

          <div
            v-if="availabilityDialog.loading"
            class="text-center py-6"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </div>

          <VList
            v-else
            lines="two"
            density="compact"
          >
            <VListItem
              v-for="item in quotation?.items || []"
              :key="item.id"
            >
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="32"
                >
                  <span class="text-caption font-weight-bold">{{ item.line }}</span>
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ item.description }}
              </VListItemTitle>
              <VListItemSubtitle>
                Requerido: <strong>{{ item.quantity }}</strong>
                · Disponible: <strong>{{ availabilityDialog.data.find(d => d.line === item.line)?.available ?? 0 }}</strong>
              </VListItemSubtitle>

              <template #append>
                <VChip
                  size="small"
                  :color="(availabilityDialog.data.find(d => d.line === item.line)?.available ?? 0) >= Number(item.quantity) ? 'success' : 'error'"
                  variant="tonal"
                >
                  {{ (availabilityDialog.data.find(d => d.line === item.line)?.available ?? 0) >= Number(item.quantity) ? 'Stock suficiente' : 'Stock insuficiente' }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </VCardText>

        <VCardActions class="justify-end pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="availabilityDialog.open = false"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
