<script setup>
import { onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import BoxFormDrawer from '@/modules/finance/components/boxes/BoxFormDrawer.vue'
import { boxesApi } from '@/modules/finance/services/cashApi'
import { rolesApi } from '@/modules/roles/services/rolesApi'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'boxes.view',
    title: 'Cajas Registradoras',
    icon: 'tabler-device-desktop',
    description: 'Gestión de cajas físicas de cobranza, control de acceso por roles y estado.',
  },
})

const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const rolesList = ref([])
const isDrawerOpen = ref(false)
const selectedBox = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)
const drawerRef = ref(null)

const items = ref([])
const totalItems = ref(0)
const isLoading = ref(false)

const loadRoles = async () => {
  try {
    const res = await rolesApi.list({ 'per_page': 100 })

    rolesList.value = res?.data || []
  }
  catch {
    rolesList.value = []
  }
}

onMounted(() => {
  loadRoles()
})

const fetchBoxes = async params => {
  isLoading.value = true
  try {
    const res = await boxesApi.list(params)

    items.value = res?.data || []
    totalItems.value = res?.meta?.total ?? items.value.length

    return res
  }
  finally {
    isLoading.value = false
  }
}

const table = useServerTable({
  fetch: fetchBoxes,
  filters: { 'is_active': null },
})

const headers = [
  { title: 'CÓDIGO', key: 'code', sortable: false, alwaysVisible: true },
  { title: 'NOMBRE', key: 'name', sortable: false, alwaysVisible: true },
  { title: 'SEDE', key: 'branch', sortable: false },
  { title: 'ROLES ASIGNADOS', key: 'roles', sortable: false },
  { title: 'SESIÓN ACTIVA', key: 'open_session', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const openCreateDrawer = () => {
  selectedBox.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = box => {
  selectedBox.value = { ...box }
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  drawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await boxesApi.update(formData.id, formData)
      notify.success('Caja actualizada correctamente')
    }
    else {
      await boxesApi.create(formData)
      notify.success('Caja creada correctamente')
    }
    isDrawerOpen.value = false
    await table.reload()
  }
  catch (err) {
    drawerRef.value?.setErrors(err)
  }
  finally {
    isSubmitting.value = false
  }
}

const removeBox = async box => {
  if (!await confirmDelete({ entity: 'caja registradora', name: `${box.name} (${box.code})` }))
    return

  deletingId.value = box.id
  try {
    await boxesApi.delete(box.id)
    notify.success('Caja eliminada correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar la caja.' })
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
        table-id="boxes"
        :headers="headers"
        :items="items"
        :total-items="totalItems"
        :loading="isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Buscar por código o nombre..."
        create-label="Nueva caja"
        :creatable="can('boxes.manage')"
        @refresh="table.reload"
        @create="openCreateDrawer"
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
            <div class="font-weight-medium">
              {{ item.name }}
            </div>
            <div
              v-if="item.description"
              class="text-caption text-disabled"
            >
              {{ item.description }}
            </div>
          </div>
        </template>

        <!-- Sede -->
        <template #item.branch="{ item }">
          <span>{{ item.branch?.name || '—' }}</span>
        </template>

        <!-- Roles -->
        <template #item.roles="{ item }">
          <div
            v-if="item.roles && item.roles.length > 0"
            class="d-flex flex-wrap gap-1"
          >
            <VChip
              v-for="role in item.roles"
              :key="role.id"
              size="x-small"
              variant="tonal"
              color="primary"
            >
              {{ role.name }}
            </VChip>
          </div>
          <span
            v-else
            class="text-disabled text-caption"
          >Sin roles</span>
        </template>

        <!-- Sesión activa -->
        <template #item.open_session="{ item }">
          <VChip
            v-if="item.open_session"
            size="small"
            color="success"
            variant="tonal"
          >
            Abierta: {{ item.open_session.number }}
          </VChip>
          <VChip
            v-else
            size="small"
            color="secondary"
            variant="tonal"
          >
            Cerrada
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
              v-if="can('boxes.manage')"
              action="edit"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('boxes.manage')"
              action="delete"
              :loading="deletingId === item.id"
              @click="removeBox(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <BoxFormDrawer
      ref="drawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :box-data="selectedBox"
      :roles-list="rolesList"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
    />
  </div>
</template>
