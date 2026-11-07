<script setup>
import { onMounted, ref } from 'vue'
import { useAcl } from '@/composables/useAcl'
import { useConfirm } from '@/composables/useConfirm'
import { useNotify } from '@/composables/useNotify'
import { useServerTable } from '@/composables/useServerTable'
import PageHeader from '@/components/common/PageHeader.vue'
import ServerDataTable from '@/components/common/ServerDataTable.vue'
import RowActionButton from '@/components/common/data-table/RowActionButton.vue'
import EmployeeFormDrawer from '@/modules/maintenance/components/EmployeeFormDrawer.vue'
import { areasApi } from '@/modules/maintenance/services/areasApi'
import { branchesApi } from '@/modules/maintenance/services/branchesApi'
import { useEmployeesStore } from '@/modules/maintenance/stores/employeesStore'
import { STATUS_OPTIONS } from '@/utils/options'

definePage({
  meta: {
    action: 'employees.view',
    title: 'Empleados',
    icon: 'tabler-id-badge-2',
    description: 'Gestión del padrón de personal y vinculación de cuentas de acceso',
  },
})

const employeesStore = useEmployeesStore()
const { can } = useAcl()
const { confirmDelete, alert } = useConfirm()
const notify = useNotify()

const formDrawerRef = ref(null)
const isDrawerOpen = ref(false)
const selectedEmployee = ref(null)
const isSubmitting = ref(false)
const deletingId = ref(null)

const branchFilterOptions = ref([])
const areaFilterOptions = ref([])

const HAS_USER_OPTIONS = [
  { title: 'Con acceso', value: true },
  { title: 'Sin acceso', value: false },
]

const table = useServerTable({
  fetch: params => employeesStore.fetchEmployees(params),
  filters: { 'branch_id': null, 'area_id': null, 'is_active': null, 'has_user': null },
})

const headers = [
  { title: 'EMPLEADO', key: 'full_name', sortable: false, alwaysVisible: true },
  { title: 'DOCUMENTO', key: 'document_number', sortable: false },
  { title: 'CARGO', key: 'position', sortable: false },
  { title: 'SEDE', key: 'branch', sortable: false },
  { title: 'ÁREA', key: 'area', sortable: false },
  { title: 'ACCESO AL SISTEMA', key: 'user', sortable: false, align: 'center' },
  { title: 'ESTADO', key: 'is_active', sortable: false, align: 'center' },
  { title: 'ACCIONES', key: 'actions', sortable: false, align: 'center' },
]

const loadFilterCatalogs = async () => {
  try {
    const [branchesRes, areasRes] = await Promise.all([
      branchesApi.options(),
      areasApi.options(),
    ])

    branchFilterOptions.value = (branchesRes?.data || []).map(b => ({
      title: `${b.code} - ${b.name}`,
      value: b.id,
    }))

    areaFilterOptions.value = (areasRes?.data || []).map(a => ({
      title: a.name,
      value: a.id,
    }))
  }
  catch {
    notify.error('No se pudieron cargar los filtros de sede y área.')
  }
}

onMounted(loadFilterCatalogs)

const openCreateDrawer = () => {
  selectedEmployee.value = null
  isDrawerOpen.value = true
}

const openEditDrawer = employee => {
  selectedEmployee.value = { ...employee }
  isDrawerOpen.value = true
}

const handleFormSubmit = async formData => {
  isSubmitting.value = true
  formDrawerRef.value?.clearErrors()
  try {
    if (formData.id) {
      await employeesStore.updateEmployee(formData.id, formData)
      notify.success('Empleado actualizado correctamente')
    }
    else {
      await employeesStore.createEmployee(formData)
      notify.success('Empleado creado correctamente')
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

const removeEmployee = async employee => {
  if (!await confirmDelete({ entity: 'empleado', name: employee.full_name }))
    return

  deletingId.value = employee.id
  try {
    await employeesStore.deleteEmployee(employee.id)
    notify.success('Empleado eliminado correctamente')
    await table.reload()
  }
  catch (err) {
    await alert({ message: err?.data?.message || 'No se pudo eliminar el empleado.' })
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
        table-id="employees"
        :headers="headers"
        :items="employeesStore.items"
        :total-items="employeesStore.meta.total"
        :loading="employeesStore.isLoading"
        :has-filters="table.hasFilters"
        search-placeholder="Juan Pérez o 12345678"
        create-label="Nuevo empleado"
        :creatable="can('employees.create')"
        :exportable="can('employees.export')"
        :exporting="table.exporting"
        @refresh="table.reload"
        @create="openCreateDrawer"
        @export="table.export('/v1/employees/export', 'empleados')"
      >
        <template #filters>
          <VAutocomplete
            v-model="table.filters.branch_id"
            :items="branchFilterOptions"
            label="Sede"
            placeholder="SEDE-01"
            density="compact"
            clearable
          />
          <VAutocomplete
            v-model="table.filters.area_id"
            :items="areaFilterOptions"
            label="Área"
            placeholder="Ventas"
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
          <VSelect
            v-model="table.filters.has_user"
            :items="HAS_USER_OPTIONS"
            label="Acceso"
            placeholder="Con acceso"
            density="compact"
            clearable
          />
        </template>

        <!-- Columna Empleado (nombre y contacto) -->
        <template #item.full_name="{ item }">
          <div class="d-flex flex-column py-1">
            <span class="font-weight-medium text-body-1 text-high-emphasis">
              {{ item.full_name }}
            </span>
            <span
              v-if="item.email || item.phone"
              class="text-caption text-medium-emphasis"
            >
              {{ [item.email, item.phone].filter(Boolean).join(' • ') }}
            </span>
          </div>
        </template>

        <!-- Columna Documento -->
        <template #item.document_number="{ item }">
          <div class="d-flex align-center gap-1">
            <VChip
              size="x-small"
              variant="outlined"
              color="secondary"
            >
              {{ item.document_type }}
            </VChip>
            <span class="font-weight-medium font-monospace">{{ item.document_number }}</span>
          </div>
        </template>

        <!-- Columna Cargo -->
        <template #item.position="{ item }">
          <span>{{ item.position || '—' }}</span>
        </template>

        <!-- Columna Sede -->
        <template #item.branch="{ item }">
          <span v-if="item.branch">{{ item.branch.code }} - {{ item.branch.name }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <!-- Columna Área -->
        <template #item.area="{ item }">
          <span v-if="item.area">{{ item.area.name }}</span>
          <span
            v-else
            class="text-medium-emphasis"
          >—</span>
        </template>

        <!-- Columna Acceso al Sistema -->
        <template #item.user="{ item }">
          <div v-if="item.user">
            <VChip
              color="primary"
              size="small"
              variant="tonal"
              prepend-icon="tabler-user-check"
            >
              {{ item.user.email }}
            </VChip>
          </div>
          <div v-else>
            <VChip
              size="small"
              color="secondary"
              variant="outlined"
            >
              Sin acceso
            </VChip>
          </div>
        </template>

        <!-- Columna Estado -->
        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'secondary'"
            size="small"
            class="text-uppercase"
          >
            {{ item.is_active ? 'Activo' : 'Inactivo' }}
          </VChip>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <RowActionButton
              v-if="can('employees.update')"
              action="edit"
              tooltip="Editar empleado"
              @click="openEditDrawer(item)"
            />
            <RowActionButton
              v-if="can('employees.delete')"
              action="delete"
              tooltip="Eliminar empleado"
              :loading="deletingId === item.id"
              @click="removeEmployee(item)"
            />
          </div>
        </template>
      </ServerDataTable>
    </VCard>

    <!-- Drawer de Formulario -->
    <EmployeeFormDrawer
      ref="formDrawerRef"
      v-model:is-drawer-open="isDrawerOpen"
      :employee-data="selectedEmployee"
      :is-submitting="isSubmitting"
      @submit="handleFormSubmit"
      @user-updated="table.reload"
    />
  </div>
</template>
