<script setup>
import { ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BranchFormDrawer from '@/modules/maintenance/components/BranchFormDrawer.vue'
import { useBranchesStore } from '@/modules/maintenance/stores/branchesStore'
import { useUbigeo } from '@/modules/ubigeo/composables/useUbigeo'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'branches.view',
    title: 'Sedes',
    icon: 'tabler-building-store',
    description: 'Gestión de sedes físicas y sucursales de la empresa',
  },
})

const branchesStore = useBranchesStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()
const { describe: describeUbigeo } = useUbigeo()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedBranch = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => branchesStore.fetchBranches(params),
  filters: { 'is_active': null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'DIRECCIÓN', key: 'address', sortable: false, maxWidth: 240 },
  { title: 'UBICACIÓN', key: 'ubigeo', sortable: false },
  { title: 'TELÉFONO', key: 'phone', sortable: false },
  { title: 'PRINCIPAL', key: 'is_main', sortable: false, align: 'center' },
  { title: 'USUARIOS', key: 'users_count', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedBranch.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = branch => {
  selectedBranch.value = { ...branch }
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await branchesStore.updateBranch(formData.id, formData)
      notify.success('Sede actualizada correctamente')
    }
    else {
      await branchesStore.createBranch(formData)
      notify.success('Sede creada correctamente')
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

const removeBranch = async branch => {
  if (!await confirmDelete({ entity: 'sede', name: `${branch.name} (${branch.code})` }))
    return

  deletingId.value = branch.id
  try {
    await branchesStore.deleteBranch(branch.id)
    notify.success('Sede eliminada correctamente')
    await table.reload()
  }
  catch (err) {
    // Reglas de negocio de la API (ej. sede principal o con usuarios asignados).
    await alert({ message: err?.data?.message || 'No se pudo eliminar la sede.' })
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
        table-id="branches"
        :headers="headers"
        :items="branchesStore.items"
        :total-items="branchesStore.meta.total"
        :loading="branchesStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="SEDE-01 o Sede Central"
        create-label="Nueva sede"
        :creatable="can('branches.create')"
        :exportable="can('branches.export')"
        :exporting="table.exporting"
        @refresh="table.reload"
        @create="openCreateDrawer"
        @export="table.export('/v1/branches/export', 'sedes')"
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

        <!-- Código -->
        <template #item.code="{ item }">
          <span class="font-weight-medium font-monospace">{{ item.code }}</span>
        </template>

        <!-- Nombre -->
        <template #item.name="{ item }">
          <div>
            <span class="font-weight-medium">{{ item.name }}</span>
            <div
              v-if="item.email"
              class="text-caption text-disabled"
            >
              {{ item.email }}
            </div>
          </div>
        </template>

        <!-- Ubicación -->
        <template #item.ubigeo="{ item }">
          <span>{{ describeUbigeo(item.ubigeo) }}</span>
        </template>

        <!-- Teléfono -->
        <template #item.phone="{ item }">
          <span>{{ item.phone || '—' }}</span>
        </template>

        <!-- Principal -->
        <template #item.is_main="{ item }">
          <VTooltip
            v-if="item.is_main"
            text="Sede principal"
          >
            <template #activator="{ props: tooltipProps }">
              <VIcon
                v-bind="tooltipProps"
                icon="tabler-star-filled"
                color="warning"
                size="22"
              />
            </template>
          </VTooltip>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- Usuarios -->
        <template #item.users_count="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            {{ item.users_count ?? 0 }}
          </VChip>
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
              v-if="can('branches.update')"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('branches.delete')"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeBranch(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer de Formulario -->
    <BranchFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :branch-data="selectedBranch"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </div>
</template>
