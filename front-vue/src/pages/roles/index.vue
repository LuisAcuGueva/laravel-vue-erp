<script setup>
import { onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import RoleFormDrawer from '@/modules/roles/components/RoleFormDrawer.vue'
import RolePermissionsDialog from '@/modules/roles/components/RolePermissionsDialog.vue'
import { useRolesStore } from '@/modules/roles/stores/rolesStore'
import { usePermissionsStore } from '@/modules/permissions/stores/permissionsStore'

definePage({
  meta: {
    action: 'roles.view',
    title: 'Roles',
    icon: 'tabler-shield',
    description: 'Gestión de roles y de los permisos asignados a cada uno',
  },
})

const rolesStore = useRolesStore()
const permissionsStore = usePermissionsStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const isDrawerOpen = ref(false)
const selectedRole = ref(null)
const isSubmitting = ref(false)
const serverErrors = ref({})
const deletingId = ref(null)

const isPermissionsModalOpen = ref(false)
const roleForPermissions = ref(null)
const isSavingPermissions = ref(false)

const table = useServerTable({
  fetch: params => rolesStore.fetchRoles(params),
})

const headers = [
  { title: 'ROL', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'DESCRIPCIÓN', key: 'description', sortable: false, maxWidth: 320 },
  { title: 'USUARIOS', key: 'users_count', sortable: false, align: 'center' },
  { title: 'PERMISOS ASIGNADOS', key: 'permissions', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

onMounted(() => {
  permissionsStore.fetchPermissions({ 'per_page': 100 }).catch(() => {
    notify.error('No se pudo cargar el catálogo de permisos.')
  })
})

const openCreateDrawer = () => {
  selectedRole.value = null
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const openEditDrawer = role => {
  selectedRole.value = { ...role }
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const openPermissionsModal = role => {
  roleForPermissions.value = { ...role }
  isPermissionsModalOpen.value = true
}

const handleSaveRolePermissions = async ({ roleId, permissions }) => {
  if (!roleForPermissions.value) return

  isSavingPermissions.value = true
  try {
    await rolesStore.updateRole(roleId, {
      name: roleForPermissions.value.name,
      description: roleForPermissions.value.description,
      permissions,
    })
    notify.success('Permisos del rol actualizados correctamente')
    isPermissionsModalOpen.value = false
    await table.reload()
  }
  catch (err) {
    notify.error(err?.data?.message || 'No se pudieron actualizar los permisos del rol.')
  }
  finally {
    isSavingPermissions.value = false
  }
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  serverErrors.value = {}
  try {
    if (formData.id) {
      await rolesStore.updateRole(formData.id, formData)
      notify.success('Rol actualizado correctamente')
    }
    else {
      await rolesStore.createRole(formData)
      notify.success('Rol creado correctamente')
    }
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    if (err?.data?.errors)
      serverErrors.value = err.data.errors
    else
      notify.error(err?.data?.message || 'No se pudo guardar el rol.')
  }
  finally {
    isSubmitting.value = false
  }
}

const removeRole = async role => {
  if (!await confirmDelete({ entity: 'rol', name: role.name }))
    return

  deletingId.value = role.id
  try {
    await rolesStore.deleteRole(role.id)
    notify.success('Rol eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el rol.' })
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
        table-id="roles"
        :headers="headers"
        :items="rolesStore.items"
        :total-items="rolesStore.meta.total"
        :loading="rolesStore.isLoading"
        search-placeholder="vendedor"
        create-label="Nuevo rol"
        :creatable="can('roles.create')"
        @refresh="table.reload"
        @create="openCreateDrawer"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2">
            <span class="font-weight-medium text-high-emphasis">
              {{ item.name }}
            </span>
            <VChip
              v-if="item.is_protected"
              size="x-small"
              color="warning"
              variant="tonal"
              prepend-icon="tabler-lock"
            >
              Protegido
            </VChip>
          </div>
        </template>

        <template #item.users_count="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="secondary"
          >
            {{ item.users_count || 0 }} {{ (item.users_count === 1) ? 'usuario' : 'usuarios' }}
          </VChip>
        </template>

        <template #item.permissions="{ item }">
          <div
            class="d-flex gap-1 flex-wrap align-center"
            style="max-inline-size: 480px;"
          >
            <VChip
              v-for="perm in (item.permissions || []).slice(0, 3)"
              :key="perm"
              size="x-small"
              color="info"
              variant="tonal"
            >
              {{ perm }}
            </VChip>
            <VChip
              v-if="(item.permissions || []).length > 3"
              size="x-small"
              color="secondary"
              variant="outlined"
            >
              +{{ item.permissions.length - 3 }} más
            </VChip>
            <span
              v-if="!item.permissions || item.permissions.length === 0"
              class="text-disabled text-caption"
            >
              Sin permisos
            </span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              v-if="can('roles.update')"
              action="custom"
              :icon="item.is_protected ? 'tabler-lock' : 'tabler-key'"
              :tooltip="item.is_protected ? 'Rol protegido por el sistema' : 'Gestionar permisos'"
              :disabled="item.is_protected"
              @click="openPermissionsModal(item)"
            />
            <RowActionButton
              v-if="can('roles.update')"
              action="edit"
              :disabled="item.is_protected"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('roles.delete') && !item.is_protected"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeRole(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Modal para Gestionar Permisos -->
    <RolePermissionsDialog
      v-model:is-dialog-open="isPermissionsModalOpen"
      :role="roleForPermissions"
      :available-permissions="permissionsStore.items"
      :is-submitting="isSavingPermissions"
      @save="handleSaveRolePermissions"
    />

    <!-- Drawer para Crear / Editar -->
    <RoleFormDrawer
      v-model:is-drawer-open="isDrawerOpen"
      :role-data="selectedRole"
      :is-submitting="isSubmitting"
      :server-errors="serverErrors"
      @submit="handleFormSubmit"
    />
  </div>
</template>
