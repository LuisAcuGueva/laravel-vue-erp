<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import AreaFormDrawer from '@/modules/maintenance/components/AreaFormDrawer.vue'
import { useAreasStore } from '@/modules/maintenance/stores/areasStore'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'areas.view',
    title: 'Áreas',
    icon: 'tabler-sitemap',
    description: 'Gestión de áreas organizacionales para clasificación de personal y requerimientos',
  },
})

const areasStore = useAreasStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedArea = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => areasStore.fetchAreas(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false, maxWidth: 360 },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedArea.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = area => {
  selectedArea.value = { ...area }
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await areasStore.updateArea(formData.id, formData)
      notify.success('Área actualizada correctamente')
    }
    else {
      await areasStore.createArea(formData)
      notify.success('Área creada correctamente')
    }
    isDrawerOpen.value = false
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

const removeArea = async area => {
  if (!await confirmDelete({ entity: 'área', name: area.name }))
    return

  deletingId.value = area.id
  try {
    await areasStore.deleteArea(area.id)
    notify.success('Área eliminada correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el área.' })
  }
  finally {
    deletingId.value = null
  }
}
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
        table-id="areas"
        :headers="headers"
        :items="areasStore.items"
        :total-items="areasStore.meta.total"
        :loading="areasStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Ventas"
        create-label="Nueva área"
        :creatable="can('areas.create')"
        :exportable="can('areas.export')"
        :exporting="table.exporting"
        @refresh="table.reload"
        @create="openCreateDrawer"
        @export="table.export('/v1/areas/export', 'areas')"
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

        <!-- Nombre -->
        <template #item.name="{ item }">
          <span class="font-weight-medium">{{ item.name }}</span>
        </template>

        <!-- Estado -->
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            {{ item.is_active ? 'Activo' : 'Inactivo' }}
          </VChip>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              v-if="can('areas.update')"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('areas.delete')"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeArea(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer de Formulario -->
    <AreaFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :area-data="selectedArea"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </div>
</template>
