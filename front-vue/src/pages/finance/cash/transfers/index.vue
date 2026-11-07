<script setup>
import { computed, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import CashTransferActionDialog from '@/modules/finance/components/cash/CashTransferActionDialog.vue'
import CashTransferDetailDialog from '@/modules/finance/components/cash/CashTransferDetailDialog.vue'
import { cashTransfersApi } from '@/modules/finance/services/cashTransfersApi'
import { useCashTransfersStore } from '@/modules/finance/stores/cashTransfersStore'
import { formatDateTime12h } from '@/utils/dates'

definePage({
  meta: {
    action: ['cash_transfers.view', 'cash_transfers.create'],
    title: 'Transferencias de Caja',
    icon: 'tabler-arrows-left-right',
    description: 'Control de envíos y recepciones de efectivo entre cajas físicas con flujo de aprobación.',
  },
})

const { can } = useAcl()
const authStore = useAuthStore()
const transfersStore = useCashTransfersStore()
const { confirm, alert } = useConfirm()
const notify = useNotify()

const formatCurrency = val => {
  if (val === null || val === undefined || val === '')
    return '0.00'
  const num = Number(val)

  return Number.isNaN(num) ? '0.00' : num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const currentUserId = computed(() => authStore.user?.id)

// Pestañas: 'pending' (si puede aprobar) o 'sent'
const activeTab = ref(can('cash_transfers.approve') ? 'pending' : 'sent')

// Diálogos
const isDetailOpen = ref(false)
const selectedTransfer = ref(null)

const isActionDialogOpen = ref(false)
const actionType = ref('reject') // 'reject' | 'cancel'
const actionTransfer = ref(null)
const isSubmittingAction = ref(false)
const actionDialogRef = ref(null)

const STATUS_FILTER_OPTIONS = [
  { value: 'pending', title: 'Pendiente' },
  { value: 'approved', title: 'Aprobada' },
  { value: 'rejected', title: 'Rechazada' },
  { value: 'cancelled', title: 'Cancelada' },
]

const fetchTransfers = async params => {
  const res = await cashTransfersApi.list(params)

  if (can('cash_transfers.approve')) {
    transfersStore.fetchPendingCount()
  }

  return res
}

const table = useServerTable({
  fetch: fetchTransfers,
  filters: {
    status: null,
  },
  mapParams: params => {
    const mapped = { ...params }
    if (activeTab.value === 'pending') {
      mapped.status = 'pending'
      delete mapped.direction
    }
    else {
      mapped.direction = activeTab.value
    }

    return mapped
  },
})

watch(activeTab, () => {
  if (table.filters.status !== null) {
    table.filters.status = null
  }
  else if (table.options.page !== 1) {
    table.options.page = 1
  }
  else {
    table.reload()
  }
})

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'ORIGEN (SALIDA)', key: 'source_box', sortable: false },
  { title: 'DESTINO (INGRESO)', key: 'target_box', sortable: false },
  { title: 'MONTO', key: 'amount', sortable: false, align: 'end' },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'MOTIVO', key: 'reason', sortable: false },
  { title: 'FECHA', key: 'created_at', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

// Acciones
const openDetail = async item => {
  try {
    const res = await cashTransfersApi.get(item.id)

    selectedTransfer.value = res?.data || item
    isDetailOpen.value = true
  }
  catch {
    selectedTransfer.value = item
    isDetailOpen.value = true
  }
}

const handleApprove = async item => {
  const isConfirmed = await confirm({
    title: '¿Aprobar transferencia?',
    message: `¿Confirmas la recepción de ${formatCurrency(item.amount)} en la caja de destino? Esta acción registrará de forma inmediata la salida y entrada en ambas cajas.`,
    confirmText: 'Aprobar transferencia',
    color: 'success',
    icon: 'tabler-check',
  })

  if (!isConfirmed)
    return

  try {
    await cashTransfersApi.approve(item.id)
    notify.success('Transferencia aprobada correctamente.')
    await table.reload()
  }
  catch (error) {
    alert({
      title: 'No se pudo aprobar',
      message: error?.data?.message || error?.message || 'No se pudo aprobar la transferencia.',
    })
  }
}

const openReject = item => {
  actionTransfer.value = item
  actionType.value = 'reject'
  isActionDialogOpen.value = true
}

const openCancel = item => {
  actionTransfer.value = item
  actionType.value = 'cancel'
  isActionDialogOpen.value = true
}

const handleActionSubmit = async reason => {
  isSubmittingAction.value = true
  try {
    if (actionType.value === 'reject') {
      await cashTransfersApi.reject(actionTransfer.value.id, reason)
      notify.success('Transferencia rechazada correctamente.')
    }
    else {
      await cashTransfersApi.cancel(actionTransfer.value.id, reason)
      notify.success('Transferencia cancelada correctamente.')
    }
    isActionDialogOpen.value = false
    await table.reload()
  }
  catch (error) {
    if (actionDialogRef.value?.setErrors && error?.response?.data?.errors) {
      actionDialogRef.value.setErrors(error.response.data.errors)
    }
    else {
      alert({ message: error?.response?.data?.message || 'Error al procesar la acción.' })
    }
  }
  finally {
    isSubmittingAction.value = false
  }
}
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VTabs
        v-model="activeTab"
        class="v-tabs-pill mx-6 mb-4"
      >
        <VTab
          v-if="can('cash_transfers.approve')"
          value="pending"
        >
          <VIcon
            start
            icon="tabler-clock"
          />
          Pendientes de aprobar
          <VBadge
            v-if="transfersStore.pendingCount > 0"
            color="warning"
            :content="transfersStore.pendingCount"
            inline
            class="ms-2"
          />
        </VTab>
        <VTab value="sent">
          <VIcon
            start
            icon="tabler-arrow-up-right"
          />
          Enviadas
        </VTab>
        <VTab value="received">
          <VIcon
            start
            icon="tabler-arrow-down-left"
          />
          Recibidas
        </VTab>
      </VTabs>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="cash-transfers"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="activeTab !== 'pending' && table.hasFilters"
        search-placeholder="TC-000001"
        :creatable="false"
        @refresh="table.reload"
      >
        <!-- Slot de Filtros -->
        <template
          v-if="activeTab !== 'pending'"
          #filters
        >
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <VSelect
                v-model="table.filters.status"
                :items="STATUS_FILTER_OPTIONS"
                label="Estado"
                placeholder="Ej. Aprobada"
                clearable
                density="compact"
              />
            </VCol>
          </VRow>
        </template>

        <!-- Columna Número -->
        <template #item.number="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.number }}</span>
        </template>

        <!-- Columna Caja Origen -->
        <template #item.source_box="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.source_box?.name || 'N/A' }}
            </div>
            <div class="text-caption text-disabled">
              {{ item.source_branch?.name || '' }}
            </div>
          </div>
        </template>

        <!-- Columna Caja Destino -->
        <template #item.target_box="{ item }">
          <div>
            <div class="font-weight-medium">
              {{ item.target_box?.name || 'N/A' }}
            </div>
            <div class="text-caption text-disabled">
              {{ item.target_branch?.name || '' }}
            </div>
          </div>
        </template>

        <!-- Columna Monto -->
        <template #item.amount="{ item }">
          <span class="font-weight-bold text-success">{{ formatCurrency(item.amount) }}</span>
        </template>

        <!-- Columna Estado -->
        <template #item.status="{ item }">
          <VChip
            size="small"
            class="text-uppercase"
            :color="item.status_color"
          >
            {{ item.status_label }}
          </VChip>
        </template>

        <!-- Columna Motivo -->
        <template #item.reason="{ item }">
          <span
            class="text-truncate d-inline-block"
            style="max-width: 220px;"
            :title="item.reason"
          >
            {{ item.reason }}
          </span>
        </template>

        <!-- Columna Fecha -->
        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDateTime12h(item.created_at) }}</span>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <!-- Ver Detalle -->
            <RowActionButton
              action="view"
              tooltip="Ver detalle"
              @click="openDetail(item)"
            />

            <!-- Botón Aprobar (Solo si es pending, usuario puede aprobar y no es el creador) -->
            <RowActionButton
              v-if="item.status === 'pending' && can('cash_transfers.approve') && item.creator?.id !== currentUserId"
              action="custom"
              color="success"
              tooltip="Aprobar transferencia"
              icon="tabler-check"
              @click="handleApprove(item)"
            />

            <!-- Botón Rechazar (Solo si es pending y usuario puede aprobar) -->
            <RowActionButton
              v-if="item.status === 'pending' && can('cash_transfers.approve')"
              action="custom"
              color="error"
              tooltip="Rechazar transferencia"
              icon="tabler-x"
              @click="openReject(item)"
            />

            <!-- Botón Cancelar (Solo si es pending y es el creador de la transferencia) -->
            <RowActionButton
              v-if="item.status === 'pending' && item.creator?.id === currentUserId"
              action="custom"
              color="secondary"
              tooltip="Cancelar mi solicitud"
              icon="tabler-ban"
              @click="openCancel(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Diálogo de Detalle -->
    <CashTransferDetailDialog
      v-model:is-dialog-open="isDetailOpen"
      :transfer="selectedTransfer"
    />

    <!-- Diálogo de Rechazo / Cancelación -->
    <CashTransferActionDialog
      ref="actionDialogRef"
      v-model:is-dialog-open="isActionDialogOpen"
      :transfer="actionTransfer"
      :action-type="actionType"
      :is-submitting="isSubmittingAction"
      @submit="handleActionSubmit"
    />
  </div>
</template>
