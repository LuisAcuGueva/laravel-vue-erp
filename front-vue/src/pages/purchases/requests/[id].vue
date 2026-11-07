<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import PurchaseRequestForm from '@/modules/purchases/components/requests/PurchaseRequestForm.vue'
import PurchaseRequestItemsTable from '@/modules/purchases/components/requests/PurchaseRequestItemsTable.vue'
import { purchaseRequestsApi } from '@/modules/purchases/services/purchaseRequestsApi'
import { requestStatusColor, requestStatusTitle } from '@/modules/purchases/utils/status'
import { formatDateDMY } from '@/utils/dates'

definePage({
  meta: {
    action: 'purchase_requests.view',
    title: 'Detalle del requerimiento',
    icon: 'tabler-clipboard-list',
    description: 'Detalle, aprobación y avance de ordenado del requerimiento de compra.',
  },
})

const route = useRoute()
const router = useRouter()
const { can } = useAcl()
const { alert } = useConfirm()
const notify = useNotify()
const authStore = useAuthStore()
const { goBack } = useBackNavigation({ name: 'purchases-requests' })

const requestId = route.params.id
const request = ref(null)
const isLoading = ref(true)
const isBusy = ref(false)
const formRef = ref(null)

// Diálogo de motivo (rechazar / anular).
const reasonDialog = ref({ open: false, mode: 'reject' })

const isOwner = computed(() => request.value && authStore.user?.id === request.value.requested_by)
const isEditable = computed(() => request.value?.status === 'draft' && isOwner.value && can('purchase_requests.create'))
const canSubmit = computed(() => request.value?.status === 'draft' && isOwner.value && can('purchase_requests.create'))
const canResolve = computed(() => request.value?.status === 'submitted' && can('purchase_requests.approve'))
const canCancel = computed(() => ['draft', 'submitted', 'approved'].includes(request.value?.status) && can('purchase_requests.approve'))
const canOrder = computed(() => ['approved', 'partially_ordered'].includes(request.value?.status) && can('purchase_orders.create'))

const load = async () => {
  try {
    const res = await purchaseRequestsApi.get(requestId)

    request.value = res?.data || null
  }
  catch {
    notify.error('No se pudo cargar el requerimiento')
    router.push({ name: 'purchases-requests' })
  }
  finally {
    isLoading.value = false
  }
}

onMounted(load)

const run = async (action, successMessage) => {
  isBusy.value = true
  try {
    await action()
    notify.success(successMessage)
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
    await purchaseRequestsApi.update(requestId, payload)
    if (action === 'send')
      await purchaseRequestsApi.submit(requestId)

    notify.success(action === 'send' ? 'Requerimiento enviado correctamente' : 'Requerimiento actualizado correctamente')
    await load()
  }
  catch (err) {
    formRef.value?.setErrors(err)
  }
  finally {
    isBusy.value = false
  }
}

const submitRequest = () => run(() => purchaseRequestsApi.submit(requestId), 'Requerimiento enviado correctamente')
const approve = () => run(() => purchaseRequestsApi.approve(requestId), 'Requerimiento aprobado correctamente')

const openReason = mode => {
  reasonDialog.value = { open: true, mode }
}

const confirmReason = async reason => {
  const mode = reasonDialog.value.mode

  reasonDialog.value.open = false
  await run(
    () => (mode === 'reject' ? purchaseRequestsApi.reject(requestId, reason) : purchaseRequestsApi.cancel(requestId, reason)),
    mode === 'reject' ? 'Requerimiento rechazado correctamente' : 'Requerimiento anulado correctamente',
  )
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <PageHeader
          :title="request ? `Requerimiento ${request.number}` : 'Cargando requerimiento...'"
          :description="request ? `${request.area?.name || ''} · ${request.warehouse?.name || ''}` : undefined"
        >
          <template #actions>
            <VChip
              v-if="request"
              size="small"
              class="text-uppercase"
              :color="requestStatusColor(request.status)"
            >
              {{ requestStatusTitle(request.status) }}
            </VChip>
            <VBtn
              v-if="canSubmit && !isEditable"
              color="success"
              prepend-icon="tabler-send"
              :loading="isBusy"
              @click="submitRequest"
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
              @click="openReason('reject')"
            >
              Rechazar
            </VBtn>
            <VBtn
              v-if="canOrder"
              color="primary"
              prepend-icon="tabler-shopping-cart-plus"
              @click="router.push({ name: 'purchases-orders-new', query: { request: request.id } })"
            >
              Crear orden de compra
            </VBtn>
            <VBtn
              v-if="canCancel"
              color="error"
              variant="outlined"
              prepend-icon="tabler-ban"
              :disabled="isBusy"
              @click="openReason('cancel')"
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

    <template v-if="request">
      <VAlert
        v-if="request.status === 'rejected'"
        type="error"
        variant="tonal"
        class="mb-6"
      >
        <strong>Rechazado.</strong> {{ request.rejection_reason }}
      </VAlert>
      <VAlert
        v-if="request.status === 'cancelled'"
        type="warning"
        variant="tonal"
        class="mb-6"
      >
        <strong>Anulado.</strong> {{ request.cancel_reason }}
      </VAlert>

      <!-- Borrador del propio solicitante: se edita -->
      <VCard v-if="isEditable">
        <VCardText>
          <PurchaseRequestForm
            ref="formRef"
            :request="request"
            :is-submitting="isBusy"
            @submit="handleSave"
            @cancel="router.push({ name: 'purchases-requests' })"
          />
        </VCardText>
      </VCard>

      <!-- Resto de casos: solo lectura -->
      <template v-else>
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Solicitante
                </div>
                <div class="font-weight-medium">
                  {{ request.requester?.name || '—' }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Fecha requerida
                </div>
                <div class="font-weight-medium">
                  {{ formatDateDMY(request.required_date) }}
                </div>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Prioridad
                </div>
                <VChip
                  size="small"
                  :color="request.priority === 'urgent' ? 'error' : 'secondary'"
                >
                  {{ request.priority_label }}
                </VChip>
              </VCol>
              <VCol
                cols="12"
                md="3"
              >
                <div class="text-caption text-medium-emphasis">
                  Aprobado por
                </div>
                <div class="font-weight-medium">
                  {{ request.approver?.name || '—' }}
                </div>
              </VCol>
              <VCol
                v-if="request.notes"
                cols="12"
              >
                <div class="text-caption text-medium-emphasis">
                  Notas
                </div>
                <div>{{ request.notes }}</div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard>
          <VCardText>
            <PurchaseRequestItemsTable :items="request.items" />
          </VCardText>
        </VCard>
      </template>
    </template>

    <CancelDocumentDialog
      v-model="reasonDialog.open"
      :title="reasonDialog.mode === 'reject' ? 'Rechazar requerimiento' : 'Anular requerimiento'"
      :document-number="request?.number || ''"
      :description="reasonDialog.mode === 'reject'
        ? 'El requerimiento quedará rechazado y el solicitante deberá crear uno nuevo.'
        : 'El requerimiento quedará anulado. Solo es posible si no tiene órdenes de compra.'"
      :reason-label="reasonDialog.mode === 'reject' ? 'Motivo del rechazo *' : 'Motivo de anulación *'"
      reason-placeholder="Ej. Presupuesto agotado para este periodo"
      :confirm-label="reasonDialog.mode === 'reject' ? 'Confirmar rechazo' : 'Confirmar anulación'"
      required-message="El motivo es obligatorio."
      :loading="isBusy"
      @confirm="confirmReason"
    />
  </div>
</template>
