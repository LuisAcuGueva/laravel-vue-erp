<script setup>
import { computed, ref, watch } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import PermissionFormDrawer from '@/modules/permissions/components/PermissionFormDrawer.vue'
import { usePermissionsStore } from '@/modules/permissions/stores/permissionsStore'

definePage({
  meta: {
    action: 'permissions.view',
    title: 'Permisos',
    icon: 'tabler-key',
    description: 'Catálogo de permisos disponibles por módulo',
  },
})

const permissionsStore = usePermissionsStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const isDrawerOpen = ref(false)
const selectedPermission = ref(null)
const isSubmitting = ref(false)
const serverErrors = ref({})
const deletingId = ref(null)

const table = useServerTable({
  fetch: params => permissionsStore.fetchPermissions(params),
  filters: { module: null },
})

const headers = [
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'SLUG', key: 'slug', sortable: false },
  { title: 'MÓDULO', key: 'module', sortable: false },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false, maxWidth: 320 },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

// Módulos conocidos más los que aparecen al paginar (el filtro no depende de la página visible).
const knownModules = ref(new Set(['users', 'roles', 'permissions', 'clients', 'reports']))

watch(() => permissionsStore.items, items => {
  for (const item of items || []) {
    if (item.module)
      knownModules.value.add(item.module)
  }
}, { immediate: true })

const moduleOptions = computed(() => [...knownModules.value].sort())

watch(isDrawerOpen, val => {
  if (!val) {
    selectedPermission.value = null
    serverErrors.value = {}
  }
})

const openCreateDrawer = () => {
  selectedPermission.value = null
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const openEditDrawer = permission => {
  selectedPermission.value = { ...permission }
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  serverErrors.value = {}
  try {
    if (formData.id) {
      await permissionsStore.updatePermission(formData.id, formData)
      notify.success('Permiso actualizado correctamente')
    }
    else {
      await permissionsStore.createPermission(formData)
      notify.success('Permiso creado correctamente')
    }
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    if (err?.data?.errors)
      serverErrors.value = err.data.errors
    else
      notify.error(err?.data?.message || 'No se pudo guardar el permiso.')
  }
  finally {
    isSubmitting.value = false
  }
}

const removePermission = async permission => {
  if (!await confirmDelete({ entity: 'permiso', name: `${permission.name} (${permission.slug})` }))
    return

  deletingId.value = permission.id
  try {
    await permissionsStore.deletePermission(permission.id)
    notify.success('Permiso eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el permiso.' })
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
        table-id="permissions"
        :headers="headers"
        :items="permissionsStore.items"
        :total-items="permissionsStore.meta.total"
        :loading="permissionsStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="clients.view"
        create-label="Nuevo permiso"
        :creatable="can('permissions.create')"
        @refresh="table.reload"
        @create="openCreateDrawer"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.module"
            :items="moduleOptions"
            label="Módulo"
            placeholder="clients"
            density="compact"
            clearable
          />
        </template>

        <template #item.name="{ item }">
          <span class="font-weight-medium text-high-emphasis">
            {{ item.name }}
          </span>
        </template>

        <template #item.slug="{ item }">
          <code class="text-body-2 font-weight-medium text-high-emphasis">
            {{ item.slug }}
          </code>
        </template>

        <template #item.module="{ item }">
          <VChip
            size="small"
            color="primary"
            variant="tonal"
            class="text-capitalize"
          >
            {{ item.module }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              v-if="can('permissions.update')"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('permissions.delete')"
              action="delete"
              :loading="deletingId === item.id"
              @click="removePermission(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer para Crear / Editar -->
    <PermissionFormDrawer
      v-model:is-drawer-open="isDrawerOpen"
      :permission-data="selectedPermission"
      :is-submitting="isSubmitting"
      :server-errors="serverErrors"
      @submit="handleFormSubmit"
    />
  </div>
</template>
