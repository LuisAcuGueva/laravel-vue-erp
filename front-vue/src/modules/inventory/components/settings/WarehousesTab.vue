<script setup>
import { computed, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import WarehouseFormDrawer from './WarehouseFormDrawer.vue'
import { warehousesApi } from '../../services/inventorySettingsApi'
import { useInventorySettingsStore } from '../../stores/inventorySettingsStore'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { SETTINGS_DRAWERS_TARGET } from '../../utils/options'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const inventoryStore = useInventorySettingsStore()
const authStore = useAuthStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedWarehouse = ref(null)
const deletingId = ref(null)

// Solo las sedes accesibles del usuario (las mismas que valida la API).
const branchOptions = computed(() => authStore.userBranches.map(b => ({
  value: b.id,
  title: b.code ? `${b.name} (${b.code})` : b.name,
})))

const table = useServerTable({
  fetch: params => warehousesApi.list(params),
  filters: { 'branch_id': null, 'is_active': null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false },
  { title: 'SEDE', key: 'branch', sortable: false },
  { title: 'DIRECCIÓN', key: 'address', sortable: false, maxWidth: 240 },
  { title: 'POR DEFECTO', key: 'is_default', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedWarehouse.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = warehouse => {
  selectedWarehouse.value = { ...warehouse }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await warehousesApi.update(id, payload)
      notify.success('Almacén actualizado correctamente')
    }
    else {
      await warehousesApi.create(payload)
      notify.success('Almacén creado correctamente')
    }
    isDrawerOpen.value = false
    inventoryStore.invalidateWarehouses()
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

const removeWarehouse = async warehouse => {
  if (!await confirmDelete({ entity: 'almacén', name: `${warehouse.name} (${warehouse.code})` }))
    return

  deletingId.value = warehouse.id
  try {
    await warehousesApi.delete(warehouse.id)
    notify.success('Almacén eliminado correctamente')
    inventoryStore.invalidateWarehouses()
    await table.reload()
  }
  catch (err) {
    // Reglas de negocio de la API (ej. almacén con stock o movimientos).
    await alert({ message: err?.data?.message || 'No se pudo eliminar el almacén.' })
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
    table-id="inventory-warehouses"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="ALM01 o Almacén Principal"
    create-label="Nuevo almacén"
    :creatable="can('warehouses.create')"
    :exportable="can('warehouses.export')"
    :exporting="table.exporting"
    @refresh="table.reload"
    @create="openCreateDrawer"
    @export="table.export('/v1/inventory/warehouses/export', 'almacenes')"
  >
    <template #filters>
      <VAutocomplete
        v-model="table.filters.branch_id"
        :items="branchOptions"
        label="Sede"
        placeholder="Sede Principal"
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

    <template #item.branch="{ item }">
      <span>{{ item.branch?.name || '—' }}</span>
    </template>

    <template #item.address="{ item }">
      <span>{{ item.address || '—' }}</span>
    </template>

    <template #item.is_default="{ item }">
      <VChip
        v-if="item.is_default"
        size="small"
        color="warning"
        variant="tonal"
        prepend-icon="tabler-star-filled"
      >
        Por defecto
      </VChip>
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
          v-if="can('warehouses.update')"
          action="edit"
          tooltip="Editar almacén"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('warehouses.delete')"
          action="delete"
          tooltip="Eliminar almacén"
          :loading="deletingId === item.id"
          @click="removeWarehouse(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <WarehouseFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :warehouse-data="selectedWarehouse"
      :branch-options="branchOptions"
      :default-branch-id="authStore.activeBranchId"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
