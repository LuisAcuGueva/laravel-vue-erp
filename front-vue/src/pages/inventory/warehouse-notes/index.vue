<script setup>
/* eslint-disable camelcase */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { formatDateDMY } from '@/utils/dates'
import CancelDocumentDialog from '@/components/common/CancelDocumentDialog.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import { warehousesApi } from '@/modules/inventory/services/inventorySettingsApi'
import { warehouseNotesApi } from '@/modules/inventory/services/warehouseNotesApi'

definePage({
  meta: {
    action: 'warehouse_notes.view',
    title: 'Notas de almacén',
    icon: 'tabler-file-invoice',
    description: 'Registro, aprobación y seguimiento de movimientos físicos y traslados de inventario',
  },
})

const router = useRouter()
const { can } = useAcl()
const { confirm, alert } = useConfirm()
const notify = useNotify()

const NOTE_TYPE_OPTIONS = [
  { title: 'Ingreso', value: 'in' },
  { title: 'Salida', value: 'out' },
  { title: 'Traslado', value: 'transfer' },
]

const NOTE_STATUS_OPTIONS = [
  { title: 'Borrador', value: 'draft' },
  { title: 'Aprobada', value: 'approved' },
  { title: 'Anulada', value: 'cancelled' },
]

const headers = [
  { title: 'NÚMERO', key: 'number', sortable: false, alwaysVisible: true },
  { title: 'FECHA', key: 'document_date', sortable: false },
  { title: 'TIPO', key: 'type', sortable: false },
  { title: 'ALMACÉN / DESTINO', key: 'warehouse', sortable: false },
  { title: 'MOTIVO', key: 'reason', sortable: false },
  { title: 'TOTAL (S/)', key: 'total_cost', align: 'end', sortable: false },
  { title: 'ESTADO', key: 'status', align: 'center', sortable: false },
  { title: 'CREADO POR', key: 'created_by', sortable: false },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false },
]

const warehouses = ref([])

const isCancelling = ref(false)
const cancelDialogOpen = ref(false)
const noteToCancel = ref(null)

const table = useServerTable({
  fetch: params => warehouseNotesApi.list(params),
  filters: {
    type: null,
    status: null,
    warehouse_id: null,
    date_from: null,
    date_to: null,
  },
})

const formatCurrency = val => {
  const num = Number(val || 0)
  
  return num.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const typeColor = type => {
  if (type === 'in') return 'success'
  if (type === 'out') return 'warning'
  if (type === 'transfer') return 'info'
  
  return 'secondary'
}

const statusColor = status => {
  if (status === 'draft') return 'secondary'
  if (status === 'approved') return 'success'
  if (status === 'cancelled') return 'error'
  
  return 'default'
}

const handleApprove = async note => {
  const confirmed = await confirm({
    title: 'Aprobar nota de almacén',
    message: `¿Estás seguro de aprobar la nota ${note.number}? Esta acción afectará el stock inmediatamente.`,
    confirmText: 'Sí, aprobar',
    cancelText: 'Cancelar',
  })

  if (!confirmed) return

  try {
    await warehouseNotesApi.approve(note.id)
    notify.success('Nota de almacén aprobada correctamente')
    table.reload()
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al aprobar la nota de almacén'

    alert({ title: 'No se pudo aprobar', message })
  }
}

const openCancelDialog = note => {
  noteToCancel.value = note
  cancelDialogOpen.value = true
}

const handleConfirmCancel = async reason => {
  if (!noteToCancel.value) return

  isCancelling.value = true
  try {
    await warehouseNotesApi.cancel(noteToCancel.value.id, { reason })
    notify.success('Nota de almacén anulada correctamente')
    cancelDialogOpen.value = false
    table.reload()
  }
  catch (err) {
    const message = err?.data?.message || err?.message || 'Error al anular la nota de almacén'

    alert({ title: 'No se pudo anular', message })
  }
  finally {
    isCancelling.value = false
  }
}

onMounted(async () => {
  try {
    const res = await warehousesApi.options()

    warehouses.value = res?.data || []
  }
  catch (err) {
    console.error('Error al cargar almacenes', err)
  }
})
</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <PageHeader />
      </VCardText>

      <VDivider />

      <ServerDataTable
        v-model:options="table.options"
        v-model:search="table.search"
        table-id="warehouse-notes"
        :headers="headers"
        :items="table.items"
        :total-items="table.total"
        :loading="table.loading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por número, p. ej. NA-000001"
        create-label="Nueva nota"
        :creatable="can('warehouse_notes.create')"
        @refresh="table.reload"
        @create="router.push({ name: 'inventory-warehouse-notes-new' })"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.type"
            :items="NOTE_TYPE_OPTIONS"
            label="Tipo de nota"
            placeholder="Ingreso"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.status"
            :items="NOTE_STATUS_OPTIONS"
            label="Estado"
            placeholder="Aprobada"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.warehouse_id"
            :items="warehouses"
            item-title="name"
            item-value="id"
            label="Almacén"
            placeholder="Almacén Central"
            density="compact"
            clearable
          />
          <VTextField
            v-model="table.filters.date_from"
            type="date"
            label="Desde"
            density="compact"
            clearable
          />
          <VTextField
            v-model="table.filters.date_to"
            type="date"
            label="Hasta"
            density="compact"
            clearable
          />
        </template>

        <!-- Número -->
        <template #[`item.number`]="{ item }">
          <span
            class="font-weight-bold text-primary cursor-pointer"
            @click="router.push({ name: 'inventory-warehouse-notes-id', params: { id: item.id } })"
          >
            {{ item.number }}
          </span>
        </template>

        <!-- Fecha -->
        <template #[`item.document_date`]="{ item }">
          <span>{{ formatDateDMY(item.document_date) }}</span>
        </template>

        <!-- Tipo -->
        <template #[`item.type`]="{ item }">
          <VChip
            :color="typeColor(item.type)"
            size="small"
            variant="tonal"
          >
            {{ item.type_label }}
          </VChip>
        </template>

        <!-- Almacén -->
        <template #[`item.warehouse`]="{ item }">
          <div>
            <span>{{ item.warehouse?.name || '—' }}</span>
            <span
              v-if="item.target_warehouse"
              class="text-caption text-medium-emphasis ms-1"
            >
              → {{ item.target_warehouse.name }}
            </span>
          </div>
        </template>

        <!-- Motivo -->
        <template #[`item.reason`]="{ item }">
          <span>{{ item.reason?.name || '—' }}</span>
        </template>

        <!-- Total Cost -->
        <template #[`item.total_cost`]="{ item }">
          <span class="font-weight-medium">S/ {{ formatCurrency(item.total_cost) }}</span>
        </template>

        <!-- Estado -->
        <template #[`item.status`]="{ item }">
          <VChip
            :color="statusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ item.status_label }}
          </VChip>
        </template>

        <!-- Creador -->
        <template #[`item.created_by`]="{ item }">
          <span class="text-caption">{{ item.created_by?.name || '—' }}</span>
        </template>

        <!-- Acciones -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <!-- Ver -->
            <RowActionButton
              action="view"
              tooltip="Ver detalle"
              color="info"
              @click="router.push({ name: 'inventory-warehouse-notes-id', params: { id: item.id } })"
            />

            <!-- Editar (solo en borrador) -->
            <RowActionButton
              v-if="item.status === 'draft' && can('warehouse_notes.update')"
              action="edit"
              tooltip="Editar nota"
              color="success"
              @click="router.push({ name: 'inventory-warehouse-notes-id', params: { id: item.id } })"
            />

            <!-- Aprobar (solo en borrador) -->
            <RowActionButton
              v-if="item.status === 'draft' && can('warehouse_notes.approve')"
              icon="tabler-check"
              tooltip="Aprobar nota"
              color="primary"
              @click="handleApprove(item)"
            />

            <!-- Anular (aprobada o borrador, no anulada) -->
            <RowActionButton
              v-if="item.status !== 'cancelled' && can('warehouse_notes.cancel')"
              icon="tabler-ban"
              tooltip="Anular nota"
              color="error"
              @click="openCancelDialog(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <CancelDocumentDialog
      v-model="cancelDialogOpen"
      :document-number="noteToCancel?.number"
      title="Anular nota de almacén"
      :loading="isCancelling"
      @confirm="handleConfirmCancel"
    />
  </div>
</template>
