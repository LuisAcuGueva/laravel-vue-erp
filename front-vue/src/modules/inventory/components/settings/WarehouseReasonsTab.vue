<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import WarehouseReasonFormDrawer from './WarehouseReasonFormDrawer.vue'
import { warehouseReasonsApi } from '../../services/inventorySettingsApi'
import { useInventorySettingsStore } from '../../stores/inventorySettingsStore'
import { SETTINGS_DRAWERS_TARGET, WAREHOUSE_REASON_TYPE_COLORS, WAREHOUSE_REASON_TYPE_OPTIONS } from '../../utils/options'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const inventoryStore = useInventorySettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedReason = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => warehouseReasonsApi.list(params),
  filters: { type: null, 'is_active': null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'TIPO', key: 'type', sortable: false },
  { title: 'APROBACIÓN', key: 'requires_approval', sortable: false, align: 'center' },
  { title: 'SISTEMA', key: 'is_system', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedReason.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = reason => {
  selectedReason.value = { ...reason }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await warehouseReasonsApi.update(id, payload)
      notify.success('Motivo actualizado correctamente')
    }
    else {
      await warehouseReasonsApi.create(payload)
      notify.success('Motivo creado correctamente')
    }
    isDrawerOpen.value = false
    inventoryStore.invalidateWarehouseReasons()
    await table.reload()
  }
  catch (err) {
    // El drawer muestra los errores de campo y el mensaje general de la API.
    formDrawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeReason = async reason => {
  if (!await confirmDelete({ entity: 'motivo', name: `${reason.name} (${reason.code})` }))
    return

  deletingId.value = reason.id
  try {
    await warehouseReasonsApi.delete(reason.id)
    notify.success('Motivo eliminado correctamente')
    inventoryStore.invalidateWarehouseReasons()
    await table.reload()
  }
  catch (err) {
    // Reglas de negocio de la API (ej. motivo usado en movimientos).
    await alert({ message: err?.data?.message || 'No se pudo eliminar el motivo.' })
  }
  finally {
    deletingId.value = null
  }
}
</script>

<template>
  <ServerDataTable
    v-model:options="table.options"
    v-model:search="table.search"
    table-id="inventory-warehouse-reasons"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Merma o SHRINKAGE"
    create-label="Nuevo motivo"
    :creatable="can('warehouse_reasons.create')"
    :exportable="can('warehouse_reasons.export')"
    :exporting="table.exporting"
    @refresh="table.reload"
    @create="openCreateDrawer"
    @export="table.export('/v1/inventory/warehouse-reasons/export', 'motivos_almacen')"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.type"
        :items="WAREHOUSE_REASON_TYPE_OPTIONS"
        label="Tipo"
        placeholder="Salida"
        density="compact"
        clearable
      />

      <VSelect
        v-model="table.filters.is_active"
        :items="STATUS_OPTIONS"
        label="Estado"
        placeholder="Activo"
        density="compact"
        clearable
      />
    </template>

    <template #item.code="{ item }">
      <span class="font-monospace font-weight-bold">{{ item.code }}</span>
    </template>

    <template #item.name="{ item }">
      <span class="font-weight-medium">{{ item.name }}</span>
    </template>

    <template #item.type="{ item }">
      <VChip
        size="small"
        :color="WAREHOUSE_REASON_TYPE_COLORS[item.type] || 'primary'"
        variant="tonal"
      >
        {{ item.type_label || item.type }}
      </VChip>
    </template>

    <template #item.requires_approval="{ item }">
      <VIcon
        :icon="item.requires_approval ? 'tabler-check' : 'tabler-x'"
        :color="item.requires_approval ? 'success' : 'secondary'"
        size="20"
      />
    </template>

    <template #item.is_system="{ item }">
      <VTooltip
        v-if="item.is_system"
        text="Motivo de sistema: el código, el tipo y la aprobación no se modifican"
      >
        <template #activator="{ props }">
          <VIcon
            v-bind="props"
            icon="tabler-lock"
            color="warning"
            size="20"
          />
        </template>
      </VTooltip>
      <span
        v-else
        class="text-disabled"
      >—</span>
    </template>

    <template #item.is_active="{ item }">
      <VChip
        size="small"
        :color="item.is_active ? 'success' : 'secondary'"
      >
        {{ item.is_active ? 'Activo' : 'Inactivo' }}
      </VChip>
    </template>

    <template #item.actions="{ item }">
      <div class="d-flex justify-center gap-1">
        <RowActionButton
          v-if="can('warehouse_reasons.update')"
          action="edit"
          tooltip="Editar motivo"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('warehouse_reasons.delete')"
          action="delete"
          :tooltip="item.is_system ? 'Los motivos de sistema no se pueden eliminar' : 'Eliminar motivo'"
          :disabled="item.is_system"
          :loading="deletingId === item.id"
          @click="removeReason(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <WarehouseReasonFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :reason-data="selectedReason"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
