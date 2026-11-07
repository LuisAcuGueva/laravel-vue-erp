<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import UserFormDrawer from '@/modules/users/components/UserFormDrawer.vue'
import { usersApi } from '@/modules/users/services/usersApi'
import { useUsersStore } from '@/modules/users/stores/usersStore'
import { useRolesStore } from '@/modules/roles/stores/rolesStore'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'users.view',
    title: 'Usuarios',
    icon: 'tabler-user',
    description: 'Gestión de usuarios del sistema, sus roles y sedes asignadas',
  },
})

const usersStore = useUsersStore()
const rolesStore = useRolesStore()
const authStore = useAuthStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const isDrawerOpen = ref(false)
const selectedUser = ref(null)
const isSubmitting = ref(false)
const serverErrors = ref({})
const deletingId = ref(null)

// `ListUsersRequest` espera status = active | inactive (STATUS_OPTIONS llega como '1' / '0').
const USER_STATUS = { 1: 'active', 0: 'inactive' }

const table = useServerTable({
  fetch: params => usersStore.fetchUsers(params),
  filters: { status: null, role: null },
  mapParams: ({ status, ...params }) => ({ ...params, status: USER_STATUS[status] }),
})

const headers = [
  { title: 'USUARIO', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'CORREO', key: 'email', sortable: false },
  { title: 'ROLES', key: 'roles', sortable: false },
  { title: 'SEDES', key: 'branches', sortable: false },
  { title: 'ESTADO', key: 'status', sortable: false, align: 'center' },
  { title: 'ÚLTIMO ACCESO', key: 'last_login_at', sortable: false },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const availableRolesList = computed(() => rolesStore.items.map(r => r.name))

onMounted(async () => {
  if (can('roles.view')) {
    try {
      await rolesStore.fetchRoles({ 'per_page': 100 })
    }
    catch {
      // Sin acceso a roles: el filtro queda vacío.
    }
  }
})

const openCreateDrawer = () => {
  selectedUser.value = null
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const openEditDrawer = user => {
  selectedUser.value = { ...user }
  serverErrors.value = {}
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  serverErrors.value = {}
  try {
    let savedUser
    if (formData.id) {
      savedUser = await usersStore.updateUser(formData.id, formData)
    }
    else {
      savedUser = await usersStore.createUser(formData)
    }

    const userId = formData.id || savedUser?.id || savedUser?.data?.id
    if (userId && Array.isArray(formData.branch_ids) && formData.branch_ids.length > 0) {
      await usersApi.syncBranches(userId, {
        'branch_ids': formData.branch_ids,
        'default_branch_id': formData.default_branch_id || formData.branch_ids[0],
      })
    }

    notify.success(formData.id ? 'Usuario actualizado correctamente' : 'Usuario creado correctamente')
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    if (err?.data?.errors)
      serverErrors.value = err.data.errors
    else
      notify.error(err?.data?.message || 'No se pudo guardar el usuario.')
  }
  finally {
    isSubmitting.value = false
  }
}

const removeUser = async user => {
  if (!await confirmDelete({ entity: 'usuario', name: `${user.name} (${user.email})` }))
    return

  deletingId.value = user.id
  try {
    await usersStore.deleteUser(user.id)
    notify.success('Usuario eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el usuario.' })
  }
  finally {
    deletingId.value = null
  }
}

const statusColor = status => {
  switch (status) {
  case 'active': return 'success'
  case 'inactive': return 'secondary'
  default: return 'primary'
  }
}

const statusLabel = status => {
  switch (status) {
  case 'active': return 'Activo'
  case 'inactive': return 'Inactivo'
  default: return status
  }
}

const formatLastLogin = value => (value ? new Date(value).toLocaleString('es-PE') : 'Nunca')
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
        table-id="users"
        :headers="headers"
        :items="usersStore.items"
        :total-items="usersStore.meta.total"
        :loading="usersStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Ana Torres o ana@empresa.com"
        create-label="Nuevo usuario"
        :creatable="can('users.create')"
        :exportable="can('users.export')"
        :exporting="table.exporting"
        @refresh="table.reload"
        @create="openCreateDrawer"
        @export="table.export('/v1/users/export', 'usuarios')"
      >
        <template #filters>
          <VSelect
            v-model="table.filters.status"
            :items="STATUS_OPTIONS"
            label="Estado"
            placeholder="Activo"
            density="compact"
            clearable
          />
          <VSelect
            v-model="table.filters.role"
            :items="availableRolesList"
            label="Rol"
            placeholder="admin"
            density="compact"
            clearable
          />
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar
              size="32"
              color="primary"
              variant="tonal"
            >
              <span class="text-xs font-weight-medium text-uppercase">
                {{ item.name ? item.name.slice(0, 2) : 'US' }}
              </span>
            </VAvatar>
            <span class="font-weight-medium text-high-emphasis">
              {{ item.name }}
            </span>
          </div>
        </template>

        <template #item.roles="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <VChip
              v-for="role in (item.roles || [])"
              :key="role"
              size="x-small"
              color="primary"
              variant="tonal"
              class="text-capitalize"
            >
              {{ role }}
            </VChip>
            <span
              v-if="!item.roles || item.roles.length === 0"
              class="text-disabled text-caption"
            >
              Sin roles
            </span>
          </div>
        </template>

        <template #item.branches="{ item }">
          <div class="d-flex gap-1 flex-wrap">
            <VTooltip
              v-for="branch in (item.branches || [])"
              :key="branch.id"
              :text="branch.is_default ? `${branch.name} · Sede predeterminada del usuario` : branch.name"
            >
              <template #activator="{ props: tooltipProps }">
                <VChip
                  v-bind="tooltipProps"
                  size="x-small"
                  :color="branch.is_default ? 'info' : 'default'"
                  variant="tonal"
                >
                  <VIcon
                    v-if="branch.is_default"
                    start
                    icon="tabler-pinned-filled"
                    size="12"
                  />
                  {{ branch.code || branch.name }}
                </VChip>
              </template>
            </VTooltip>
            <span
              v-if="!item.branches || item.branches.length === 0"
              class="text-disabled text-caption"
            >
              Sin sedes
            </span>
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="statusColor(item.status)"
            size="small"
            variant="tonal"
          >
            {{ statusLabel(item.status) }}
          </VChip>
        </template>

        <template #item.last_login_at="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ formatLastLogin(item.last_login_at) }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              v-if="can('users.update')"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('users.delete') && item.id !== authStore.user?.id"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeUser(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer para Crear / Editar -->
    <UserFormDrawer
      v-model:is-drawer-open="isDrawerOpen"
      :user-data="selectedUser"
      :available-roles="availableRolesList"
      :is-submitting="isSubmitting"
      :server-errors="serverErrors"
      @submit="handleFormSubmit"
    />
  </div>
</template>
