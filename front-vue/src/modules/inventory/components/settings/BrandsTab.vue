<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BrandFormDrawer from './BrandFormDrawer.vue'
import { brandsApi } from '../../services/inventorySettingsApi'
import { useInventorySettingsStore } from '../../stores/inventorySettingsStore'
import { SETTINGS_DRAWERS_TARGET } from '../../utils/options'
import { STATUS_OPTIONS } from '@/utils/options'

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const inventoryStore = useInventorySettingsStore()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const isSubmitting = ref(false)
const selectedBrand = ref(null)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => brandsApi.list(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedBrand.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = brand => {
  selectedBrand.value = { ...brand }
  isDrawerOpen.value = true
}

const handleFormSubmit = async ({ id, ...payload }) => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (id) {
      await brandsApi.update(id, payload)
      notify.success('Marca actualizada correctamente')
    }
    else {
      await brandsApi.create(payload)
      notify.success('Marca creada correctamente')
    }
    isDrawerOpen.value = false
    inventoryStore.invalidateBrands()
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

const removeBrand = async brand => {
  if (!await confirmDelete({ entity: 'marca', name: brand.name }))
    return

  deletingId.value = brand.id
  try {
    await brandsApi.delete(brand.id)
    notify.success('Marca eliminada correctamente')
    inventoryStore.invalidateBrands()
    await table.reload()
  }
  catch (err) {
    // Reglas de negocio de la API (ej. marca usada por productos).
    await alert({ message: err?.data?.message || 'No se pudo eliminar la marca.' })
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
    table-id="inventory-brands"
    :headers="headers"
    :items="table.items"
    :total-items="table.total"
    :loading="table.loading"
    :has-filters="table.hasFilters"
    search-placeholder="Samsung"
    create-label="Nueva marca"
    :creatable="can('brands.create')"
    :exportable="can('brands.export')"
    :exporting="table.exporting"
    @refresh="table.reload"
    @create="openCreateDrawer"
    @export="table.export('/v1/inventory/brands/export', 'marcas')"
  >
    <template #filters>
      <VSelect
        v-model="table.filters.is_active"
        :items="STATUS_OPTIONS"
        label="Estado"
        placeholder="Activo"
        density="compact"
        clearable
      />
    </template>

    <template #item.name="{ item }">
      <span class="font-weight-medium">{{ item.name }}</span>
    </template>

    <template #item.description="{ item }">
      <span>{{ item.description || '—' }}</span>
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
          v-if="can('brands.update')"
          action="edit"
          tooltip="Editar marca"
          @click="openEditDrawer(item)"
        />
        <RowActionButton
          v-if="can('brands.delete')"
          action="delete"
          tooltip="Eliminar marca"
          :loading="deletingId === item.id"
          @click="removeBrand(item)"
        />
      </div>
    </template>
  </ServerDataTable>

  <Teleport
    defer
    :to="SETTINGS_DRAWERS_TARGET"
  >
    <BrandFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :brand-data="selectedBrand"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </Teleport>
</template>
